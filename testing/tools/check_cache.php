<?php
$dsn = 'mysql:host=127.0.0.1;dbname=corelynk_db';
try{
    $pdo = new PDO($dsn, 'root', '');
    $tables = ['sales_cache','sale_lines_cache','purchases_cache','purchase_lines_cache'];
    foreach($tables as $t){
        try{
            $stmt = $pdo->query('SELECT COUNT(*) AS c FROM ' . $t);
            $c = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC)['c'] : 0;
            echo $t . ': ' . $c . PHP_EOL;
        } catch (Exception $e) {
            echo $t . ': ERROR - ' . $e->getMessage() . PHP_EOL;
        }
    }
    echo "\nSample sales_cache row:\n";
    $r = $pdo->query('SELECT * FROM sales_cache LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    var_export($r);
} catch (Exception $e) {
    echo 'DB connection failed: ' . $e->getMessage() . PHP_EOL;
}
