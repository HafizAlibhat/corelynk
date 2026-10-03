<?php

namespace Tests\Containment;

use App\Controllers\Corelynk;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Which currency the dashboard's Annual Revenue Matrix opens on.
 *
 * It used to open on the default SALES currency. On books kept in PKR that
 * meant the chart showed a USD series holding revenue and no expenses at all,
 * so a posted expense journal looked like it had never been recorded. The
 * chart reads the ledger, so it must open on the ledger's own currency.
 *
 * Pure array logic — no database, so this extends TestCase directly.
 */
final class DashboardChartCurrencyTest extends TestCase
{
    private function pick(array $series, string $base): string
    {
        $ref = new ReflectionMethod(Corelynk::class, 'pickChartCurrency');
        $ref->setAccessible(true);

        return $ref->invokeArgs(new Corelynk(), [$series, $base]);
    }

    /** @return array{revenue: list<float>, expenses: list<float>} */
    private function series(float $revenue, float $expenses): array
    {
        return ['revenue' => [0.0, $revenue], 'expenses' => [0.0, $expenses]];
    }

    public function testOpensOnTheBaseCurrencyWhereTheExpensesAre(): void
    {
        $series = [
            'PKR' => $this->series(0.0, 17000.0),  // the books
            'USD' => $this->series(145.0, 0.0),    // sales only
        ];

        self::assertSame('PKR', $this->pick($series, 'PKR'), 'the ledger chart follows the ledger');
    }

    public function testBaseCurrencyWinsEvenWhenListedAfterAnother(): void
    {
        $series = [
            'USD' => $this->series(145.0, 0.0),
            'PKR' => $this->series(0.0, 17000.0),
        ];

        self::assertSame('PKR', $this->pick($series, 'PKR'), 'order in the map must not decide it');
    }

    public function testFallsBackToACurrencyThatHasPostings(): void
    {
        $series = [
            'PKR' => $this->series(0.0, 0.0),      // nothing posted yet
            'USD' => $this->series(145.0, 0.0),
        ];

        self::assertSame('USD', $this->pick($series, 'PKR'), 'an empty base currency would show an empty chart');
    }

    public function testRevenueOnlyPostingsStillCount(): void
    {
        $series = ['PKR' => $this->series(500.0, 0.0), 'USD' => $this->series(145.0, 0.0)];

        self::assertSame('PKR', $this->pick($series, 'PKR'));
    }

    public function testAnEmptyLedgerKeepsTheBaseCurrency(): void
    {
        $series = ['PKR' => $this->series(0.0, 0.0), 'USD' => $this->series(0.0, 0.0)];

        self::assertSame('PKR', $this->pick($series, 'PKR'), 'a blank dashboard still reads in the books currency');
    }
}
