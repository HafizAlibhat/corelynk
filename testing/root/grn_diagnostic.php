<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== GRN DATA DIAGNOSTIC ===\n\n";

// Connect to database
$db = mysqli_connect('localhost', 'root', '', 'corelynk_db');
if (!$db) {
    echo "❌ MySQL Connection Failed: " . mysqli_connect_error() . "\n";
    exit(1);
}

echo "✓ Connected to MySQL\n";
echo "MySQL Version: " . mysqli_get_server_info($db) . "\n\n";

// Check GRN tables
echo "=== CHECKING GRN TABLES ===\n";

$query = "SELECT COUNT(*) as count FROM purchase_grns";
$result = mysqli_query($db, $query);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "Total GRNs: " . $row['count'] . "\n";
} else {
    echo "❌ Error querying purchase_grns: " . mysqli_error($db) . "\n";
}

// Get latest GRN
$query = "SELECT id, grn_number, received_at, created_at, notes FROM purchase_grns ORDER BY created_at DESC LIMIT 3";
$result = mysqli_query($db, $query);
if ($result && mysqli_num_rows($result) > 0) {
    echo "\nLatest GRNs:\n";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "  GRN ID: " . $row['id'] . ", Number: " . $row['grn_number'] . "\n";
        echo "    Created: " . $row['created_at'] . "\n";
        echo "    Received: " . $row['received_at'] . "\n";
        if ($row['notes']) {
            echo "    Notes: " . substr($row['notes'], 0, 100) . "\n";
        }
    }
} else {
    echo "No GRNs found in database\n";
}

// Check GRN lines
$query = "SELECT COUNT(*) as count FROM purchase_grn_lines";
$result = mysqli_query($db, $query);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "\nTotal GRN Lines: " . $row['count'] . "\n";
} else {
    echo "Error: " . mysqli_error($db) . "\n";
}

// Check for any GRNs with over_received_qty > 0 (extra quantities)
$query = "SELECT grn_id, COUNT(*) as line_count, SUM(over_received_qty) as total_over FROM purchase_grn_lines WHERE over_received_qty > 0 GROUP BY grn_id";
$result = mysqli_query($db, $query);
if ($result && mysqli_num_rows($result) > 0) {
    echo "\nGRNs with Extra Quantities:\n";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "  GRN ID " . $row['grn_id'] . ": " . $row['total_over'] . " extra units (in " . $row['line_count'] . " lines)\n";
    }
} else {
    echo "\nNo GRNs with extra quantities found\n";
}

// Check application logs
echo "\n=== APPLICATION LOGS ===\n";
$logFile = 'writable/logs/log-' . date('Y-m-d') . '.log';
if (file_exists($logFile)) {
    $lines = shell_exec("tail -20 " . escapeshellarg($logFile));
    echo "Latest log entries:\n" . $lines;
} else {
    echo "Log file not found: $logFile\n";
}

mysqli_close($db);
echo "\n✓ Diagnostic complete\n";
?>
