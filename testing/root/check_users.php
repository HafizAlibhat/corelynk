<?php
$c = new PDO('mysql:host=localhost;dbname=corelynk_db', 'root', '');
$rows = $c->query('SELECT id, username, email, SUBSTRING(password_hash,1,12) as hash_pre, is_active FROM users')->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo $r['id'].'|'.$r['username'].'|'.$r['email'].'|'.$r['hash_pre'].'|'.$r['is_active']."\n";
}

// Also reset password for sair so we can test
$newHash = password_hash('Test@1234', PASSWORD_BCRYPT);
$c->prepare('UPDATE users SET password_hash=? WHERE username=?')->execute([$newHash, 'sair']);
echo "\nPassword for sair reset to Test@1234\n";
