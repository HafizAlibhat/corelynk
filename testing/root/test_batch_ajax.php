<?php
// Test AJAX endpoint for batch data
$url = 'http://localhost/pro_sys/public/production/ajax-get-batches';

echo "<h3>Testing AJAX Endpoint</h3>";
echo "URL: $url<br><br>";

// Test with curl
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'X-Requested-With: XMLHttpRequest'
));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode<br>";
if ($error) {
    echo "cURL Error: $error<br>";
}

echo "Response:<br>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

// Parse JSON response if valid
if ($httpCode == 200 && $response) {
    $data = json_decode($response, true);
    if ($data && isset($data['batches'])) {
        echo "<br><h4>Parsed Data:</h4>";
        echo "Number of batches: " . count($data['batches']) . "<br>";
        foreach ($data['batches'] as $batch) {
            echo "Batch: " . ($batch['batch_code'] ?? 'NULL') . 
                 ", WO: " . ($batch['wo_number'] ?? 'NULL') . 
                 ", Product: " . ($batch['product_name'] ?? 'NULL') . 
                 ", Process: " . ($batch['process_name'] ?? 'NULL') . "<br>";
        }
    } else {
        echo "<br>Invalid JSON or no batches data<br>";
    }
}
?>