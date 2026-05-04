<?php
require 'vendor/autoload.php';

$config = new \Config\Database();
$db = \CodeIgniter\Database\Config::connect();

echo "<h2>Finding Laser Cutting Process ID</h2>\n";

$query = $db->query("SELECT * FROM processes WHERE name LIKE '%laser%' OR name LIKE '%cutting%'");
$processes = $query->getResultArray();

if ($processes) {
    echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Category ID</th><th>Active</th></tr>\n";
    foreach($processes as $process) {
        echo "<tr>";
        echo "<td>{$process['id']}</td>";
        echo "<td>{$process['name']}</td>";
        echo "<td>" . ($process['category_id'] ?? 'NULL') . "</td>";
        echo "<td>" . ($process['is_active'] ? 'Yes' : 'No') . "</td>";
        echo "</tr>\n";
    }
    echo "</table><br>\n";
} else {
    echo "No processes found with 'laser' or 'cutting' in name<br>\n";
}

echo "<h3>All active processes:</h3>\n";
$query = $db->query("SELECT p.*, pc.name as category_name FROM processes p LEFT JOIN process_categories pc ON pc.id = p.category_id WHERE p.is_active = 1 ORDER BY p.name");
$allProcesses = $query->getResultArray();

if ($allProcesses) {
    echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Category</th></tr>\n";
    foreach($allProcesses as $process) {
        echo "<tr>";
        echo "<td>{$process['id']}</td>";
        echo "<td>{$process['name']}</td>";
        echo "<td>" . ($process['category_name'] ?? 'No Category') . "</td>";
        echo "</tr>\n";
    }
    echo "</table><br>\n";
} else {
    echo "No active processes found<br>\n";
}
?>
