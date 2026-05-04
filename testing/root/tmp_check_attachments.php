<?php
// Temporary script to list attachments for a journal entry
$entryId = $argv[1] ?? '1';
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=corelynk', 'root', '');
    $sql = 'SELECT id,document_type,document_id,file_path,original_name,uploaded_at FROM document_attachments WHERE document_id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$entryId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        echo "No rows found for document_id={$entryId}\n";
        exit(0);
    }
    foreach ($rows as $r) {
        echo implode("\t", [
            $r['id'], $r['document_type'], $r['document_id'], $r['file_path'], $r['original_name'], $r['uploaded_at']
        ]) . PHP_EOL;
    }
} catch (PDOException $e) {
    echo 'DB error: ' . $e->getMessage() . PHP_EOL;
    exit(2);
}
