<?php
/**
 * Self-check for the invoice payment-schedule money path.
 *
 * Run: php tests/app/payment_schedule_check.php
 *
 * Covers milestone validation, the percentage split (including rounding), and
 * the payment waterfall. Pure logic only — no database needed.
 */

require __DIR__ . '/../../app/Services/InvoicePaymentScheduleService.php';

// The class only touches models/DB inside methods this check does not call.
$svc = (new ReflectionClass(\App\Services\InvoicePaymentScheduleService::class))
    ->newInstanceWithoutConstructor();

$failures = 0;
function check(string $name, $expected, $actual): void
{
    global $failures;
    if ($expected == $actual) {
        echo "  ok   $name\n";
        return;
    }
    $failures++;
    echo "  FAIL $name\n";
    echo '       expected: ' . json_encode($expected) . "\n";
    echo '       actual:   ' . json_encode($actual) . "\n";
}

echo "normalizeMilestones\n";

$eighty = [
    ['label' => 'Advance Payment', 'percentage' => 80, 'basis' => 'invoice_date', 'offset_days' => 0],
    ['label' => 'Balance After Delivery', 'percentage' => 20, 'basis' => 'delivery_date', 'offset_days' => 30],
];
check('accepts an 80/20 split', $eighty, $svc->normalizeMilestones($eighty));
check('accepts the same split as JSON', $eighty, $svc->normalizeMilestones(json_encode($eighty)));

check('rejects a split under 100%', [], $svc->normalizeMilestones([
    ['label' => 'Advance', 'percentage' => 80, 'basis' => 'invoice_date', 'offset_days' => 0],
]));
check('rejects a split over 100%', [], $svc->normalizeMilestones([
    ['label' => 'A', 'percentage' => 80, 'basis' => 'invoice_date', 'offset_days' => 0],
    ['label' => 'B', 'percentage' => 30, 'basis' => 'invoice_date', 'offset_days' => 0],
]));
check('rejects empty / garbage input', [], $svc->normalizeMilestones('not json'));

$defaulted = $svc->normalizeMilestones([
    ['percentage' => 100, 'basis' => 'whenever', 'offset_days' => -5],
]);
check('unknown basis falls back to invoice_date', 'invoice_date', $defaulted[0]['basis']);
check('negative offsets are clamped to zero', 0, $defaulted[0]['offset_days']);
check('missing label is generated', 'Instalment 1', $defaulted[0]['label']);

echo "\nsplitAmounts\n";

// The invoice from the reported case: 80% advance on $7,866.00.
$split = $svc->splitAmounts($eighty, 7866.00);
check('80/20 of 7866.00', [6292.80, 1573.20], array_values($split));
check('80/20 sums to the invoice total', 7866.00, round(array_sum($split), 2));

$thirds = $svc->normalizeMilestones([
    ['label' => 'A', 'percentage' => 33.33, 'basis' => 'invoice_date', 'offset_days' => 0],
    ['label' => 'B', 'percentage' => 33.33, 'basis' => 'invoice_date', 'offset_days' => 30],
    ['label' => 'C', 'percentage' => 33.34, 'basis' => 'invoice_date', 'offset_days' => 60],
]);
$oddSplit = $svc->splitAmounts($thirds, 100.01);
check('awkward thirds still sum to the total exactly', 100.01, round(array_sum($oddSplit), 2));
check('last instalment absorbs the remainder', 33.35, $oddSplit[2]);

check('a zero-total invoice splits to zeros', [0.0, 0.0], array_values($svc->splitAmounts($eighty, 0.0)));

echo "\nallocatePayments\n";

$amounts = [6292.80, 1573.20];
check('nothing paid', [0.0, 0.0], array_values($svc->allocatePayments($amounts, 0.0)));
check('advance paid in full', [6292.80, 0.0], array_values($svc->allocatePayments($amounts, 6292.80)));
check('partial advance', [1000.00, 0.0], array_values($svc->allocatePayments($amounts, 1000.00)));
check('advance plus part of the balance', [6292.80, 500.00], array_values($svc->allocatePayments($amounts, 6792.80)));
check('paid in full', [6292.80, 1573.20], array_values($svc->allocatePayments($amounts, 7866.00)));
check('overpayment never exceeds the instalments', [6292.80, 1573.20], array_values($svc->allocatePayments($amounts, 9999.00)));
check('a negative paid total is treated as zero', [0.0, 0.0], array_values($svc->allocatePayments($amounts, -50.00)));

$pending = 7866.00 - array_sum($svc->allocatePayments($amounts, 6292.80));
check('pending after the advance is the 20% balance', 1573.20, round($pending, 2));

echo "\npaymentDates\n";

check('no payments means no dates', [null, null], array_values($svc->paymentDates($amounts, [])));

$advance = [['allocated_amount' => 6292.80, 'payment_date' => '2026-08-01']];
check('advance carries its own date', ['2026-08-01', null], array_values($svc->paymentDates($amounts, $advance)));

$partial = [['allocated_amount' => 1000.00, 'payment_date' => '2026-07-15']];
check('a part payment still dates the first instalment', ['2026-07-15', null], array_values($svc->paymentDates($amounts, $partial)));

$two = [
    ['allocated_amount' => 6292.80, 'payment_date' => '2026-08-01'],
    ['allocated_amount' => 1573.20, 'payment_date' => '2026-09-03'],
];
check('each instalment keeps the date that settled it', ['2026-08-01', '2026-09-03'], array_values($svc->paymentDates($amounts, $two)));

$spanning = [['allocated_amount' => 6792.80, 'payment_date' => '2026-08-10']];
check('one payment spanning both instalments dates both', ['2026-08-10', '2026-08-10'], array_values($svc->paymentDates($amounts, $spanning)));

$dribble = [
    ['allocated_amount' => 3000.00, 'payment_date' => '2026-07-01'],
    ['allocated_amount' => 3292.80, 'payment_date' => '2026-07-20'],
];
check('an instalment reports the payment that completed it', ['2026-07-20', null], array_values($svc->paymentDates($amounts, $dribble)));

$noisy = [
    ['allocated_amount' => 0.0, 'payment_date' => '2026-06-01'],
    ['allocated_amount' => 6292.80, 'payment_date' => null],
    ['allocated_amount' => 6292.80, 'payment_date' => '2026-08-05'],
];
check('zero and undated payments are ignored', ['2026-08-05', null], array_values($svc->paymentDates($amounts, $noisy)));

echo "\n" . ($failures === 0 ? "All checks passed.\n" : "$failures check(s) FAILED.\n");
exit($failures === 0 ? 0 : 1);
