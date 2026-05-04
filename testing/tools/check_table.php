<?php
$db=new mysqli('localhost','root','','production_management_system');
if($db->connect_errno){
    echo 'connect_failed:'.$db->connect_error."\n";
    exit(1);
}
$res=$db->query("SHOW TABLES LIKE 'work_orders'");
if($res && $r=$res->fetch_row()){
    echo 'table_exists:'.$r[0]."\n";
} else {
    echo 'table_missing\n';
}
