<?php
echo "Testing AJAX endpoint: ajax-get-work-order-hierarchy\n";

$url = 'http://localhost:8080/production/ajax-get-work-order-hierarchy';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Requested-With: XMLHttpRequest']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Response code: $httpCode\n";
echo "Response received:\n";
echo "Length: " . strlen($response) . " bytes\n";
echo "Content:\n$response\n";

$data = json_decode($response, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "JSON decoded successfully:\n";
    print_r($data);
} else {
    echo "JSON decode error: " . json_last_error_msg() . "\n";
}