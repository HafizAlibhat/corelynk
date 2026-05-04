<?php
// Temporary schema check script
$db = new PDO('mysql:host=localhost;dbname=corelynk_db', 'root', '');
$stmt = $db->query("SHOW COLUMNS FROM sales_orders LIKE 'shipping_amount'");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo $row ? "YES\n" : "NO\n";
