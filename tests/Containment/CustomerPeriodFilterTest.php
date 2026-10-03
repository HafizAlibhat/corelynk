<?php

namespace Tests\Containment;

use App\Controllers\Customers;
use ReflectionMethod;
use Tests\Support\ContainmentTestCase;

/**
 * The customer detail page filters by a year/month picker. Those two selects are
 * only shorthand for a date range, so the translation has to be exact -- a month
 * that ends on the 28th, 29th, 30th or 31st, and junk that degrades safely.
 */
final class CustomerPeriodFilterTest extends ContainmentTestCase
{
    private function call(string $method, array $args)
    {
        $ref = new ReflectionMethod(Customers::class, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs(new Customers(), $args);
    }

    public function testAMonthResolvesToItsOwnFirstAndLastDay(): void
    {
        self::assertSame([2024, 2, '2024-02-01', '2024-02-29'], $this->call('resolvePeriod', ['2024', '2']), 'February in a leap year');
        self::assertSame([2025, 2, '2025-02-01', '2025-02-28'], $this->call('resolvePeriod', ['2025', '2']));
        self::assertSame([2025, 12, '2025-12-01', '2025-12-31'], $this->call('resolvePeriod', ['2025', '12']));
        self::assertSame([2025, 4, '2025-04-01', '2025-04-30'], $this->call('resolvePeriod', ['2025', '04']), 'a zero-padded month still works');
    }

    public function testAYearWithoutAMonthCoversTheWholeYear(): void
    {
        self::assertSame([2025, null, '2025-01-01', '2025-12-31'], $this->call('resolvePeriod', ['2025', '']));
    }

    public function testJunkDegradesInsteadOfFiltering(): void
    {
        self::assertSame([2025, null, '2025-01-01', '2025-12-31'], $this->call('resolvePeriod', ['2025', '13']), 'an impossible month falls back to the year');
        self::assertSame([null, null, null, null], $this->call('resolvePeriod', ['', '5']), 'a month alone means nothing');
        self::assertSame([null, null, null, null], $this->call('resolvePeriod', ['20xx', '']));
    }

    public function testThePeriodIsNamedInPlainEnglish(): void
    {
        self::assertSame('March 2026', $this->call('periodLabel', [2026, 3, null, null]));
        self::assertSame('2026', $this->call('periodLabel', [2026, null, null, null]));
        self::assertSame('01 Mar 2026 – 15 Mar 2026', $this->call('periodLabel', [null, null, '2026-03-01', '2026-03-15']));
        self::assertSame('All time', $this->call('periodLabel', [null, null, null, null]));
    }
}
