<?php
$db=new mysqli('127.0.0.1','root','','corelynk_db');
if($db->connect_errno){echo 'connect error'; exit(1);} 
$res=$db->query('SHOW COLUMNS FROM quotation_lines');
while($r=$res->fetch_assoc()) echo $r['Field'].'\t'.$r['Type']."\n";
