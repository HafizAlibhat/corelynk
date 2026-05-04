<?php
// Simple HTTP GET with custom header to test AJAX endpoint
$url = 'http://localhost/pro_sys/public/index.php/work-orders/1/items/1/processes?product_id=10';
$options = [
    'http' => [
        'method' => 'GET',
        'header' => "X-Requested-With: XMLHttpRequest\r\n",
        'ignore_errors' => true,
        'timeout' => 5,
    ]
];
$ctx = stream_context_create($options);
$response = @file_get_contents($url, false, $ctx);
$status = null;
if (!empty($http_response_header)) {
    foreach ($http_response_header as $h) {
        if (preg_match('#HTTP/\d+\.\d+\s+(\d+)#', $h, $m)) {
            $status = (int)$m[1];
            break;
        }
    }
}
echo "Status: " . ($status ?? 'no-status') . "\n";
echo "Body:\n" . ($response ?? 'no-body') . "\n";
