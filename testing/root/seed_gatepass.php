<?php
$mysqli = new mysqli('localhost', 'root', '', 'production_management_system');
if ($mysqli->connect_error) { echo "DB connect error: {$mysqli->connect_error}\n"; exit(1);} 

$vendorId = null; 
$vres = $mysqli->query("SELECT id FROM vendors ORDER BY id ASC LIMIT 1");
if ($vres && $vres->num_rows>0) { $vendorId = (int) $vres->fetch_assoc()['id']; }

$items = json_encode([[
  'product_id' => null,
  'description' => 'Test Item',
  'quantity' => 5,
  'unit' => 'Pcs',
  'remarks' => 'seed'
]], JSON_UNESCAPED_UNICODE);

$number = 'GP-' . date('Ymd') . '-' . str_pad(rand(1,9999),4,'0',STR_PAD_LEFT);
$type = 'outgoing';
$recipient_type = $vendorId ? 'vendor' : 'internal';
$recipient_name = $vendorId ? null : 'Test Warehouse';
$vendor_id = $vendorId ? $vendorId : null; 
$created_at = date('Y-m-d H:i:s');

$stmt = $mysqli->prepare("INSERT INTO gate_passes (gate_pass_number,type,recipient_type,recipient_name,vendor_id,purpose,items,status,expected_date,actual_date,notes,remarks,created_by,completed_by,created_at,updated_at) VALUES (?,?,?,?,?, 'Seed entry', ?, 'pending', NULL, NULL, NULL, NULL, 1, NULL, ?, ?)");
$stmt->bind_param('ssssisss', $number, $type, $recipient_type, $recipient_name, $vendor_id, $items, $created_at, $created_at);
if (!$stmt->execute()) { echo "Insert error: ".$stmt->error."\n"; exit(1);} 

$id = $stmt->insert_id; echo "Inserted gate_pass id: $id\n"; 
