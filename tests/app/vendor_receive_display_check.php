<?php

$controller = file_get_contents(__DIR__ . '/../../app/Controllers/VendorReceive.php');
$indexView = file_get_contents(__DIR__ . '/../../app/Views/vendor_receive/index.php');
$formView = file_get_contents(__DIR__ . '/../../app/Views/vendor_receive/form.php');

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (! $condition) {
        $failures[] = $message;
    }
};

$check(str_contains($controller, 'sales_order_lines sol'), 'VendorReceive::index() must join sales_order_lines.');
$check(str_contains($controller, 'product_variants so_pv'), 'VendorReceive::index() must join product_variants for sales order line.');
$check(str_contains($controller, "'resolved_variant_name'"), 'VendorReceive::index() must compute resolved_variant_name.');
$check(str_contains($controller, "'resolved_code'"), 'VendorReceive::index() must compute resolved_code.');
$check(!str_contains($indexView, "(' . (\$note['product_code'] ?? '-') . ')"), 'VendorReceive index view must not print raw (-) parentheses.');
$check(str_contains($indexView, "\$note['resolved_variant_name']"), 'VendorReceive index view must display resolved variant name.');
$check(str_contains($indexView, "\$note['resolved_code']"), 'VendorReceive index view must display resolved code.');
$check(str_contains($indexView, "number_format((float) (\$note['qty'] ?? 0), 2)"), 'Sent Qty must be formatted to 2 decimals.');
$check(str_contains($indexView, "number_format((float) (\$note['remaining_qty'] ?? 0), 2)"), 'Remaining Qty must be formatted to 2 decimals.');
$check(!str_contains($indexView, 'sent_item_summary'), 'Duplicate sent item line must not be rendered in the index table.');
$check(!str_contains($formView, "<?= esc(\$send_note['product_code'] ?? '-') ?>"), 'VendorReceive form view must not fall back to standalone dash for code.');
$check(str_contains($controller, 'tl.name AS to_location_name'), 'VendorReceive controller must select to_location_name.');
$check(str_contains($indexView, "\$note['to_location_name']"), 'VendorReceive index view must display vendor location.');
$check(str_contains($formView, "\$send_note['to_location_name']"), 'VendorReceive form view must display vendor location.');

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Vendor receive display checks passed successfully." . PHP_EOL;
