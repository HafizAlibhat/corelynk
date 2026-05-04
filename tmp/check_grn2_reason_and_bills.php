<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=corelynk_db_dev', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== GRN 2 lines ===\n";
$q1 = $pdo->query("SELECT gl.id, gl.grn_id, gl.po_line_id, gl.qty_received, gl.over_received_qty, gl.over_receipt_reason_type, gl.over_receipt_reason_details, gl.unit_cost, p.name as product_name FROM purchase_grn_lines gl LEFT JOIN products p ON p.id=gl.product_id WHERE gl.grn_id=2 ORDER BY gl.id");
print_r($q1->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== PO line ordered qty ===\n";
$q2 = $pdo->query("SELECT pol.id, pol.qty, pol.qty_received, pol.unit_price FROM purchase_order_lines pol WHERE pol.id IN (SELECT po_line_id FROM purchase_grn_lines WHERE grn_id=2)");
print_r($q2->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== Related bills by PO of GRN 2 ===\n";
$q3 = $pdo->query("SELECT vb.id, vb.po_id, vb.bill_number, vb.status, vb.based_on, vb.total_amount, vb.balance, vb.bill_date FROM vendor_bills vb WHERE vb.po_id=(SELECT po_id FROM purchase_grns WHERE id=2) ORDER BY vb.id DESC");
$bills = $q3->fetchAll(PDO::FETCH_ASSOC);
print_r($bills);

if (!empty($bills)) {
  $ids = array_map(fn($r)=>(int)$r['id'],$bills);
  $idList = implode(',', $ids);
  echo "\n=== Posted allocations per related bill ===\n";
  $sql = "SELECT vpa.vendor_bill_id, SUM(COALESCE(NULLIF(vpa.amount_allocated,0), vpa.amount, 0)) as paid_amount FROM vendor_payment_allocations vpa INNER JOIN vendor_payments vp ON vp.id=vpa.payment_id WHERE vpa.vendor_bill_id IN ($idList) AND vp.status='posted' GROUP BY vpa.vendor_bill_id";
  $q4 = $pdo->query($sql);
  print_r($q4->fetchAll(PDO::FETCH_ASSOC));
}
