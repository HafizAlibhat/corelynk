<?php
$m = new mysqli('localhost','root','','corelynk_db');
$r=$m->query('SHOW COLUMNS FROM vendors');
while($row=$r->fetch_assoc()) echo $row['Field']."\n";
?>
