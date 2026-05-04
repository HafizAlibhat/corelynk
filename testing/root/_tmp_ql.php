<?php
$m = new mysqli('localhost','root','','corelynk_db');

// Add missing columns
$m->query('ALTER TABLE company_settings ADD COLUMN website VARCHAR(255) DEFAULT NULL AFTER email');
$m->query('ALTER TABLE company_settings ADD COLUMN invoice_footer TEXT DEFAULT NULL AFTER website');
$m->query('ALTER TABLE company_settings ADD COLUMN phone VARCHAR(50) DEFAULT NULL AFTER contact');

echo "Columns added (or already exist)\n";
echo "=== company_settings columns ===\n";
$r = $m->query('DESCRIBE company_settings');
while($row = $r->fetch_assoc()) {
    echo $row['Field'] . ' | ' . $row['Type'] . "\n";
}
