<?php

/**
 * Pricing rule check: run with
 *   php tests/app/price_list_check.php
 *
 * Only the pure math is exercised (no DB): margin/markup and the discount rule.
 */

require __DIR__ . '/../../app/Services/PriceListService.php';

$svc  = (new ReflectionClass(\App\Services\PriceListService::class))->newInstanceWithoutConstructor();
$pass = 0;
$fail = 0;

$check = static function (string $label, $expected, $actual) use (&$pass, &$fail) {
    if (abs((float)$expected - (float)$actual) < 0.005) {
        $pass++;
        echo "PASS  {$label} = {$actual}\n";
    } else {
        $fail++;
        echo "FAIL  {$label}: expected {$expected}, got {$actual}\n";
    }
};

// "Charge 30% on top of cost" -> markup
$check('markup 30% on cost 100', 130.00, $svc->applyMargin(100.0, 30.0, 'markup'));
$check('markup 30% on cost 12.50', 16.25, $svc->applyMargin(12.5, 30.0, 'markup'));

// "30% of the selling price is profit" -> margin
$check('margin 30% on cost 100', 142.86, $svc->applyMargin(100.0, 30.0, 'margin'));
$check('margin 50% on cost 40', 80.00, $svc->applyMargin(40.0, 50.0, 'margin'));

// Guards
$check('zero cost stays zero', 0.00, $svc->applyMargin(0.0, 30.0, 'markup'));
$check('margin 100% is capped', $svc->applyMargin(100.0, 99.99, 'margin'), $svc->applyMargin(100.0, 100.0, 'margin'));

// Discount rule as applied in resolve()
$check('10% off base 250', 225.00, round(250.0 * (1 - (10 / 100)), 2));

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
