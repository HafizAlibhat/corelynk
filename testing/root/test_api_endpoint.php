<?php
echo "<h2>Testing API Endpoint: /products/10/processes/add</h2>\n";

// Test with curl to simulate the exact AJAX call
$url = 'http://localhost/pro_sys/public/products/10/processes/add';
$data = 'process_ids=1'; // Assuming process ID 1 exists

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    'X-Requested-With: XMLHttpRequest'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode<br>\n";
echo "Response: $response<br>\n";

if ($httpCode === 200) {
    $json = json_decode($response, true);
    if ($json) {
        echo "<h3>Parsed JSON Response:</h3>\n";
        echo "Success: " . ($json['success'] ? 'true' : 'false') . "<br>\n";
        echo "Message: " . $json['message'] . "<br>\n";
    } else {
        echo "Response is not valid JSON<br>\n";
    }
} else {
    echo "HTTP request failed<br>\n";
}

// Let's also test with multiple IDs as comma-separated
echo "<h2>Testing with comma-separated IDs</h2>\n";

$data2 = 'process_ids=1,2';
$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, $url);
curl_setopt($ch2, CURLOPT_POST, 1);
curl_setopt($ch2, CURLOPT_POSTFIELDS, $data2);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    'X-Requested-With: XMLHttpRequest'
]);

$response2 = curl_exec($ch2);
$httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

echo "HTTP Code: $httpCode2<br>\n";
echo "Response: $response2<br>\n";
?>
