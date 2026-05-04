<?php
$mysqli = new mysqli('127.0.0.1', 'root', '', 'production_management_system', 3306);
if ($mysqli->connect_errno) {
    echo 'CONNECT_ERR: ' . $mysqli->connect_error . PHP_EOL;
    exit(1);
}

echo "Fixing processes table - making product_id nullable..." . PHP_EOL;

// First, drop the foreign key constraint
$sql = "ALTER TABLE `processes` DROP FOREIGN KEY `processes_ibfk_1`";
if ($mysqli->query($sql)) {
    echo "✅ Dropped foreign key constraint processes_ibfk_1" . PHP_EOL;
} else {
    echo "❌ Error dropping foreign key: " . $mysqli->error . PHP_EOL;
}

// Make product_id nullable
$sql = "ALTER TABLE `processes` MODIFY `product_id` int(10) unsigned NULL";
if ($mysqli->query($sql)) {
    echo "✅ Made product_id nullable" . PHP_EOL;
} else {
    echo "❌ Error making product_id nullable: " . $mysqli->error . PHP_EOL;
}

// Re-add the foreign key constraint but allow NULLs
$sql = "ALTER TABLE `processes` ADD CONSTRAINT `processes_ibfk_1` 
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) 
        ON DELETE CASCADE ON UPDATE CASCADE";
if ($mysqli->query($sql)) {
    echo "✅ Re-added foreign key constraint (allowing NULL)" . PHP_EOL;
} else {
    echo "❌ Error re-adding foreign key: " . $mysqli->error . PHP_EOL;
}

echo PHP_EOL . "Verification - product_id should now be NULL:" . PHP_EOL;
$res = $mysqli->query('DESCRIBE processes');
while ($row = $res->fetch_assoc()) {
    if ($row['Field'] == 'product_id') {
        echo $row['Field'] . "\t" . $row['Type'] . "\t" . ($row['Null'] == 'YES' ? 'NULL ✅' : 'NOT NULL ❌') . PHP_EOL;
        break;
    }
}
