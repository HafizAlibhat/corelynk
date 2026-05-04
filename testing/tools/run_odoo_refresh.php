<?php
// Lightweight background script to warm Odoo screen caches by calling local endpoints.
// Run with: php tools/run_odoo_refresh.php

$base = 'http://localhost/corelynk';
$url = $base . '/integrations/odoo/screen/action/pull';
$payload = json_encode(['lookback_days' => 90, 'page_limit' => 200]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 600);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
$resp = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);
$logline = date('c') . " POST $url -> " . ($err ? "ERROR: $err" : "OK: " . substr($resp,0,100)) . "\n";
@file_put_contents(__DIR__ . '/odoo_refresh.log', $logline, FILE_APPEND);


?>