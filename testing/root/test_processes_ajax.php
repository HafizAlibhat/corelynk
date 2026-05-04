<?php
// Test the AJAX endpoint for getting item processes
$url = 'http://localhost/pro_sys/public/production/ajaxGetItemProcesses/3';

// Create a context with headers to simulate AJAX request
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => [
            'X-Requested-With: XMLHttpRequest',
            'User-Agent: Mozilla/5.0'
        ]
    ]
]);

$response = file_get_contents($url, false, $context);

echo "Response for item ID 3:\n";
echo $response;
echo "\n\n";

// Also test item ID 4 (testing product)
$url2 = 'http://localhost/pro_sys/public/production/ajaxGetItemProcesses/4';
$response2 = file_get_contents($url2, false, $context);

echo "Response for item ID 4:\n";
echo $response2;
?>