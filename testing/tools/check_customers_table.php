<?php
$m=new mysqli('127.0.0.1','root','','corelynk_db');
if($m->connect_errno){ echo "CONNECT_ERROR:".$m->connect_error; exit(1); }
$r=$m->query("SHOW TABLES LIKE 'customers'");
if(!$r){ echo "QUERY_ERROR:".$m->error; exit(1); }
echo "ROWS:".$r->num_rows.PHP_EOL;
if($r->num_rows>0){ $row=$r->fetch_row(); echo "FOUND:".$row[0].PHP_EOL; }
$m->close();
