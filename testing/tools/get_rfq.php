<?php
$id = $argv[1] ?? null;
if (!$id) { echo "Usage: php get_rfq.php <id>\n"; exit(1); }
$m = new mysqli('127.0.0.1','root','', 'corelynk_db');
if ($m->connect_errno) { echo 'CONNECT_ERR: '.$m->connect_error.PHP_EOL; exit(1); }
$id = (int)$id;
$res = $m->query("SELECT * FROM purchase_rfqs WHERE id = $id");
if (!$res) { echo 'ERR: '.$m->error.PHP_EOL; exit(1); }
$rfq = $res->fetch_assoc();
if (!$rfq) { echo "RFQ not found\n"; exit(0); }
echo "RFQ:\n";
foreach ($rfq as $k=>$v) { echo "$k -> ".(is_null($v)?'NULL':$v)."\n"; }
$res2 = $m->query("SELECT * FROM purchase_rfq_lines WHERE rfq_id = $id");
if ($res2) {
    echo "\nLINES:\n";
    while($row=$res2->fetch_assoc()){
        foreach($row as $k=>$v) echo "$k -> ".(is_null($v)?'NULL':$v)."\t";
        echo "\n";
    }
}
