<?php
echo "Testing hierarchy endpoint through development server:\n";

$url = 'http://localhost/pro_sys/public/index.php/production/ajax-get-work-order-hierarchy';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-Requested-With: XMLHttpRequest',
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "URL: $url\n";
echo "Response code: $httpCode\n";
echo "Curl error: " . ($error ?: 'none') . "\n";
echo "Response length: " . strlen($response) . " bytes\n";
echo "Response:\n$response\n";

if ($httpCode == 200 && $response) {
    $data = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "\nJSON decoded successfully:\n";
        print_r($data);
    } else {
        echo "\nJSON decode error: " . json_last_error_msg() . "\n";
    }
}