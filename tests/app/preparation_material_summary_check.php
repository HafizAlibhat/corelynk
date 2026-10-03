<?php

$controller = file_get_contents(__DIR__ . '/../../app/Controllers/SalesOrders.php');
$view = file_get_contents(__DIR__ . '/../../app/Views/sales_orders/partials/preparation_execution.php');
$reservationService = file_get_contents(__DIR__ . '/../../app/Services/StockReservationService.php');

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (! $condition) {
        $failures[] = $message;
    }
};

$check(str_contains($controller, "'required_materials' => \$requiredMaterials"), 'Preparation payload must expose every required material.');
$check(str_contains($controller, "'variant_id' => \$compVariantId"), 'Material identity must retain its variant.');
$check(str_contains($view, 'foreach ($blockMaterials as $material)'), 'Summary must iterate every material in each production block.');
$check(str_contains($view, "\$materialSummary[\$materialKey]['needed'] +="), 'Repeated materials must aggregate their required quantity.');
$check(str_contains($view, "max(0.0, \$materialRow['needed'] - \$materialRow['available'])"), 'Shortage must be calculated after requirements are aggregated.');
$check(str_contains($view, "'reserved_for_order'"), 'Material payload must show order-specific reserved quantity.');
$check(str_contains($reservationService, "\$row['reserved_orders']"), 'Location rows must identify the sales orders holding stock.');
$check(str_contains($view, 'pe-open-reservation'), 'Preparation rows must expose the explicit Reserve action.');
$check(str_contains($view, "warehouse_name'] ?? '') . ' / ' . (\$location['location_name']"), 'Material rows must show warehouse and location names.');
$check(str_contains($controller, 'StockReservationService'), 'Preparation data must use the location-aware reservation service.');
$check(str_contains($controller, 'vendorOutstandingQty'), 'Preparation logic must account for material already sent to vendors before marking a shortage.');
$check(str_contains($controller, 'vsn.product_id'), 'Vendor shipment lookups must filter by product and sales order before shortage math.');
$check(!str_contains($view, "min(6, count(\$materialSummary))"), 'Material summary must not cap visible items at six and hide the rest.');
$check(!str_contains($view, "is-collapsed d-none"), 'Material summary rows must remain visible instead of being forcibly hidden.');

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Preparation material summary checks passed." . PHP_EOL;
