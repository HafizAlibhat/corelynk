<?php

/** Explicit reservation integration check. All writes are rolled back. */
$root = realpath(__DIR__ . '/../..') . DIRECTORY_SEPARATOR;
define('ENVIRONMENT', 'development');
define('HOMEPATH', $root);
define('CONFIGPATH', $root . 'app' . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR);
define('PUBLICPATH', $root . 'public' . DIRECTORY_SEPARATOR);
require CONFIGPATH . 'Paths.php';
$paths = new Config\Paths();
define('APPPATH', realpath(rtrim($paths->appDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
define('ROOTPATH', $root);
define('SYSTEMPATH', realpath(rtrim($paths->systemDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
define('WRITEPATH', realpath(rtrim($paths->writableDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
define('FCPATH', PUBLICPATH);
define('TESTPATH', $root . 'tests' . DIRECTORY_SEPARATOR);
define('COMPOSER_PATH', $root . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php');
require SYSTEMPATH . 'Boot.php';
CodeIgniter\Boot::bootTest($paths);

$db = Config\Database::connect();
$reservations = new App\Services\StockReservationService($db);
$availability = new App\Services\InventoryAvailabilityService();
$pass = 0;
$fail = 0;
$check = static function (string $label, $expected, $actual) use (&$pass, &$fail): void {
    $ok = is_bool($expected) ? $expected === $actual : abs((float) $expected - (float) $actual) < 0.005;
    if ($ok) {
        $pass++;
        echo "PASS  {$label}\n";
    } else {
        $fail++;
        echo "FAIL  {$label}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
    }
};

$db->transBegin();
try {
    $baseline = $availability->getAvailability(9, 2834);
    $baselineOwn = $availability->getAvailability(9, 2834, '', ['exclude_sales_order_line_id' => 19]);
    $baselineLocations = $reservations->locationAvailability(9, 2834, 19);
    $baselineBox = array_values(array_filter($baselineLocations, static fn ($row) => (int) $row['location_id'] === 34));

    // RI-S0001 lines 19 and 20 both consume Silver Tweezers variant 2834 from Box-6.
    $first = $reservations->reserve(2, 19, 9, 2834, 34, 30, 1);
    $check('first order line reserves', true, $first['ok']);

    $global = $availability->getAvailability(9, 2834);
    $check('on hand remains physical', $baseline['on_hand'], $global['on_hand']);
    $check('explicit reserved quantity increases', (float) $baseline['reserved'] + 30, $global['reserved']);
    $check('free quantity is reduced', (float) $baseline['available'] - 30, $global['available']);

    $own = $availability->getAvailability(9, 2834, '', ['exclude_sales_order_line_id' => 19]);
    $check('own reservation remains available for execution', $baselineOwn['available'], $own['available']);

    $safetyExtra = $reservations->reserve(2, 19, 9, 2834, 34, 2, 1);
    $check('line can reserve a safety overage', true, $safetyExtra['ok']);

    $wrongOrder = $reservations->reserve(999999, 19, 9, 2834, 34, 1, 1);
    $check('line ownership is enforced', false, $wrongOrder['ok']);

    $afterExtra = $availability->getAvailability(9, 2834);
    $check('requirement and safety overage aggregate', (float) $baseline['reserved'] + 32, $afterExtra['reserved']);
    $check('free stock after safety overage', (float) $baseline['available'] - 32, $afterExtra['available']);

    $locations = $reservations->locationAvailability(9, 2834, 19);
    $box = array_values(array_filter($locations, static fn ($row) => (int) $row['location_id'] === 34));
    $check('location carries current-line hold', (float) ($baselineBox[0]['reserved_for_order'] ?? 0) + 32, $box[0]['reserved_for_order'] ?? -1);
    $check('current order can use its own hold', $baselineBox[0]['available_for_order'] ?? -1, $box[0]['available_for_order'] ?? -2);

    $reservations->consumeForExecution(19, 9, 2834, 34, 10);
    $afterConsume = $availability->getAvailability(9, 2834);
    $check('partial execution consumes hold', (float) $baseline['reserved'] + 22, $afterConsume['reserved']);

    $extraReservation = $db->table('stock_reservations')->select('id')
        ->where('sales_order_line_id', 19)->where('reserved_quantity', 2)->where('status', 'active')->orderBy('id', 'DESC')->get(1)->getRowArray();
    $released = $reservations->release((int) ($extraReservation['id'] ?? 0), 2, 1);
    $check('active hold can be released', true, $released['ok']);
    $afterRelease = $availability->getAvailability(9, 2834);
    $check('release restores free stock', (float) $baseline['reserved'] + 20, $afterRelease['reserved']);
    $releasedTwice = $reservations->release((int) ($extraReservation['id'] ?? 0), 2, 1);
    $check('release is idempotently rejected', false, $releasedTwice['ok']);
} catch (Throwable $e) {
    $fail++;
    echo 'FAIL  exception: ' . $e->getMessage() . "\n";
} finally {
    $db->transRollback();
}

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);

