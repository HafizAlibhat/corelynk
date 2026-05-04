<?php
$pdo = new PDO('mysql:host=localhost;dbname=production_management_system', 'root', '');
echo "=== PRODUCT_PROCESSES TABLE STRUCTURE ===\n";
$result = $pdo->query('DESCRIBE product_processes');
while ($row = $result->fetch()) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}

echo "\n=== PROCESSES TABLE STRUCTURE ===\n";
$result = $pdo->query('DESCRIBE processes');
while ($row = $result->fetch()) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}
?>
