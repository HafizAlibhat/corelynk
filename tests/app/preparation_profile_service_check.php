<?php

/**
 * Round-trip check for the preparation profile form:
 *  - the create/edit view renders with the new vendor-service picker
 *  - a posted step keeps its service product and per-item price
 *
 * Run: php tests/app/preparation_profile_service_check.php
 * All writes happen inside a rolled-back transaction.
 */

$root = 'D:/xampp/htdocs/corelynk/';
define('ENVIRONMENT', 'development');
define('HOMEPATH', $root);
define('CONFIGPATH', $root . 'app/Config/');
define('PUBLICPATH', $root . 'public/');
require CONFIGPATH . 'Paths.php';
$paths = new Config\Paths();
define('APPPATH', realpath(rtrim($paths->appDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
define('ROOTPATH', $root);
define('SYSTEMPATH', realpath(rtrim($paths->systemDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
define('WRITEPATH', realpath(rtrim($paths->writableDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
define('FCPATH', PUBLICPATH);
define('TESTPATH', $root . 'tests/');
define('COMPOSER_PATH', $root . 'vendor/autoload.php');
require SYSTEMPATH . 'Boot.php';
CodeIgniter\Boot::bootTest($paths);

// Start the session before anything is echoed (views touch it) and act as a logged-in user.
$session = \Config\Services::session();
$session->set(['user_id' => 1, 'logged_in' => true, 'isLoggedIn' => true]);

$pass = 0;
$fail = 0;
$check = function (string $label, bool $ok) use (&$pass, &$fail) {
    echo ($ok ? 'PASS  ' : 'FAIL  ') . $label . "\n";
    $ok ? $pass++ : $fail++;
};

$db = \Config\Database::connect();
$db->transBegin();

$product = $db->table('products')->select('id, name')->limit(1)->get()->getRowArray();
$vendor  = $db->table('vendors')->select('id, name')->limit(1)->get()->getRowArray();

$controller = (new ReflectionClass(\App\Controllers\PreparationProfiles::class))->newInstance();
$rp = new ReflectionProperty(\CodeIgniter\Controller::class, 'request');
$rp->setAccessible(true);
$rsp = new ReflectionProperty(\CodeIgniter\Controller::class, 'response');
$rsp->setAccessible(true);
$rsp->setValue($controller, \Config\Services::response());
foreach (['session' => $session, 'currentUser' => ['id' => 1, 'username' => 'check']] as $prop => $value) {
    $rprop = new ReflectionProperty(\App\Controllers\BaseController::class, $prop);
    $rprop->setAccessible(true);
    $rprop->setValue($controller, $value);
}

$post = static function (array $data) use ($rp, $controller) {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = $data;
    $request = \Config\Services::request(null, false);
    \Config\Services::injectMock('request', $request);
    $rp->setValue($controller, $request);
};

// --- 1. inline service creation (what the modal posts) -----------------------
$post([
    'name'      => 'ZZ Gold Coloring Service ' . uniqid(),
    'vendor_id' => $vendor['id'],
    'price'     => '120',
    'currency'  => 'PKR',
]);
$created = json_decode((string) $controller->createServiceAjax()->getBody(), true);
$check('modal creates the service: ' . ($created['message'] ?? 'ok'), ! empty($created['success']));
$serviceValue = (string) ($created['service']['value'] ?? '');
$serviceId = (int) substr($serviceValue, strlen('product:'));
$check('service is linked to the vendor at the agreed price', (int) ($created['service']['vendor_id'] ?? 0) === (int) $vendor['id']
    && abs((float) ($created['service']['price'] ?? 0) - 120) < 0.0001);
$serviceValue = (string) ($created['service']['value'] ?? '');
$check('service comes back as a picker value', $serviceValue === 'product:' . $serviceId);

// --- 2. the form posts the step with that service and its per-item price -----
$post([
    'product_id'              => $product['id'],
    'description'             => 'service round-trip check',
    'material_product_id'     => [0 => 'product:' . $product['id']],
    'material_qty_per_unit'   => [0 => '1'],
    'step_name'               => [0 => 'Gold Coloring'],
    'step_order'              => [0 => '1'],
    'step_description'        => [0 => ''],
    'execution_vendor'        => [0 => '1'],
    'execution_vendor_id'     => [0 => (string) $vendor['id']],
    'execution_default'       => [0 => 'vendor'],
    'execution_notes'         => [0 => ''],
    'step_service_product_id' => [0 => $serviceValue],
    'execution_service_price' => [0 => '135'],
]);

$build = new ReflectionMethod($controller, 'buildPayloadFromPost');
$build->setAccessible(true);
$payload = $build->invoke($controller, null, null);

$check('payload builds', ! empty($payload['ok']));
$step = $payload['steps'][0] ?? [];
$check('step carries service_product_id', (int) ($step['service_product_id'] ?? 0) === $serviceId);
$vendorOption = null;
foreach ($step['options'] ?? [] as $option) {
    if ($option['execution_type'] === 'vendor') {
        $vendorOption = $option;
    }
}
$check('vendor option carries typed price', $vendorOption && abs((float) $vendorOption['service_price'] - 135) < 0.0001);
$check('vendor option carries per_item unit', $vendorOption && $vendorOption['service_unit'] === 'per_item');
$check('vendor option carries currency', $vendorOption && $vendorOption['currency'] === 'PKR');

// price left blank falls back to the vendor's agreed price on the service product
$blank = $_POST;
$blank['execution_service_price'] = [0 => ''];
$post($blank);
$fallback = $build->invoke($controller, null, null);
$fallbackOption = null;
foreach ($fallback['steps'][0]['options'] ?? [] as $option) {
    if ($option['execution_type'] === 'vendor') {
        $fallbackOption = $option;
    }
}
$check('blank price falls back to the service price', $fallbackOption && abs((float) $fallbackOption['service_price'] - 120) < 0.0001);

// --- 2b. a colour service that lives as a variant of a service product -------
$serviceVariantId = (int) (new \App\Models\ProductVariantModel())->insert([
    'product_id'      => $serviceId,
    'name'            => 'ZZ Gold',
    'vendor_id'       => $vendor['id'],
    'vendor_price'    => 145,
    'vendor_currency' => 'PKR',
], true);

$post(array_merge($_POST, ['step_service_product_id' => [0 => 'variant:' . $serviceVariantId], 'execution_service_price' => [0 => '']]));
$variantPayload = $build->invoke($controller, null, null);
$variantStep = $variantPayload['steps'][0] ?? [];
$check('variant service keeps its parent product', (int) ($variantStep['service_product_id'] ?? 0) === $serviceId);
$check('variant service is stored on the step', (int) ($variantStep['service_variant_id'] ?? 0) === $serviceVariantId);
$variantOption = null;
foreach ($variantStep['options'] ?? [] as $option) {
    if ($option['execution_type'] === 'vendor') {
        $variantOption = $option;
    }
}
$check('variant price is used when none is typed', $variantOption && abs((float) $variantOption['service_price'] - 145) < 0.0001);

// --- 3. the values actually reach the tables --------------------------------
$stepModel   = new \App\Models\PreparationStepModel();
$optionModel = new \App\Models\StepExecutionOptionModel();
$profileModel = new \App\Models\PreparationProfileModel();

$profileId = $profileModel->createProfile($payload['profile']);
$stepId = $stepModel->addStep([
    'profile_id'         => $profileId,
    'step_order'         => $step['step_order'],
    'name'               => $step['name'],
    'description'        => $step['description'],
    'is_optional'        => $step['is_optional'],
    'service_product_id' => $step['service_product_id'],
    'service_variant_id' => $step['service_variant_id'],
    'created_at'         => date('Y-m-d H:i:s'),
]);
$vendorOption['step_id'] = $stepId;
$optionId = $optionModel->addOption($vendorOption);

$savedStep = $db->table('preparation_steps')->where('id', $stepId)->get()->getRowArray();
$savedOption = $db->table('step_execution_options')->where('id', $optionId)->get()->getRowArray();
$check('service_product_id stored on the step', (int) $savedStep['service_product_id'] === $serviceId);
$check('service_price stored on the option', abs((float) $savedOption['service_price'] - 135) < 0.0001);

// --- 4. the form renders with the picker -------------------------------------
$services = json_decode((string) $controller->vendorServicesAjax($vendor['id'])->getBody(), true)['services'] ?? [];
// A service product with variants must be offered per variant, not as the bare parent.
$values = array_column($services, 'value');
$check('variant services are listed individually', in_array('variant:' . $serviceVariantId, $values, true));
$check('the endpoint only answers for the vendor asked about', ! array_filter($services, static fn ($x) => (int) $x['vendor_id'] !== (int) $vendor['id']));
// The parent product is itself attached to this vendor (createServiceAjax set that), so it
// must appear too — same list as the vendor's own "Products & Services Offered" page.
$check('the parent product is offered alongside its variant', in_array('product:' . $serviceId, $values, true));
$variantService = null;
foreach ($services as $svc) {
    if ($svc['value'] === 'variant:' . $serviceVariantId) {
        $variantService = $svc;
    }
}
$check('variant service carries its own vendor and price', $variantService
    && (int) $variantService['vendor_id'] === (int) $vendor['id']
    && abs((float) $variantService['price'] - 145) < 0.0001);

$waqar = $db->table('vendors')->select('id')->where('name', 'Waqar Plasma Coating')->get()->getRowArray();
$pvd = $db->table('products')->select('id')->where('name', 'Plasma Coating (PVD)')->get()->getRowArray();
if ($waqar && $pvd) {
    $waqarServices = json_decode((string) $controller->vendorServicesAjax((int) $waqar['id'])->getBody(), true)['services'] ?? [];
    $waqarValues = array_column($waqarServices, 'value');
    $check('Waqar PVD parent service is listed', in_array('product:' . (int) $pvd['id'], $waqarValues, true));

    $bluePlasma = $db->table('product_variants')
        ->select('id')
        ->where('product_id', (int) $pvd['id'])
        ->where('vendor_id', (int) $waqar['id'])
        ->like('name', 'Blue Plasma')
        ->get()
        ->getRowArray();
    if ($bluePlasma) {
        $check('Waqar PVD Blue Plasma variant is listed', in_array('variant:' . (int) $bluePlasma['id'], $waqarValues, true));
    }
}

try {
$html = view('preparation_profiles/form', [
    'page_title'     => 'check',
    'product'        => $product,
    'variant'        => null,
    'profile'        => null,
    'materials'      => [],
    'steps'          => [],
    'step_options'   => [],
    'material_items' => [],
    'vendors'        => [$vendor],
    'services'       => $services,
    'validation'     => \Config\Services::validation(),
]);
} catch (\Throwable $e) { echo 'VIEW ERROR: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "
"; $html = ''; }
$check('form renders the service picker', str_contains($html, 'step_service_product_id[0]'));
$check('form renders the price field', str_contains($html, 'execution_service_price[0]'));
$check('material qty is entered with two decimals', str_contains($html, 'step="0.01" min="0.01" name="material_qty_per_unit'));
$check('form renders the new-service modal', str_contains($html, 'newServiceModal'));
// Select2 emits change only through jQuery, so the vendor binding must exist on that side too.
$check('vendor change is bound through jQuery', str_contains($html, "window.jQuery(document).on('change', 'select.execution-vendor-select'"));
$check('vendor items are seeded into the browser cache', str_contains($html, 'PREP_SERVICE_CACHE['));
$check('other vendors are fetched on demand', str_contains($html, 'preparation-profiles/ajax/vendor-services'));
$check('the step UI groups the vendor fields', str_contains($html, 'vendor_field_0') && str_contains($html, 'service_field_0') && str_contains($html, 'price_field_0'));
$check('select2 changes are wired to the service refresh', str_contains($html, "on('select2:select select2:clear'"));
$check('step template still present for Add Step', str_contains($html, 'step-block-template'));
$check('template placeholder untouched', str_contains($html, 'step_service_product_id[__INDEX__]'));

$db->transRollback();

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
