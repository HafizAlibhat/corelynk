<?php
$mysqli = new mysqli('127.0.0.1','root','','corelynk_db');
if ($mysqli->connect_errno) { echo "CONNECT_ERR:".$mysqli->connect_error; exit(1); }
$hasZero = $mysqli->query("SELECT COUNT(*) as c FROM vendor_contacts WHERE id=0")->fetch_assoc()['c'];
echo "rows_with_id_0=".$hasZero.PHP_EOL;
$max = (int)$mysqli->query("SELECT MAX(id) as m FROM vendor_contacts")->fetch_assoc()['m'];
echo "max_before=".$max.PHP_EOL;
if ($hasZero>0) {
    $newId = $max + 1;
    echo "Updating id 0 to $newId...".PHP_EOL;
    $ok = $mysqli->query("UPDATE vendor_contacts SET id=$newId WHERE id=0");
    if (!$ok) { echo "UPDATE_ERR:".$mysqli->error.PHP_EOL; exit(1); }
    echo "Updated.".PHP_EOL;
    $max = (int)$mysqli->query("SELECT MAX(id) as m FROM vendor_contacts")->fetch_assoc()['m'];
}
$newAI = $max + 1;
echo "Setting AUTO_INCREMENT to $newAI and altering column...".PHP_EOL;
$alter = $mysqli->query("ALTER TABLE vendor_contacts MODIFY id int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=$newAI");
if (!$alter) { echo "ALTER_ERR:".$mysqli->error.PHP_EOL; exit(1); }
echo "Altered successfully.".PHP_EOL;
$rows = $mysqli->query('SELECT id,vendor_id,name FROM vendor_contacts ORDER BY id ASC');
while ($r = $rows->fetch_assoc()) { echo $r['id'].'|'.$r['vendor_id'].'|'.$r['name'].PHP_EOL; }
?>