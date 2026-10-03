<?php

namespace Tests\Containment;

use App\Controllers\NewPurchaseOrders;
use Tests\Support\ContainmentTestCase;

/**
 * NewPurchaseOrders::resetToRfq() sends a purchase order back to its RFQ so the
 * buyer can correct it and confirm again.
 *
 * That transition is only safe while the PO is a purely commercial document. The
 * moment it has touched stock (a GRN, or any received quantity) or the ledger (a
 * vendor bill, an advance payment), reverting it would leave those postings
 * pointing at a document that no longer exists — inventory valued against a
 * deleted PO, an unmatched payable. Every test below is one of those exploits.
 *
 * The happy path additionally asserts the PO row itself is gone rather than left
 * as a cancelled shell: purchase_orders.po_number is UNIQUE and both
 * NewPurchaseRfqs::accept() and ::confirm() pick up "the existing PO for this
 * RFQ", so a surviving shell would either block re-confirmation on the unique
 * index or silently resurrect the stale document with its old lines.
 */
final class PurchaseOrderResetToRfqTest extends ContainmentTestCase
{
    /** resetToRfq() runs its own transBegin/transCommit; clean up by watermark. */
    protected bool $useTransaction = false;

    protected array $cleanupFrom = [
        'purchase_order_lines' => 0,
        'purchase_orders'      => 0,
        'purchase_rfq_lines'   => 0,
        'purchase_rfqs'        => 0,
        'purchase_grns'        => 0,
        'vendor_bills'         => 0,
        'document_logs'        => 0,
        'vendors'              => 0,
    ];

    private function makeVendor(): int
    {
        $this->db->table('vendors')->insert([
            'vendor_code' => 'TEST-V-' . uniqid(),
            'name'        => 'Reset Fixture Vendor',
        ]);

        return (int) $this->db->insertID();
    }

    /**
     * An RFQ that has been converted into a confirmed PO, exactly as
     * NewPurchaseRfqs::confirm() leaves it: one line each side, same figures.
     *
     * @return array{rfq_id:int, po_id:int, vendor_id:int}
     */
    private function makeConvertedPo(string $poStatus = 'confirmed'): array
    {
        $vendorId = $this->makeVendor();
        $suffix   = uniqid();

        $this->db->table('purchase_rfqs')->insert([
            'rfq_number'  => 'TEST-RFQ-' . $suffix,
            'vendor_id'   => $vendorId,
            'status'      => 'confirmed',
            'currency'    => 'PKR',
            'subtotal'    => 1000,
            'tax_amount'  => 100,
            'total_tax'   => 100,
            'grand_total' => 1100,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        $rfqId = (int) $this->db->insertID();

        $this->db->table('purchase_rfq_lines')->insert([
            'rfq_id'      => $rfqId,
            'product_id'  => 1,
            'description' => 'Fixture line',
            'quantity'    => 10,
            'unit_cost'   => 100,
            'line_total'  => 1000,
            'tax_percent' => 10,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        $this->db->table('purchase_orders')->insert([
            'po_number'  => 'TEST-RFQ-' . $suffix,
            'rfq_id'     => $rfqId,
            'vendor_id'  => $vendorId,
            'status'     => $poStatus,
            'currency'   => 'PKR',
            'order_date' => date('Y-m-d'),
            'subtotal'   => 1000,
            'tax_total'  => 100,
            'total'      => 1100,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $poId = (int) $this->db->insertID();

        $this->db->table('purchase_order_lines')->insert([
            'po_id'        => $poId,
            'product_id'   => 1,
            'description'  => 'Fixture line',
            'qty'          => 10,
            'unit_price'   => 100,
            'line_total'   => 1000,
            'qty_received' => 0,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        return ['rfq_id' => $rfqId, 'po_id' => $poId, 'vendor_id' => $vendorId];
    }

    /** Drive the real endpoint and return the decoded JSON body. */
    private function reset(int $poId): array
    {
        $controller = new NewPurchaseOrders();

        $request  = \Config\Services::request();
        $response = \Config\Services::response();
        $logger   = \Config\Services::logger();

        if (method_exists($request, 'withMethod')) {
            $request = $request->withMethod('POST');
        }

        $controller->initController($request, $response, $logger);

        $result = $controller->resetToRfq((string) $poId);

        return json_decode((string) $result->getBody(), true) ?: [];
    }

    private function poExists(int $poId): bool
    {
        return (int) $this->db->table('purchase_orders')->where('id', $poId)->countAllResults() > 0;
    }

    private function rfqStatus(int $rfqId): string
    {
        $row = $this->db->table('purchase_rfqs')->select('status')->where('id', $rfqId)->get()->getRowArray();

        return (string) ($row['status'] ?? '');
    }

    /**
     * Happy path: nothing received, nothing billed. PO disappears, RFQ is a
     * draft again and carries the PO's lines and totals.
     */
    public function testCleanPoIsResetToADraftRfq(): void
    {
        $fx = $this->makeConvertedPo();

        $json = $this->reset($fx['po_id']);

        self::assertTrue((bool) ($json['success'] ?? false), 'Reset should succeed: ' . ($json['error'] ?? ''));
        self::assertSame($fx['rfq_id'], (int) ($json['rfq_id'] ?? 0));
        self::assertFalse($this->poExists($fx['po_id']), 'PO row must be gone, not left as a shell.');
        self::assertSame(
            0,
            (int) $this->db->table('purchase_order_lines')->where('po_id', $fx['po_id'])->countAllResults(),
            'PO lines must be gone with their PO.'
        );
        self::assertSame('draft', $this->rfqStatus($fx['rfq_id']));

        $lines = $this->db->table('purchase_rfq_lines')->where('rfq_id', $fx['rfq_id'])->get()->getResultArray();
        self::assertCount(1, $lines);
        self::assertEquals(10, (float) $lines[0]['quantity']);
        self::assertEquals(100, (float) $lines[0]['unit_cost']);
        // Tax % lives only on the RFQ line, so it must survive the round trip.
        self::assertEquals(10, (float) $lines[0]['tax_percent']);

        $rfq = $this->db->table('purchase_rfqs')->where('id', $fx['rfq_id'])->get()->getRowArray();
        self::assertEquals(1100, (float) $rfq['grand_total'], 'RFQ totals must match the PO being reverted.');
    }

    /** A goods receipt means stock has already moved. */
    public function testPoWithGoodsReceiptCannotBeReset(): void
    {
        $fx = $this->makeConvertedPo();

        $this->db->table('purchase_grns')->insert([
            'grn_number'  => 'TEST-GRN-' . uniqid(),
            'po_id'       => $fx['po_id'],
            'vendor_id'   => $fx['vendor_id'],
            'received_at' => date('Y-m-d H:i:s'),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        $json = $this->reset($fx['po_id']);

        self::assertFalse((bool) ($json['success'] ?? false));
        self::assertStringContainsString('goods receipt', strtolower((string) ($json['error'] ?? '')));
        self::assertTrue($this->poExists($fx['po_id']));
        self::assertSame('confirmed', $this->rfqStatus($fx['rfq_id']), 'A refused reset must not touch the RFQ.');
    }

    /** Partial receipt without a GRN header row is still stock movement. */
    public function testPoWithReceivedQuantityCannotBeReset(): void
    {
        $fx = $this->makeConvertedPo();

        $this->db->table('purchase_order_lines')
            ->where('po_id', $fx['po_id'])
            ->update(['qty_received' => 4]);

        $json = $this->reset($fx['po_id']);

        self::assertFalse((bool) ($json['success'] ?? false));
        self::assertStringContainsString('received', strtolower((string) ($json['error'] ?? '')));
        self::assertTrue($this->poExists($fx['po_id']));
    }

    /** An open vendor bill is a payable that would be orphaned. */
    public function testPoWithVendorBillCannotBeReset(): void
    {
        $fx = $this->makeConvertedPo();

        $this->db->table('vendor_bills')->insert([
            'bill_number' => 'TEST-BILL-' . uniqid(),
            'vendor_id'   => $fx['vendor_id'],
            'po_id'       => $fx['po_id'],
            'bill_date'   => date('Y-m-d'),
            'status'      => 'confirmed',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        $json = $this->reset($fx['po_id']);

        self::assertFalse((bool) ($json['success'] ?? false));
        self::assertStringContainsString('vendor bill', strtolower((string) ($json['error'] ?? '')));
        self::assertTrue($this->poExists($fx['po_id']));
        self::assertSame('confirmed', $this->rfqStatus($fx['rfq_id']));
    }

    /** Closed / cancelled POs are terminal states; only live commercial ones revert. */
    public function testClosedPoCannotBeReset(): void
    {
        $fx = $this->makeConvertedPo('closed');

        $json = $this->reset($fx['po_id']);

        self::assertFalse((bool) ($json['success'] ?? false));
        self::assertTrue($this->poExists($fx['po_id']));
    }

    /** A PO born without an RFQ (e.g. the shipping PO flow) has nothing to go back to. */
    public function testPoWithoutRfqCannotBeReset(): void
    {
        $fx = $this->makeConvertedPo();
        $this->db->table('purchase_orders')->where('id', $fx['po_id'])->update(['rfq_id' => null]);

        $json = $this->reset($fx['po_id']);

        self::assertFalse((bool) ($json['success'] ?? false));
        self::assertStringContainsString('not created from an rfq', strtolower((string) ($json['error'] ?? '')));
        self::assertTrue($this->poExists($fx['po_id']));
    }

    /** The endpoint must stay behind the purchase_orders.edit permission. */
    public function testResetRouteIsPermissionGuarded(): void
    {
        $routes = $this->sourceOf('app/Config/Routes.php');
        self::assertStringContainsString("new-purchase-orders/(:segment)/reset-to-rfq", $routes);

        $permissions = $this->sourceOf('app/Config/RoutePermissions.php');
        self::assertStringContainsString("'POST:new-purchase-orders/*/reset-to-rfq' => 'purchase_orders.edit'", $permissions);
    }
}
