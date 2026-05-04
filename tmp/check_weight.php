<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=corelynk_db_dev', 'root', '');
$row = $pdo->query('SELECT id,name,weight,unit_weight,weight_unit FROM products WHERE id=37')->fetch(PDO::FETCH_ASSOC);
print_r($row);
