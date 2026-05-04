<?php
$url = 'http://localhost/corelynk/corelynk/fx-rates?base=USD&symbols=PKR,EUR,GBP';
$ctx = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 5,
        'header' => "Accept: application/json\r\n",
    ],
]);
$out = @file_get_contents($url, false, $ctx);
$code = 0;
if (isset($http_response_header) && is_array($http_response_header)) {
    foreach ($http_response_header as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) {
            $code = (int)$m[1];
            break;
        }
    }
}
echo "HTTP {$code}\n";
if ($out === false) {
    echo "FAILED\n";
    exit(1);
}
echo substr($out, 0, 800) . "\n";
