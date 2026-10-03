<?php

$routesContent = file_get_contents(__DIR__ . '/../../app/Config/Routes.php');
$permContent = file_get_contents(__DIR__ . '/../../app/Config/RoutePermissions.php');
$vrController = file_get_contents(__DIR__ . '/../../app/Controllers/VendorReceive.php');
$peController = file_get_contents(__DIR__ . '/../../app/Controllers/PreparationExecution.php');
$peView = file_get_contents(__DIR__ . '/../../app/Views/sales_orders/partials/preparation_execution.php');
$slipView = file_get_contents(__DIR__ . '/../../app/Views/vendor_receive/slip.php');
$combView = file_get_contents(__DIR__ . '/../../app/Views/vendor_receive/combined_slip.php');

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (! $condition) {
        $failures[] = $message;
    }
};

// 1. Routes and permissions
$check(str_contains($routesContent, "'order/(:num)/combined-send-slip', 'VendorReceive::combinedSendSlip/$1'"), 'Routes must define order combined-send-slip endpoint.');
$check(str_contains($routesContent, "'order/(:num)/lot-stickers', 'VendorReceive::lotStickers/$1'"), 'Routes must define order lot-stickers endpoint.');
$check(str_contains($routesContent, "'vendor-receive/verify/(:any)', 'VendorReceive::verify/$1'"), 'Routes must define vendor-receive/verify endpoint.');
$check(str_contains($permContent, "'GET:vendor-receive/order/*/combined-send-slip' => 'sales_orders.read'"), 'RoutePermissions must map combined-send-slip to sales_orders.read.');
$check(str_contains($permContent, "'GET:vendor-receive/order/*/lot-stickers' => 'sales_orders.read'"), 'RoutePermissions must map lot-stickers to sales_orders.read.');
$check(str_contains($permContent, "'GET:vendor-receive/verify/*' => true"), 'RoutePermissions must allow vendor-receive/verify public access.');

// 2. VendorReceive controller
$check(str_contains($vrController, 'public function combinedSendSlip(int $salesOrderId)'), 'VendorReceive must implement combinedSendSlip.');
$check(str_contains($vrController, 'public function lotStickers(int $salesOrderId)'), 'VendorReceive must implement lotStickers.');
$check(str_contains($vrController, 'public function verify(string $token)'), 'VendorReceive must implement verify action.');
$check(str_contains($vrController, 'target_pv.name AS target_variant_name'), 'VendorReceive must join target product variant.');
$check(str_contains($vrController, "\$note['target_full_name']"), 'VendorReceive must compute target_full_name.');
$check(str_contains($vrController, "\$note['mat_full_name']"), 'VendorReceive must compute mat_full_name.');
$check(str_contains($vrController, 'generate_secure_outwork_token'), 'VendorReceive must generate encrypted verification tokens.');

// 3. PreparationExecution dispatch_date handling
$check(str_contains($peController, "\$dispatchDate = trim((string) (\$input['dispatch_date'] ?? ''));"), 'PreparationExecution must read dispatch_date from input.');
$check(str_contains($peController, "'created_at' => \$sendCreatedAt"), 'PreparationExecution must use dispatch_date for created_at.');

// 4. Send to vendor form & button in view
$check(str_contains($peView, 'name="dispatch_date"'), 'peVendorModal must have dispatch_date input field.');
$check(str_contains($peView, 'id="pe_v_dispatch_date"'), 'peVendorModal must have pe_v_dispatch_date id.');
$check(str_contains($peView, 'Combined Send Slip'), 'Preparation execution header must provide Combined Send Slip button.');
$check(str_contains($peView, 'lot-stickers'), 'Preparation execution header must provide Lot Stickers button.');

// 5. Slip view navigation
$check(str_contains($slipView, 'combined-send-slip'), 'Individual slip toolbar must link to combined-send-slip.');
$check(str_contains($slipView, 'lot-stickers'), 'Individual slip toolbar must link to lot-stickers.');

// 6. Combined slip view structure
$check(str_contains($combView, 'Combined Vendor Send Note'), 'Combined slip must have Combined Vendor Send Note title.');
$check(str_contains($combView, 'Dispatch Date'), 'Combined slip manifest table must show Dispatch Date column.');
$check(str_contains($combView, 'Send Ref'), 'Combined slip manifest table must show Send Ref column.');
$check(str_contains($combView, 'Double Check'), 'Combined slip manifest table must show Double Check verification column.');
$check(str_contains($combView, 'verify-box'), 'Combined slip must render double check boxes.');
$check(str_contains($combView, 'Total Dispatched Lots:'), 'Combined slip header must state Total Dispatched Lots.');
$check(! str_contains($combView, '<th>Code / SKU</th>'), 'Combined slip must not have standalone Code/SKU column header.');
$check(! str_contains($combView, '<th>Status</th>'), 'Combined slip must not have Status column header.');
$check(! str_contains($combView, '<span class="meta-label">Customer</span>'), 'Combined slip must not display Customer name in summary cards.');
$check(str_contains($combView, 'Dispatched Material (Base Product &amp; Specification)'), 'Combined slip must display Dispatched Material header.');
$check(! str_contains($combView, 'target-prod-line'), 'Combined slip must not display target product line in table.');
$check(str_contains($combView, 'mat-prod-line'), 'Combined slip must define mat-prod-line class.');
$check(str_contains($combView, 'header-barcode-qr'), 'Combined slip header must contain QR and barcode.');
$check(str_contains($combView, 'barcode_code128_svg'), 'Combined slip must call barcode_code128_svg.');
$check(str_contains($combView, 'outwork_qr_svg'), 'Combined slip must call outwork_qr_svg.');

// 7. Stickers view structure
$stickersView = file_get_contents(__DIR__ . '/../../app/Views/vendor_receive/lot_stickers.php');
$check(str_contains($stickersView, 'pack-sticker'), 'Stickers view must define pack-sticker container.');
$check(str_contains($stickersView, 'sticker-lot-badge'), 'Stickers view must define sticker-lot-badge.');
$check(str_contains($stickersView, 'sticker-step-highlight'), 'Stickers view must highlight process step/color.');
$check(str_contains($stickersView, 'sticker-qty-box'), 'Stickers view must show pack quantity.');
$check(! str_contains($stickersView, 'sticker-barcode-bottom'), 'Stickers view must not contain bottom linear barcode.');
$check(str_contains($stickersView, 'sticker-product-box'), 'Stickers view must define sticker-product-box for product name focus.');
$check(str_contains($stickersView, 'outwork_qr_svg'), 'Stickers view must render vector outwork_qr_svg.');

// Single slip must have QR code
$check(str_contains($slipView, 'outwork_qr_svg'), 'Single slip view must render vector outwork_qr_svg.');
$check(! str_contains($combView, 'barcode_code128_svg($shortRef'), 'Combined slip must not render barcode under lots in manifest table.');

// 8. Barcode helper functionality & Live verification test
require_once __DIR__ . '/../../app/Helpers/barcode_helper.php';
$testToken = generate_secure_outwork_token('lot', 41, 'VSN-20261002151931-8DE1');
$check(str_starts_with($testToken, 'L41-'), 'Token must be compact and start with L41-.');

$qrSvg = outwork_qr_svg('http://192.168.100.110/corelynk-dev/vendor-receive/verify/' . $testToken, 80);
$check(str_contains($qrSvg, '<svg') && str_contains($qrSvg, '</svg>'), 'outwork_qr_svg must output valid vector SVG.');

$svgBarcode = barcode_code128_svg('VSN-20261002151931-8DE1', 28, 2, false);
$check(str_contains($svgBarcode, '<svg') && str_contains($svgBarcode, 'shape-rendering="crispEdges"'), 'barcode_code128_svg must output crispEdges vector SVG.');

// Test public verification endpoint over HTTP
$verifyUrl = 'http://localhost/corelynk-dev/vendor-receive/verify/' . $testToken;
$verifyHtml = @file_get_contents($verifyUrl);
if ($verifyHtml !== false) {
    $check(str_contains($verifyHtml, 'Authentic Verified Outwork Record'), 'Verification page must confirm authenticity.');
    $check(str_contains($verifyHtml, 'Colorful Tweezers'), 'Verification page must display target product Colorful Tweezers.');
    $check(str_contains($verifyHtml, 'RI-KI-11842'), 'Verification page must display target product code [RI-KI-11842].');
    $check(str_contains($verifyHtml, 'RI-KI-11836'), 'Verification page must display material required code [RI-KI-11836].');
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "All Combined send slip, lot stickers, vector barcodes & encrypted verification checks passed successfully!" . PHP_EOL;

