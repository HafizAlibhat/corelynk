<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=corelynk_db_dev', 'root', '');
$sql = "SELECT pol.id, pol.po_id, pol.product_id, pol.description, pol.qty, pol.qty_received, pol.unit_price,
               p.code, p.name, p.unit, p.product_type, p.detailed_type
        FROM purchase_order_lines pol
        LEFT JOIN products p ON p.id = pol.product_id
        WHERE pol.po_id = 9
        ORDER BY pol.id ASC";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    print_r($r);
}
