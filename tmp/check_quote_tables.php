<?php
$mysqli = new mysqli('127.0.0.1','root','','corelynk_db_dev',3306);
if ($mysqli->connect_errno) { echo 'DB connect failed: ' . $mysqli->connect_error . PHP_EOL; exit(1); }
$tables = $mysqli->query("SHOW TABLES LIKE 'quotation%'");
while($t=$tables->fetch_row()){ echo $t[0].PHP_EOL; }
$ids = [16];
foreach($ids as $qid){
  $r = $mysqli->query("SELECT COUNT(*) c FROM quotation_lines WHERE quotation_id=".(int)$qid);
  $c = $r ? $r->fetch_assoc()['c'] : 'err';
  echo "quotation_lines count for $qid: $c".PHP_EOL;
}
$mysqli->close();
?>
