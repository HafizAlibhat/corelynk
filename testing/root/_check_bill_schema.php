<?php
$pdo = new PDO('mysql:host=localhost;dbname=corelynk_db', 'root', '');
$cols = $pdo->query('DESCRIBE vendor_bills')->fetchAll(PDO::FETCH_COLUMN);
echo "vendor_bills: " . implode(', ', $cols) . PHP_EOL;
$cols2 = $pdo->query('DESCRIBE journal_entries')->fetchAll(PDO::FETCH_COLUMN);
echo "journal_entries: " . implode(', ', $cols2) . PHP_EOL;
