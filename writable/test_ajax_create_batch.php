<?php
// Simple test client to POST JSON to ajax_create_batch
$payload = [
    'process_id' => 20,
    'work_order_item_id' => 1,
    'planned_qty' => 1
];

$ch = curl_init('http://localhost/pro_sys/public/work-orders/ajax_create_batch');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
$res = curl_exec($ch);
$err = curl_error($ch);
$info = curl_getinfo($ch);
curl_close($ch);

file_put_contents(__DIR__ . '/test_ajax_create_batch_response.txt', "INFO:\n" . print_r($info, true) . "\n\nERR:\n" . $err . "\n\nRESP:\n" . $res);

echo "Test sent, check writable/test_ajax_create_batch_response.txt\n";
