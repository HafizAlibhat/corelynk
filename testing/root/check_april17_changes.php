<?php
echo "Analyzing April 17 vs April 16 table changes...\n\n";

$april16Dir = 'C:\\xampp\\mysql\\data_backup_20260416_212500\\corelynk_db';
$april17Dir = 'C:\\xampp\\mysql\\data_user_backup_20260417_190141\\corelynk_db';

echo "April 16 tables:\n";
$april16Files = glob($april16Dir . '\\*.ibd');
echo "Count: " . count($april16Files) . "\n\n";

echo "April 17 tables:\n";
$april17Files = glob($april17Dir . '\\*.ibd');
echo "Count: " . count($april17Files) . "\n\n";

// Find tables modified in April 17
echo "Tables modified in April 17 (by file timestamp):\n";
$april16Time = strtotime('2026-04-16 21:50:00');
$modifiedTables = [];

foreach ($april17Files as $file) {
    $mtime = filemtime($file);
    $tableName = basename($file, '.ibd');
    
    if ($mtime > $april16Time) {
        $modifiedTables[] = $tableName;
        echo "  - $tableName: " . date('Y-m-d H:i:s', $mtime) . "\n";
    }
}

echo "\nTotal modified tables: " . count($modifiedTables) . "\n";
echo "\nModified table names:\n";
foreach ($modifiedTables as $t) {
    echo "  $t\n";
}
?>
