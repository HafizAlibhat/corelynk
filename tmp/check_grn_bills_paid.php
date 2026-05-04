<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=corelynk_db_dev', 'root', '');
$rows = $pdo->query("SELECT id, bill_number, total_amount, balance, status FROM vendor_bills WHERE id IN (10,13) ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
echo "vendor_bills table values:\n";
print_r($rows);

$sql = "SELECT vpa.vendor_bill_id, SUM(COALESCE(NULLIF(vpa.amount_allocated,0), vpa.amount, 0)) AS paid_amount
        FROM vendor_payment_allocations vpa
        INNER JOIN vendor_payments vp ON vp.id = vpa.payment_id
        WHERE vpa.vendor_bill_id IN (10,13) AND vp.status='posted'
        GROUP BY vpa.vendor_bill_id";
$paid = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
echo "\nposted allocations:\n";
print_r($paid);
