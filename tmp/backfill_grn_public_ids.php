<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=corelynk_db_dev', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$cols = $pdo->query("SHOW COLUMNS FROM purchase_grns LIKE 'public_id'")->fetchAll(PDO::FETCH_ASSOC);
if (empty($cols)) {
    $pdo->exec("ALTER TABLE purchase_grns ADD COLUMN public_id CHAR(36) NULL AFTER id");
    echo "Added purchase_grns.public_id\n";
}

$idx = $pdo->query("SHOW INDEX FROM purchase_grns WHERE Key_name='uq_purchase_grns_public_id'")->fetchAll(PDO::FETCH_ASSOC);
if (empty($idx)) {
    try {
        $pdo->exec("ALTER TABLE purchase_grns ADD UNIQUE KEY uq_purchase_grns_public_id (public_id)");
        echo "Added unique index uq_purchase_grns_public_id\n";
    } catch (Throwable $e) {
        echo "Index note: " . $e->getMessage() . "\n";
    }
}

$rows = $pdo->query("SELECT id FROM purchase_grns WHERE public_id IS NULL OR TRIM(public_id)='' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$upd = $pdo->prepare("UPDATE purchase_grns SET public_id=? WHERE id=?");
foreach ($rows as $r) {
    $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 65535), mt_rand(0, 65535),
        mt_rand(0, 65535),
        mt_rand(16384, 20479),
        mt_rand(32768, 49151),
        mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535)
    );
    $upd->execute([$uuid, (int)$r['id']]);
}

echo "Backfilled rows: " . count($rows) . "\n";
$check = $pdo->query("SELECT id, grn_number, public_id FROM purchase_grns ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
print_r($check);
