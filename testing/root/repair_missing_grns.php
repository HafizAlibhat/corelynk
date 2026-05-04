<?php
// Repair script for missing GRNs on POs with received quantities
// Manual DB connection for CI4

$host = 'localhost';
$user = 'root'; // Adjust as needed
$pass = ''; // Adjust as needed
$dbname = 'corelynk_db'; // From .env

$mysqli = new mysqli($host, $user, $pass, $dbname);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$mysqli->autocommit(false);

try {
    // Find POs with received qty > 0 but no GRN
    $result = $mysqli->query("
        SELECT po.id, po.vendor_id, po.status, SUM(pol.qty_received) as total_received
        FROM purchase_orders po
        JOIN purchase_order_lines pol ON pol.po_id = po.id
        WHERE pol.qty_received > 0
        AND NOT EXISTS (SELECT 1 FROM grns g WHERE g.po_id = po.id)
        GROUP BY po.id
        HAVING total_received > 0
    ");

    echo "Found " . $result->num_rows . " POs with missing GRNs:\n";
    while ($po = $result->fetch_assoc()) {
        echo "PO ID: {$po['id']}, Status: {$po['status']}, Received: {$po['total_received']}\n";
    }
    $result->data_seek(0); // Reset pointer

    while ($po = $result->fetch_assoc()) {
        $poId = $po['id'];
        $vendorId = $po['vendor_id'];

        // Create GRN
        $grnNumber = 'REP-' . $poId . '-' . date('YmdHis');
        $receivedAt = date('Y-m-d H:i:s');
        $createdAt = date('Y-m-d H:i:s');

        $stmt = $mysqli->prepare("INSERT INTO grns (po_id, vendor_id, grn_number, received_at, created_by, created_at) VALUES (?, ?, ?, ?, 1, ?)");
        $stmt->bind_param('sisss', $poId, $vendorId, $grnNumber, $receivedAt, $createdAt);
        $stmt->execute();
        $grnId = $mysqli->insert_id;
        $stmt->close();

        // Get lines with received qty
        $stmt2 = $mysqli->prepare("SELECT id, product_id, qty_received, unit_price FROM purchase_order_lines WHERE po_id = ? AND qty_received > 0");
        $stmt2->bind_param('i', $poId);
        $stmt2->execute();
        $linesResult = $stmt2->get_result();

        while ($line = $linesResult->fetch_assoc()) {
            $stmt3 = $mysqli->prepare("INSERT INTO grn_lines (grn_id, po_line_id, product_id, qty_received, unit_cost) VALUES (?, ?, ?, ?, ?)");
            $stmt3->bind_param('iiidd', $grnId, $line['id'], $line['product_id'], $line['qty_received'], $line['unit_price']);
            $stmt3->execute();
            $stmt3->close();
        }
        $stmt2->close();

        // Update PO status
        $totalOrderedResult = $mysqli->query("SELECT SUM(qty) as total FROM purchase_order_lines WHERE po_id = $poId");
        $totalOrdered = $totalOrderedResult->fetch_assoc()['total'];
        $totalReceived = $po['total_received'];
        $status = $totalReceived >= $totalOrdered ? 'received' : 'partially_received';

        $stmt4 = $mysqli->prepare("UPDATE purchase_orders SET status = ? WHERE id = ?");
        $stmt4->bind_param('si', $status, $poId);
        $stmt4->execute();
        $stmt4->close();

        echo "Created GRN $grnId for PO $poId\n";
    }

    $mysqli->commit();
    echo "Repair completed.\n";
} catch (\Throwable $e) {
    $mysqli->rollback();
    echo "Error: " . $e->getMessage() . "\n";
}

$mysqli->close();
?>