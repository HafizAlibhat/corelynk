<?php
// Direct test of the AJAX endpoint to see the exact error
// Run this from command line: php test_ajax_endpoint.php

$url = 'http://localhost/pro_sys/public/index.php/production/ajax-get-item-processes/3';

echo "Testing AJAX endpoint: $url\n";

// Create a context for the request
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => [
            'X-Requested-With: XMLHttpRequest',
            'User-Agent: TestScript/1.0'
        ]
    ]
]);

// Make the request
$result = file_get_contents($url, false, $context);

if ($result === false) {
    echo "ERROR: Could not fetch URL\n";
    print_r(error_get_last());
} else {
    echo "Response received:\n";
    echo "Length: " . strlen($result) . " bytes\n";
    echo "Content:\n";
    echo $result . "\n";
    
    // Try to decode as JSON
    $json = json_decode($result, true);
    if ($json) {
        echo "\nJSON decoded successfully:\n";
        print_r($json);
    } else {
        echo "\nNot valid JSON. JSON error: " . json_last_error_msg() . "\n";
        echo "First 500 chars of response:\n";
        echo substr($result, 0, 500) . "\n";
    }
}
?>
