<?php
// Full refresh script for Odoo screen caches (large lookback)
$base = 'http://localhost/corelynk';
$url = $base . '/integrations/odoo/screen/action/pull';
$payload = json_encode(['lookback_days' => 3650, 'page_limit' => 200]);
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 1200);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
$resp = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);
$logline = date('c') . " POST $url -> " . ($err ? "ERROR: $err" : "OK: " . substr($resp,0,500)) . "\n";
@file_put_contents(__DIR__ . '/odoo_pull_full.log', $logline, FILE_APPEND);
echo $logline;
