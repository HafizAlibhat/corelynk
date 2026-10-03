<?php

namespace Tests\Containment;

use Tests\Support\ContainmentTestCase;

/**
 * TEST A -- database domain compatibility for every critical business status/type writer.
 *
 * This is the single most important test in the suite. Every confirmed data-corruption
 * defect in this system so far belongs to ONE family: the application writes a value the
 * column's ENUM does not contain, Config\Database::$strictOn = false means CI4 strips
 * STRICT_TRANS_TABLES from the session, and MySQL stores '' while reporting success.
 *
 * The six known instances, all of which this test reproduces and would have caught on
 * the day each was written:
 *
 *   1. quotation blank status        SalesOrders::createFromQuotation wrote 'confirmed'
 *   2. Sales Order blank status      SalesOrders::cancel wrote 'cancelled'
 *   3. API quotation blank status    Quotations::apiCreate defaulted to 'quoted'
 *   4. journal blank source_type     five vendor/expense source types were never members
 *   5. Delivery blank SO status      DeliveryOrders.php:1155 wrote 'delivered'   (P0-1)
 *   6. vendor bill blank based_on    'po_over_receipt' / 'po_qty_adjustment'     (P0-3)
 *
 * Note what this test does NOT rely on: it never expects MySQL to raise an error,
 * because in the configuration this application actually runs, it does not. It writes
 * the value, reads it back, and compares. That is the only detection method that works
 * while strictOn is false.
 */
final class DomainValueTest extends ContainmentTestCase
{
    /**
     * Every value the application code is capable of writing to each column.
     *
     * Sourced by tracing the writers, not by copying the schema -- copying the schema
     * would make the test tautological.
     *
     * @return array<string, array{table:string, column:string, values:string[], extra:array, writers:array<string,string>}>
     */
    public static function criticalDomains(): array
    {
        return [
            'quotations.status' => [
                'table'   => 'quotations',
                'column'  => 'status',
                'extra'   => ['quote_number' => 'TEST-Q-DOMAIN', 'customer_id' => 1],
                'values'  => ['draft', 'sent', 'accepted', 'rejected', 'converted'],
                'writers' => [
                    'converted' => 'SalesOrders::createFromQuotation (:1748)',
                    'draft'     => 'Quotations::apiCreate (:349-355)',
                ],
            ],

            'sales_orders.status' => [
                'table'   => 'sales_orders',
                'column'  => 'status',
                'extra'   => ['order_number' => 'TEST-S-DOMAIN', 'customer_id' => 1],
                'values'  => ['draft', 'confirmed', 'shipped', 'delivered', 'closed', 'cancelled'],
                'writers' => [
                    'delivered' => 'DeliveryOrders::complete (:1155)  <-- P0-1',
                    'shipped'   => 'DeliveryOrderService (:423)',
                    'cancelled' => 'SalesOrders::cancel (:2170)',
                ],
            ],

            'journal_entries.source_type' => [
                'table'   => 'journal_entries',
                'column'  => 'source_type',
                'extra'   => ['entry_date' => '2026-01-01'],
                'values'  => [
                    'invoice', 'payment', 'cheque', 'credit_note', 'manual',
                    'vendor_bill', 'vendor_payment', 'vendor_advance_application',
                    'vendor_bill_advance_application', 'mobile_expense',
                ],
                'writers' => [
                    'vendor_bill'    => 'vendor bill posting',
                    'mobile_expense' => 'Api\ExpenseApi (:140)',
                ],
            ],

            'vendor_bills.based_on' => [
                'table'   => 'vendor_bills',
                'column'  => 'based_on',
                'extra'   => ['vendor_id' => 1],
                'values'  => ['po_qty', 'grn_qty', 'manual', 'po_over_receipt', 'po_qty_adjustment'],
                'writers' => [
                    'po_over_receipt'   => 'NewPurchaseGrns (:1710), NewPurchaseOrders (:340)  <-- P0-3',
                    'po_qty_adjustment' => 'NewPurchaseOrders (:2462)                          <-- P0-3',
                    'po_qty'            => 'DeliveryOrders (:1003)',
                ],
            ],
        ];
    }

    /**
     * Every value the code can write must survive a write/read round trip byte for byte.
     */
    public function testEveryCodeWrittenDomainValueSurvivesRoundTrip(): void
    {
        $failures = [];

        foreach (self::criticalDomains() as $label => $spec) {
            foreach ($spec['values'] as $value) {
                $stored = $this->roundTrip($spec['table'], $spec['column'], $value, $spec['extra']);

                if ($stored !== $value) {
                    $writer     = $spec['writers'][$value] ?? 'see Phase 2 trace';
                    $failures[] = sprintf(
                        "%s: wrote '%s' but stored '%s' (writer: %s)",
                        $label,
                        $value,
                        $stored,
                        $writer
                    );
                }
            }
        }

        self::assertSame(
            [],
            $failures,
            "Silent domain truncation detected. The write reported success and stored something else:\n  "
            . implode("\n  ", $failures)
        );
    }

    /**
     * Guard against the reverse mistake: an ENUM so wide it no longer constrains anything.
     * A value the code never writes must NOT silently become storable as a typo sink.
     */
    public function testColumnsStillRejectAnUnknownValue(): void
    {
        foreach (self::criticalDomains() as $label => $spec) {
            $stored = $this->roundTrip(
                $spec['table'],
                $spec['column'],
                'not_a_real_domain_value',
                $spec['extra']
            );

            self::assertNotSame(
                'not_a_real_domain_value',
                $stored,
                "{$label} accepted an arbitrary value; the column is no longer constrained."
            );
        }
    }

    /**
     * The declared domain and the live schema must agree, so that widening a column
     * without updating this test (or vice versa) is caught.
     */
    public function testDeclaredDomainsMatchLiveSchema(): void
    {
        foreach (self::criticalDomains() as $label => $spec) {
            $members = $this->enumMembers($spec['table'], $spec['column']);
            self::assertNotSame([], $members, "{$label} is not an ENUM in the live schema.");

            foreach ($spec['values'] as $value) {
                self::assertTrue(
                    in_array($value, $members, true),
                    "{$label}: the code writes '{$value}' but the live ENUM does not contain it. "
                    . 'Members: ' . implode(', ', $members)
                );
            }
        }
    }

    /**
     * The two P0 values fixed in Batch 2A, asserted explicitly so a rollback of either
     * migration fails loudly and by name rather than as a generic domain failure.
     */
    public function testBatch2aP0ValuesArePresent(): void
    {
        self::assertTrue(
            in_array('delivered', $this->enumMembers('sales_orders', 'status'), true),
            "P0-1 regression: sales_orders.status lost 'delivered'. "
            . 'DeliveryOrders.php:1155 will silently blank the status of every completed delivery.'
        );

        $basedOn = $this->enumMembers('vendor_bills', 'based_on');
        self::assertTrue(
            in_array('po_over_receipt', $basedOn, true),
            "P0-3 regression: vendor_bills.based_on lost 'po_over_receipt'. "
            . 'PO billed-quantity arithmetic will be wrong and the duplicate-bill guard will be dead.'
        );
        self::assertTrue(
            in_array('po_qty_adjustment', $basedOn, true),
            "P0-3 regression: vendor_bills.based_on lost 'po_qty_adjustment'."
        );
    }
}
