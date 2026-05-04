<?php
// Test the full API flow
$base = 'http://192.168.100.110/corelynk/public/api';

function apiCall($url, $method = 'GET', $body = null, $token = null) {
    $ctx = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", array_filter([
                'Content-Type: application/json',
                'Accept: application/json',
                $token ? "Authorization: Bearer $token" : null,
            ])),
            'content' => $body ? json_encode($body) : null,
            'ignore_errors' => true,
        ]
    ]);
    $resp = file_get_contents($url, false, $ctx);
    return json_decode($resp, true);
}

// 1. Login
echo "=== LOGIN ===\n";
$login = apiCall("$base/login", 'POST', ['email' => 'sair@regalinstruments.com', 'password' => 'Test@1234']);
echo json_encode($login, JSON_PRETTY_PRINT) . "\n";

$token = $login['data']['token'] ?? null;
if (!$token) { echo "LOGIN FAILED\n"; exit(1); }

// 2. Owner Summary
echo "\n=== OWNER SUMMARY ===\n";
$summary = apiCall("$base/owner/summary", 'GET', null, $token);
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";

// 3. Sales Orders
echo "\n=== SALES ORDERS ===\n";
$so = apiCall("$base/sales-orders?latest=1", 'GET', null, $token);
echo json_encode($so, JSON_PRETTY_PRINT) . "\n";

// 4. Products
echo "\n=== PRODUCTS (first 2) ===\n";
$pr = apiCall("$base/products?per_page=2", 'GET', null, $token);
echo json_encode($pr, JSON_PRETTY_PRINT) . "\n";
