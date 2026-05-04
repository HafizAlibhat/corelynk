<?php
if ($argc<2) { echo "Usage: php convert_rfq.php <rfq_id>\n"; exit(1); }
$rfqId = (int)$argv[1];
$m = new mysqli('127.0.0.1','root','', 'corelynk_db');
if ($m->connect_errno) { echo 'CONNECT_ERR: '.$m->connect_error.PHP_EOL; exit(1); }
$m->begin_transaction();
try {
    $res = $m->query("SELECT * FROM purchase_rfqs WHERE id = $rfqId FOR UPDATE");
    if (!$res) throw new Exception($m->error);
    $rfq = $res->fetch_assoc();
    if (!$rfq) throw new Exception('RFQ not found');
    if (!in_array($rfq['status'], ['sent','draft'])) throw new Exception('Only sent or draft RFQ can be accepted');

    // create PO
    $po_number = $m->real_escape_string($rfq['rfq_number'] ?: '');
    $vendor_id = intval($rfq['vendor_id'])?: 'NULL';
    $subtotal = is_null($rfq['subtotal'])?0.00:floatval($rfq['subtotal']);
    $total = is_null($rfq['grand_total'])? (is_null($rfq['total'])?0.00:floatval($rfq['total'])) : floatval($rfq['grand_total']);
    $created_by = intval($rfq['created_by'])?:'NULL';
    $created_at = date('Y-m-d H:i:s');

    $sql = "INSERT INTO purchase_orders (po_number, rfq_id, vendor_id, status, subtotal, total, created_by, created_at) VALUES ('{$po_number}', {$rfqId}, ".($vendor_id==='NULL'?'NULL':$vendor_id).", 'draft', {$m->real_escape_string((string)$subtotal)}, {$m->real_escape_string((string)$total)}, ".($created_by==='NULL'?'NULL':$created_by).", '{$created_at}')";
    if (!$m->query($sql)) throw new Exception('PO insert error: '.$m->error);
    $poId = $m->insert_id;
    echo "Created PO id: $poId\n";
    // copy lines
    $res2 = $m->query("SELECT * FROM purchase_rfq_lines WHERE rfq_id = $rfqId");
    if (!$res2) throw new Exception($m->error);
    while($ln = $res2->fetch_assoc()){
        $qty = isset($ln['quantity']) ? floatval($ln['quantity']) : (isset($ln['qty'])?floatval($ln['qty']):0);
        if ($qty<=0) continue;
        $product_id = isset($ln['product_id'])?intval($ln['product_id']):'NULL';
        $description = $m->real_escape_string($ln['description']??'');
        $unit_price = isset($ln['unit_cost'])?floatval($ln['unit_cost']):(isset($ln['unit_price'])?floatval($ln['unit_price']):'NULL');
        $sql2 = "INSERT INTO purchase_order_lines (po_id, product_id, description, qty, unit_price, qty_received, created_at) VALUES (".$poId.", ".($product_id==='NULL'?'NULL':$product_id).", '".$description."', '".$m->real_escape_string((string)$qty)."', '".$m->real_escape_string((string)$unit_price)."', 0, '{$created_at}')";
        if (!$m->query($sql2)) throw new Exception('PO line insert error: '.$m->error);
    }
    // update RFQ status
    if (!$m->query("UPDATE purchase_rfqs SET status='accepted', updated_at='{$created_at}' WHERE id={$rfqId}")) throw new Exception('RFQ update error: '.$m->error);

    $m->commit();
    echo "Conversion successful for RFQ $rfqId -> PO $poId\n";
} catch (Exception $e) {
    $m->rollback();
    echo 'ERROR: '.$e->getMessage()."\n";
    exit(1);
}
