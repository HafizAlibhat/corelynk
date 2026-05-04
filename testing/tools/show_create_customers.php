<?php
$m=new mysqli('127.0.0.1','root','','corelynk_db');
if($m->connect_errno){ echo "CONNECT_ERROR:".$m->connect_error; exit(1); }
$r=$m->query("SHOW CREATE TABLE customers");
if(!$r){ echo "QUERY_ERROR:".$m->error; exit(1); }
$row=$r->fetch_assoc();
echo $row['Create Table'];
$m->close();
