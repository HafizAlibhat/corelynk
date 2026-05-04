<?php
$urls = [
    'http://localhost/pro_sys/public/index.php/production/ajax-get-item-processes/3',
    'http://localhost/pro_sys/public/index.php/production/ajax-get-batches'
];

foreach ($urls as $url) {
    echo "Testing AJAX endpoint: $url\n";
    
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
    echo "\n" . str_repeat("=", 80) . "\n";
}