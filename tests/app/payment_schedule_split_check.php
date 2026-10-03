<?php

/**
 * Runnable check for the milestone money split shared by invoice schedules and
 * the quotation payment-terms preview. No DB: the service is built without its
 * constructor so only the pure maths runs.
 *   php tests/app/payment_schedule_split_check.php
 */

require __DIR__ . '/../../app/Services/InvoicePaymentScheduleService.php';

use App\Services\InvoicePaymentScheduleService;

$svc = (new ReflectionClass(InvoicePaymentScheduleService::class))->newInstanceWithoutConstructor();

$failed = 0;
function check(string $label, $got, $expected): void
{
    global $failed;
    if ($got === $expected) { echo "PASS  {$label}\n"; return; }
    $failed++;
    echo "FAIL  {$label}: got " . json_encode($got) . ", expected " . json_encode($expected) . "\n";
}

$adv80 = [
    ['label' => 'Advance Payment', 'percentage' => 80, 'basis' => 'invoice_date', 'offset_days' => 0],
    ['label' => 'Balance After Delivery', 'percentage' => 20, 'basis' => 'delivery_date', 'offset_days' => 30],
];

check('80/20 of 7866', $svc->splitAmounts($adv80, 7866.00), [6292.80, 1573.20]);
// Last instalment absorbs the rounding remainder, so the split always sums exactly.
$thirds = [
    ['percentage' => 33.33], ['percentage' => 33.33], ['percentage' => 33.34],
];
$split = $svc->splitAmounts($thirds, 100.01);
check('thirds sum to total', round(array_sum($split), 2), 100.01);

// A definition that does not add up to 100% is rejected rather than prorated.
check('bad milestones rejected', $svc->normalizeMilestones([['label' => 'Half', 'percentage' => 50]]), []);
check('valid milestones kept', count($svc->normalizeMilestones($adv80)), 2);

// Quotation preview: no term means no proposed schedule.
$preview = $svc->preview(null, 7866.00);
check('no term, no schedule', $preview['has_schedule'], false);

exit($failed === 0 ? 0 : 1);
