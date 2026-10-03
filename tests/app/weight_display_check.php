<?php

/**
 * Runnable check for shipment weight conversion + display.
 *   php tests/app/weight_display_check.php
 */

require __DIR__ . '/../../app/Helpers/WeightHelper.php';

use App\Helpers\WeightHelper;

$failed = 0;
function check(string $label, $got, $expected): void
{
    global $failed;
    if ($got === $expected) {
        echo "PASS  {$label}\n";
        return;
    }
    $failed++;
    echo "FAIL  {$label}: got " . var_export($got, true) . ", expected " . var_export($expected, true) . "\n";
}

// Conversion: the product unit decides the stored value, kg is the common base.
check('30 g -> kg', WeightHelper::toKilograms(30, 'g'), 0.03);
check('100 g -> kg', WeightHelper::toKilograms(100, 'G'), 0.1);
check('2 kg -> kg', WeightHelper::toKilograms(2, 'KG'), 2.0);

// Display: >= 1 kg reads in kg, lighter reads in grams. Never raw grams like "90027000 g".
check('spoon 30 g x 900', WeightHelper::formatShipment(WeightHelper::toKilograms(30, 'g') * 900), '27 kg');
check('box 100 g x 900', WeightHelper::formatShipment(WeightHelper::toKilograms(100, 'g') * 900), '90 kg');
check('both lines', WeightHelper::formatShipment(0.03 * 900 + 0.1 * 900), '117 kg');
check('single spoon', WeightHelper::formatShipment(WeightHelper::toKilograms(30, 'g')), '30 g');
check('under 1 kg', WeightHelper::formatShipment(0.999), '999 g');
check('exactly 1 kg', WeightHelper::formatShipment(1.0), '1 kg');
check('fractional kg', WeightHelper::formatShipment(1.25), '1.25 kg');
check('unit arg ignored', WeightHelper::formatShipment(1.5, 'g'), '1.5 kg');
check('zero', WeightHelper::formatShipment(0), '0 g');

exit($failed === 0 ? 0 : 1);
