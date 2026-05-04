<?php
// Insert minimal test data to create a valid process_batches row and print the new batch id
// Usage: php tools/insert_test_batch.php

$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'production_management_system';
$port = 3306;
$mysqli = new mysqli($host, $user, $pass, $dbname, $port);
if ($mysqli->connect_errno) { echo "Connect failed: ({$mysqli->connect_errno}) {$mysqli->connect_error}\n"; exit(1); }
$mysqli->set_charset('utf8mb4');

$mysqli->begin_transaction();
try {
    // Ensure there's a user (created_by can be null, but some constraints or app logic expect user exists)
    $res = $mysqli->query("SELECT id FROM users LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        $userId = $row['id'];
    } else {
        $mysqli->query("INSERT INTO users (name, email, password, created_at, updated_at) VALUES ('Test User', 'test@example.local', 'password', NOW(), NOW())");
        $userId = $mysqli->insert_id;
    }

    // Ensure there's a product
    $res = $mysqli->query("SELECT id FROM products LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        $productId = $row['id'];
    } else {
        $mysqli->query("INSERT INTO products (name, code, description, created_at, updated_at) VALUES ('Test Product', 'TP-001', 'Inserted by test script', NOW(), NOW())");
        $productId = $mysqli->insert_id;
    }

    // Ensure there's a process
    $res = $mysqli->query("SELECT id FROM processes LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        $processId = $row['id'];
    } else {
        $mysqli->query("INSERT INTO processes (name, description, created_at, updated_at) VALUES ('Test Process', 'Inserted by test script', NOW(), NOW())");
        $processId = $mysqli->insert_id;
    }

    // Create a work order (schema requires product_id and quantity_ordered in this project)
    $woNumber = 'TEST-WO-'.time();
    $quantityOrdered = 10;
    $stmt = $mysqli->prepare("INSERT INTO work_orders (wo_number, product_id, customer_name, quantity_ordered, due_date, status, created_at, updated_at) VALUES (?, ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'planned', NOW(), NOW())");
    if ($stmt) {
        $customerName = 'Test Customer';
        $stmt->bind_param('sisi', $woNumber, $productId, $customerName, $quantityOrdered);
        $stmt->execute();
        $woId = $mysqli->insert_id;
        $stmt->close();
    } else {
        throw new Exception('Prepare failed for work_orders insert: ' . $mysqli->error);
    }

    // Create work_order_item linking to product
    $stmt2 = $mysqli->prepare("INSERT INTO work_order_items (work_order_id, product_id, quantity_ordered, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
    if ($stmt2) {
        $stmt2->bind_param('iii', $woId, $productId, $quantityOrdered);
        $stmt2->execute();
        $woItemId = $mysqli->insert_id;
        $stmt2->close();
    } else {
        throw new Exception('Prepare failed for work_order_items insert: ' . $mysqli->error);
    }

    // Create a process linking for this product if product_processes table exists, but process_batches references processes directly via process_id
    // Insert a batch
    $batchCode = 'TEST-BATCH-'.time();
    $mysqli->query("INSERT INTO process_batches (work_order_item_id, process_id, batch_code, planned_qty, actual_qty, status, created_by, created_at, updated_at) VALUES ({$woItemId}, {$processId}, '{$mysqli->real_escape_string($batchCode)}', 10, 0, 'open', {$userId}, NOW(), NOW())");
    $batchId = $mysqli->insert_id;

    $mysqli->commit();
    echo "Inserted test batch id={$batchId} (work_order_id={$woId}, work_order_item_id={$woItemId})\n";
    exit(0);
} catch (Exception $e) {
    $mysqli->rollback();
    echo "Exception while inserting test data: " . $e->getMessage() . "\n";
    exit(2);
}
