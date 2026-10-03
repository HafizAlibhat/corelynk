<?php

namespace Tests\Containment;

use App\Helpers\CustomerReceivablesHelper;
use Tests\Support\ContainmentTestCase;

/**
 * A customer can be invoiced in more than one currency. Nothing in the customer
 * detail page may add USD to PKR: every figure has to stay inside its own currency.
 *
 * This test bills the same customer twice -- once in each currency -- and proves the
 * receivables helper reports two separate buckets rather than one mixed number.
 */
final class CustomerCurrencyTest extends ContainmentTestCase
{
    private function makeCustomer(): int
    {
        $this->db->table('customers')->insert([
            'customer_code' => 'TEST-CUR-' . uniqid(),
            'name'          => 'Currency Fixture',
            'type'          => 'company',
            'status'        => 'active',
        ]);

        return (int) $this->db->insertID();
    }

    private function makeInvoice(int $customerId, string $currency, float $total): void
    {
        $this->db->table('customer_invoices')->insert([
            'customer_id'    => $customerId,
            'invoice_number' => 'TEST-INV-' . uniqid(),
            'issue_date'     => date('Y-m-d'),
            'due_date'       => date('Y-m-d'),
            'status'         => 'issued',
            'currency_code'  => $currency,
            'total_amount'   => $total,
        ]);
    }

    public function testUnpaidInvoicesAreGroupedPerCurrencyAndNeverSummedTogether(): void
    {
        if (! in_array('currency_code', $this->db->getFieldNames('customer_invoices'), true)) {
            self::fail('customer_invoices.currency_code is missing: per-currency receivables cannot work.');
        }

        $customerId = $this->makeCustomer();
        $this->makeInvoice($customerId, 'USD', 100.00);
        $this->makeInvoice($customerId, 'USD', 50.00);
        $this->makeInvoice($customerId, 'PKR', 30000.00);

        $result = CustomerReceivablesHelper::getUnpaidInvoices($customerId, $this->db, false);

        self::assertSame(3, $result['count'], 'All three open invoices should be reported.');

        $byCurrency = $result['totals_by_currency'] ?? [];
        self::assertSame(['PKR', 'USD'], self::sortedKeys($byCurrency), 'Both currencies must appear as their own bucket.');

        // Each bucket totals only its own invoices -- 150 USD, not 30150 of anything.
        self::assertSame('150.00', number_format((float) $byCurrency['USD'], 2, '.', ''), 'USD invoices must total on their own.');
        self::assertSame('30000.00', number_format((float) $byCurrency['PKR'], 2, '.', ''), 'PKR invoices must total on their own.');

        foreach ($result['unpaid'] as $row) {
            self::assertTrue(
                in_array($row['currency_code'], ['USD', 'PKR'], true),
                'Every invoice row must carry its own currency, got: ' . var_export($row['currency_code'], true)
            );
        }
    }

    /** @param array<string, mixed> $map */
    private static function sortedKeys(array $map): array
    {
        $keys = array_keys($map);
        sort($keys);

        return $keys;
    }
}
