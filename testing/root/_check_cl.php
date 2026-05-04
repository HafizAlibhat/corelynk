<?php
$db = new mysqli('localhost', 'root', '', 'corelynk_db');
$r = $db->query('DESCRIBE cheque_lines');
if ($r) { while ($row = $r->fetch_assoc()) { echo implode(' | ', array_values($row)) . "\n"; } }
else { echo "Error: " . $db->error . "\n"; }
$db->close();
