<?php

namespace Tests\Containment;

use App\Controllers\Quotations;
use App\Controllers\SalesOrders;
use Tests\Support\ContainmentTestCase;

/**
 * Sales documents freeze the customer address/contact they were issued with.
 *
 * Before this, every quotation, order and invoice rendered the address live from
 * customer_addresses, and the row it picked was ordered by is_billing/is_shipping
 * only -- so the customer profile's "default address" toggle did nothing, and
 * editing a customer silently rewrote the address printed on invoices that had
 * already been sent and paid.
 *
 * The refresh action exists for the legitimate case (wrong address on a document
 * still being negotiated). Everything below pins the boundary of that action: it
 * must not reach a document that anything downstream depends on.
 */
final class CustomerAddressSnapshotTest extends ContainmentTestCase
{
    /** The refresh endpoints run their own transBegin/transCommit. */
    protected bool $useTransaction = false;

    protected array $cleanupFrom = [
        'customer_invoices'  => 0,
        'sales_orders'       => 0,
        'quotations'         => 0,
        'customer_addresses' => 0,
        'customers'          => 0,
        'document_logs'      => 0,
        'users'              => 0,
    ];

    /**
     * Both endpoints call requirePermission(), which hard-exits when the caller
     * is anonymous. Give the test an admin session; UserModel::hasPermission()
     * short-circuits on the admin role, so no RBAC fixture is needed.
     */
    private function loginAsAdmin(): void
    {
        $this->db->table('users')->insert([
            'username'  => 'test-admin-' . uniqid(),
            'email'     => uniqid() . '@example.test',
            'password_hash' => password_hash('x', PASSWORD_DEFAULT),
            'first_name'    => 'Test',
            'last_name'     => 'Admin',
            'role'      => 'admin',
            'is_active' => 1,
        ]);

        session()->set('user_id', (int) $this->db->insertID());
    }

    /** Leave no session behind: later tests run as anonymous again. */
    protected function tearDown(): void
    {
        session()->remove('user_id');
        parent::tearDown();
    }

    private function makeCustomer(): int
    {
        $this->db->table('customers')->insert([
            'customer_code' => 'TEST-ADDR-' . uniqid(),
            'name'          => 'Snapshot Fixture Customer',
            'email'         => 'fixture@example.test',
            'phone'         => '111',
            'status'        => 'active',
        ]);

        return (int) $this->db->insertID();
    }

    private function addAddress(int $customerId, string $line1, bool $default = false, bool $billing = false): int
    {
        $this->db->table('customer_addresses')->insert([
            'customer_id' => $customerId,
            'label'       => $line1,
            'line1'       => $line1,
            'city_name'   => 'Testville',
            'is_billing'  => $billing ? 1 : 0,
            'is_shipping' => 0,
            'is_default'  => $default ? 1 : 0,
        ]);

        return (int) $this->db->insertID();
    }

    private function makeQuotation(int $customerId, string $status = 'draft'): int
    {
        helper('customer_snapshot');

        $this->db->table('quotations')->insert([
            'quote_number'      => 'TEST-Q-' . uniqid(),
            'customer_id'       => $customerId,
            'issue_date'        => date('Y-m-d'),
            'status'            => $status,
            'subtotal'          => 100,
            'total'             => 100,
            'customer_snapshot' => customer_snapshot_json($customerId),
            'created_at'        => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function makeOrder(int $customerId, int $quotationId, string $status = 'draft'): int
    {
        helper('customer_snapshot');

        $this->db->table('sales_orders')->insert([
            'order_number'      => 'TEST-SO-' . uniqid(),
            'quotation_id'      => $quotationId,
            'customer_id'       => $customerId,
            'order_date'        => date('Y-m-d'),
            'status'            => $status,
            'subtotal'          => 100,
            'total'             => 100,
            'customer_snapshot' => customer_snapshot_json($customerId),
            'created_at'        => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    /** Drive a controller action with a POST request and decode its JSON. */
    private function post(object $controller, string $method, $arg): array
    {
        $request  = \Config\Services::request();
        $response = \Config\Services::response();
        $logger   = \Config\Services::logger();

        if (method_exists($request, 'withMethod')) {
            $request = $request->withMethod('POST');
        }

        $this->loginAsAdmin();
        $controller->initController($request, $response, $logger);

        $result = $controller->{$method}($arg);

        return json_decode((string) $result->getBody(), true) ?: [];
    }

    private function snapshotOf(string $table, int $id): array
    {
        $row = $this->db->table($table)->select('customer_snapshot')->where('id', $id)->get()->getRowArray();

        return json_decode((string) ($row['customer_snapshot'] ?? ''), true) ?: [];
    }

    // ------------------------------------------------------------ selection

    /**
     * The profile's default address is the one documents use. It used to lose to
     * whichever row happened to have is_billing set, which is the reported bug.
     */
    public function testDefaultAddressWinsOverBillingAddress(): void
    {
        $customerId = $this->makeCustomer();
        $this->addAddress($customerId, 'Old Billing Street', false, true);
        $this->addAddress($customerId, 'New Default Street', true, false);

        helper('customer_snapshot');
        $addr = customer_primary_address($customerId);

        self::assertSame(
            'New Default Street',
            $addr['line1'] ?? '',
            'The customer profile default address was ignored in favour of the billing flag.'
        );
    }

    // ------------------------------------------------------------- freezing

    /** Editing the customer must not rewrite a document that is already issued. */
    public function testChangingTheCustomerDefaultDoesNotRewriteAnIssuedDocument(): void
    {
        $customerId = $this->makeCustomer();
        $this->addAddress($customerId, 'Address At Issue Time', true);
        $quoteId = $this->makeQuotation($customerId);
        $orderId = $this->makeOrder($customerId, $quoteId, 'confirmed');

        // The customer moves after the documents were issued.
        $this->db->table('customer_addresses')->where('customer_id', $customerId)->update(['is_default' => 0]);
        $this->addAddress($customerId, 'Address After The Move', true);

        self::assertSame('Address At Issue Time', $this->snapshotOf('quotations', $quoteId)['line1'] ?? '');
        self::assertSame(
            'Address At Issue Time',
            $this->snapshotOf('sales_orders', $orderId)['line1'] ?? '',
            'A confirmed order picked up the customer\'s new address.'
        );
    }

    // -------------------------------------------------------------- refresh

    /** The legitimate case: a draft quotation can be re-pointed at the new address. */
    public function testDraftQuotationCanRefreshItsAddress(): void
    {
        $customerId = $this->makeCustomer();
        $this->addAddress($customerId, 'Address At Issue Time', true);
        $quoteId = $this->makeQuotation($customerId);

        $this->db->table('customer_addresses')->where('customer_id', $customerId)->update(['is_default' => 0]);
        $this->addAddress($customerId, 'Address After The Move', true);

        $json = $this->post(new Quotations(), 'refreshCustomerAddress', (string) $quoteId);

        self::assertTrue((bool) ($json['success'] ?? false), 'Refresh failed: ' . ($json['error'] ?? ''));
        self::assertSame('Address After The Move', $this->snapshotOf('quotations', $quoteId)['line1'] ?? '');
    }

    /** Once converted, the quotation's address belongs to the order chain. */
    public function testConvertedQuotationCannotRefreshItsAddress(): void
    {
        $customerId = $this->makeCustomer();
        $this->addAddress($customerId, 'Address At Issue Time', true);
        $quoteId = $this->makeQuotation($customerId, 'converted');
        $this->makeOrder($customerId, $quoteId, 'confirmed');

        $json = $this->post(new Quotations(), 'refreshCustomerAddress', (string) $quoteId);

        self::assertFalse((bool) ($json['success'] ?? false), 'A converted quotation accepted an address change.');
        self::assertSame('Address At Issue Time', $this->snapshotOf('quotations', $quoteId)['line1'] ?? '');
    }

    /** A draft order with nothing downstream is still negotiable. */
    public function testDraftOrderCanRefreshItsAddress(): void
    {
        $customerId = $this->makeCustomer();
        $this->addAddress($customerId, 'Address At Issue Time', true);
        $quoteId = $this->makeQuotation($customerId);
        $orderId = $this->makeOrder($customerId, $quoteId, 'draft');

        $this->db->table('customer_addresses')->where('customer_id', $customerId)->update(['is_default' => 0]);
        $this->addAddress($customerId, 'Address After The Move', true);

        $json = $this->post(new SalesOrders(), 'refreshCustomerAddress', (string) $orderId);

        self::assertTrue((bool) ($json['success'] ?? false), 'Refresh failed: ' . ($json['error'] ?? ''));
        self::assertSame('Address After The Move', $this->snapshotOf('sales_orders', $orderId)['line1'] ?? '');
    }

    /** Confirmed orders are out of reach; the user must reset to quotation first. */
    public function testConfirmedOrderCannotRefreshItsAddress(): void
    {
        $customerId = $this->makeCustomer();
        $this->addAddress($customerId, 'Address At Issue Time', true);
        $quoteId = $this->makeQuotation($customerId);
        $orderId = $this->makeOrder($customerId, $quoteId, 'confirmed');

        $json = $this->post(new SalesOrders(), 'refreshCustomerAddress', (string) $orderId);

        self::assertFalse((bool) ($json['success'] ?? false));
        self::assertStringContainsString('reset', strtolower((string) ($json['error'] ?? '')));
        self::assertSame('Address At Issue Time', $this->snapshotOf('sales_orders', $orderId)['line1'] ?? '');
    }

    /** An invoiced order is a posted document, draft status or not. */
    public function testInvoicedOrderCannotRefreshItsAddress(): void
    {
        $customerId = $this->makeCustomer();
        $this->addAddress($customerId, 'Address At Issue Time', true);
        $quoteId = $this->makeQuotation($customerId);
        $orderId = $this->makeOrder($customerId, $quoteId, 'draft');

        $this->db->table('customer_invoices')->insert([
            'invoice_number' => 'TEST-INV-' . uniqid(),
            'customer_id'    => $customerId,
            'sales_order_id' => $orderId,
            'issue_date'     => date('Y-m-d'),
            'status'         => 'draft',
            'total_amount'   => 100,
            'created_at'     => date('Y-m-d H:i:s'),
        ]);

        $json = $this->post(new SalesOrders(), 'refreshCustomerAddress', (string) $orderId);

        self::assertFalse((bool) ($json['success'] ?? false), 'An invoiced order accepted an address change.');
        self::assertSame('Address At Issue Time', $this->snapshotOf('sales_orders', $orderId)['line1'] ?? '');
    }

    /** Both endpoints must stay behind the document edit permissions. */
    public function testRefreshRoutesArePermissionGuarded(): void
    {
        $routes = $this->sourceOf('app/Config/Routes.php');
        self::assertStringContainsString("'Quotations::refreshCustomerAddress/\$1'", $routes);
        self::assertStringContainsString("'SalesOrders::refreshCustomerAddress/\$1'", $routes);

        $permissions = $this->sourceOf('app/Config/RoutePermissions.php');
        self::assertStringContainsString("'POST:quotations/refresh-customer-address/*' => 'quotations.edit'", $permissions);
        self::assertStringContainsString("'POST:sales-orders/refresh-customer-address/*' => 'sales_orders.edit'", $permissions);
    }
}
