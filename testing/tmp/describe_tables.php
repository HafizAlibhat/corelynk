<?php
$m = mysqli_connect('localhost','root','','corelynk_db');
if (!$m) { echo "connect failed\n"; exit(1); }
$res = mysqli_query($m, 'DESCRIBE purchase_order_lines');
if (!$res) { echo "query failed: ".mysqli_error($m)."\n"; exit(1); }
while ($r = mysqli_fetch_assoc($res)) { echo $r['Field'] . "\n"; }
?>
