<?php
if ($argc < 2) { echo "Usage: php show_columns_table.php <table>\n"; exit(1); }
$table = $argv[1];
$m = new mysqli('127.0.0.1','root','', 'corelynk_db');
if ($m->connect_errno) { echo 'CONNECT_ERR: '.$m->connect_error.PHP_EOL; exit(1); }
$res = $m->query('SHOW COLUMNS FROM ' . $table);
if (!$res) { echo 'QUERY_ERR: '.$m->error.PHP_EOL; exit(1); }
while ($r = $res->fetch_assoc()) {
    echo $r['Field'] . "\t" . $r['Type'] . "\t" . $r['Null'] . "\t" . $r['Key'] . "\t" . $r['Default'] . "\t" . $r['Extra'] . PHP_EOL;
}
