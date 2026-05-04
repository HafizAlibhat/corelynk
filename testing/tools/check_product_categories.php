<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=corelynk_db;charset=utf8mb4', 'root', '');
    $stmt = $pdo->query("SHOW CREATE TABLE product_categories");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo $row['Create Table'];
    } else {
        echo "No table product_categories found\n";
    }
} catch (PDOException $e) {
    echo "PDO error: " . $e->getMessage() . "\n";
}
