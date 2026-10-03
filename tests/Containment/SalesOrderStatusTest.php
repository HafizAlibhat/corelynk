<?php

namespace Tests\Containment;

use Tests\Support\ContainmentTestCase;

/**
 * TEST B -- delivery completion sets sales_orders.status to exactly 'delivered'.
 * TEST H -- quotation conversion sets quotations.status to exactly 'converted'.
 * TEST I -- sales order cancellation sets sales_orders.status to exactly 'cancelled'.
 *
 * These tests do not hardcode the expected literal and then check the database can
 * store it -- that would pass even if the controller were changed to write something
 * else. Instead each test EXTRACTS THE LITERAL FROM THE CONTROLLER SOURCE and then
 * proves that exact literal survives a database round trip.
 *
 * That closes the loop that was open in Batch 1: migration 2026-08-28-000001 widened
 * sales_orders.status for 'cancelled' while DeliveryOrders.php:1155 was writing
 * 'delivered' to the same column, and nothing connected the two. This test would
 * have failed immediately.
 */
final class SalesOrderStatusTest extends ContainmentTestCase
{
    /**
     * Pull the status literal out of a controller so the test tracks the real writer.
     */
    private function statusLiteralFrom(string $file, string $anchor, string $context): string
    {
        $src = $this->sourceOf($file);

        $pos = strpos($src, $anchor);
        if ($pos === false) {
            self::fail("Could not find the {$context} write site in {$file}. "
                . "Anchor not present: '{$anchor}'. The test must be re-pointed at the new code.");
        }

        // Look at the statement immediately following the anchor.
        $window = substr($src, $pos, 400);

        if (! preg_match("/'status'\s*=>\s*'([a-z_]+)'/i", $window, $m)) {
            self::fail("Found the {$context} anchor in {$file} but no status literal near it.");
        }

        return $m[1];
    }

    // ---------------------------------------------------------------- TEST B

    public function testDeliveryCompletionWritesDeliveredAndItPersists(): void
    {
        $literal = $this->statusLiteralFrom(
            'app/Controllers/DeliveryOrders.php',
            "table('sales_orders')->where('id', \$soId)->update(",
            'delivery completion'
        );

        self::assertSame(
            'delivered',
            $literal,
            'DeliveryOrders no longer writes the expected lifecycle value on completion.'
        );

        $stored = $this->roundTrip('sales_orders', 'status', $literal, [
            'order_number' => 'TEST-S-DELIVERED',
            'customer_id'  => 1,
        ]);

        self::assertSame(
            'delivered',
            $stored,
            'P0-1: delivery completion silently blanked sales_orders.status.'
        );
        self::assertNotSame('', $stored, 'Sales order status was truncated to the empty string.');
    }

    /**
     * The reported user-visible symptom: a blanked status drops the order out of the
     * default document list, which is what made these orders look deleted.
     */
    public function testDeliveredSalesOrderRemainsVisibleAndSearchable(): void
    {
        $this->db->table('sales_orders')->insert([
            'order_number' => 'TEST-S-VISIBLE',
            'customer_id'  => 1,
            'status'       => 'delivered',
        ]);
        $id = (int) $this->db->insertID();

        // Visible: survives the "known status" filter the document list applies.
        $visible = $this->db->table('sales_orders')
            ->where('id', $id)
            ->where("status <> ''", null, false)
            ->countAllResults();
        self::assertSame(1, $visible, 'Delivered sales order dropped out of the status-filtered list.');

        // Searchable: findable by its document number.
        $found = $this->db->table('sales_orders')
            ->where('order_number', 'TEST-S-VISIBLE')
            ->get()->getRowArray();
        self::assertNotNull($found, 'Delivered sales order was not findable by order_number.');
        self::assertSame('delivered', $found['status']);
    }

    /**
     * Completion is not guarded by a state machine, so it can be replayed. Replaying it
     * must be idempotent rather than corrupting.
     */
    public function testRepeatedCompletionCannotCorruptStatus(): void
    {
        $this->db->table('sales_orders')->insert([
            'order_number' => 'TEST-S-REPEAT',
            'customer_id'  => 1,
            'status'       => 'confirmed',
        ]);
        $id = (int) $this->db->insertID();

        for ($i = 0; $i < 3; $i++) {
            $this->db->table('sales_orders')->where('id', $id)->update([
                'status'     => 'delivered',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $row = $this->db->table('sales_orders')->where('id', $id)->get()->getRowArray();
            self::assertSame(
                'delivered',
                $row['status'],
                'Status corrupted on completion attempt ' . ($i + 1) . '.'
            );
        }
    }

    // ---------------------------------------------------------------- TEST I

    public function testCancellationWritesCancelledAndItPersists(): void
    {
        $src = $this->sourceOf('app/Controllers/SalesOrders.php');
        self::assertStringContainsString(
            "\$upd['status'] = 'cancelled'",
            $src,
            'SalesOrders::cancel no longer writes the expected lifecycle value.'
        );

        $stored = $this->roundTrip('sales_orders', 'status', 'cancelled', [
            'order_number' => 'TEST-S-CANCELLED',
            'customer_id'  => 1,
        ]);

        self::assertSame('cancelled', $stored, 'Sales order cancellation silently blanked the status.');
    }

    // ---------------------------------------------------------------- TEST H

    public function testQuotationConversionWritesConvertedAndItPersists(): void
    {
        $src = $this->sourceOf('app/Controllers/SalesOrders.php');

        // The real write site (:1748) builds an update array conditionally:
        //     if (in_array('status', $qCols)) $upd['status'] = 'converted';
        self::assertStringContainsString(
            "\$upd['status'] = 'converted'",
            $src,
            "SalesOrders no longer writes 'converted' on quotation conversion. "
            . "Before Batch 1 it wrote 'confirmed', which is not a quotation status and blanked 36 quotations."
        );

        // Scoped to the conversion site only: 'confirmed' is a legitimate sales_orders
        // status elsewhere in this controller, it is only wrong against `quotations`.
        $pos = strpos($src, "\$upd['status'] = 'converted'");
        $window = substr($src, max(0, $pos - 1500), 2000);
        self::assertStringNotContainsString(
            "'confirmed'",
            (string) $window,
            "The quotation conversion site writes 'confirmed' again; that value is not a "
            . 'quotations.status member and blanked 36 quotations.'
        );

        $stored = $this->roundTrip('quotations', 'status', 'converted', [
            'quote_number' => 'TEST-Q-CONVERTED',
            'customer_id'  => 1,
        ]);

        self::assertSame('converted', $stored, 'Quotation conversion silently blanked the status.');
    }

    /**
     * The Batch 1 companion defect: 'converted' must remain in the update whitelist,
     * otherwise re-saving a converted quotation demotes it to draft.
     */
    public function testConvertedIsAcceptedByTheQuotationUpdateWhitelist(): void
    {
        $src = $this->sourceOf('app/Controllers/Quotations.php');
        self::assertStringContainsString(
            'converted',
            $src,
            "Quotations no longer whitelists 'converted'; saving a converted quotation would demote it."
        );
    }
}
