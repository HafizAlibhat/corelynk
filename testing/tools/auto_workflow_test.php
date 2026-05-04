<?php
// Automated authenticated test: login -> get processes -> create batch -> add log -> release
// Configure credentials here (use a test account present in the DB)
$email = 'admin@production.local';
$password = 'Admin12345!';
$base = 'http://localhost/pro_sys/public/index.php';
$cookieFile = sys_get_temp_dir() . '/prosys_test_cookie.txt';

function curl_post($url, $postFields = [], $cookieFile = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Requested-With: XMLHttpRequest"]);
    $resp = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    $header = substr($resp, 0, $info['header_size']);
    $body = substr($resp, $info['header_size']);
    return [$info, $header, $body];
}

function curl_get($url, $cookieFile = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Requested-With: XMLHttpRequest"]);
    $resp = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    $header = substr($resp, 0, $info['header_size']);
    $body = substr($resp, $info['header_size']);
    return [$info, $header, $body];
}

echo "1) Logging in as $email\n";
// The login form posts to /auth/login (the controller maps POST /auth/login to processLogin), so use that URL
list($info, $h, $b) = curl_post($base . '/auth/login', ['email' => $email, 'password' => $password], $cookieFile);
echo "Login HTTP status: {$info['http_code']}\n";
if ($info['http_code'] >= 300 && $info['http_code'] < 400) {
    echo "Login returned redirect (expected). Cookies saved.\n";
} else {
    echo "Login body preview: " . substr($b,0,500) . "\n";
}

// Step 2: fetch processes for work order 1 item 1 (product_id 10)
$woId = 1; $itemId = 1; $productId = 10;
echo "\n2) Fetching processes for WO $woId item $itemId\n";
list($info, $h, $b) = curl_get($base . "/work-orders/{$woId}/items/{$itemId}/processes?product_id={$productId}", $cookieFile);
echo "Status: {$info['http_code']}\n";
echo "Body: " . substr($b,0,800) . "\n";

// Attempt to decode JSON
$data = json_decode($b, true);
if (!$data || empty($data['processes'])) {
    echo "No processes or non-JSON received; aborting further actions.\n";
    exit;
}

$process = $data['processes'][0];
$processId = $process['process_id'] ?? $process['id'] ?? null;
if (!$processId) { echo "No process id available\n"; exit; }

// Step 3: create a batch
echo "\n3) Creating batch for process $processId\n";
list($info, $h, $b) = curl_post($base . "/work-orders/{$woId}/items/{$itemId}/batches/create", ['process_id' => $processId, 'planned_qty' => 10], $cookieFile);
echo "Status: {$info['http_code']}\n";
echo "Body: " . substr($b,0,800) . "\n";
$resp = json_decode($b, true);
if (empty($resp['success'])) { echo "Failed to create batch; aborting.\n"; exit; }
$batchId = $resp['batch_id'];

// Step 4: add a log to the batch
echo "\n4) Adding log to batch $batchId\n";
list($info, $h, $b) = curl_post($base . "/work-orders/batches/{$batchId}/logs/add", ['accepted' => 5, 'repaired' => 0, 'rejected' => 0, 'notes' => 'Auto test entry'], $cookieFile);
echo "Status: {$info['http_code']}\n";
echo "Body: " . substr($b,0,800) . "\n";
$resp = json_decode($b, true);
if (empty($resp['success'])) { echo "Failed to add log; aborting.\n"; exit; }

// Step 5: release some qty
echo "\n5) Releasing 5 units from batch $batchId\n";
list($info, $h, $b) = curl_post($base . "/work-orders/batches/{$batchId}/release", ['released_qty' => 5, 'carrier' => 'Test Carrier', 'notes' => 'Auto release'], $cookieFile);
echo "Status: {$info['http_code']}\n";
echo "Body: " . substr($b,0,800) . "\n";
$resp = json_decode($b, true);
if (empty($resp['success'])) { echo "Failed to release.\n"; exit; }

echo "\nAutomated flow completed successfully. Release ID: " . ($resp['release_id'] ?? 'n/a') . "\n";

// Cleanup cookie file
@unlink($cookieFile);
