<?php
// Usage: php tools/print_table_columns.php customer_invoice_lines

$table = $argv[1] ?? null;
if (!$table) {
    fwrite(STDERR, "Please provide table name\n");
    exit(1);
}

require __DIR__ . '/../app/Config/Paths.php';
require __DIR__ . '/../app/Config/Boot/development.php';

$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '\\/ ') . DIRECTORY_SEPARATOR . 'bootstrap.php';

$db = Config\Database::connect();
$cols = $db->getFieldNames($table);

foreach ($cols as $c) {
    echo $c, PHP_EOL;
}
