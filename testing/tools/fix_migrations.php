<?php
// Repair migrations table by inserting missing migration entries for files in app/Database/Migrations
$db = new mysqli('localhost', 'root', '', 'corelynk_db', 3306);
if ($db->connect_errno) {
    echo "DB connect failed: " . $db->connect_error . PHP_EOL;
    exit(1);
}
$files = glob(__DIR__ . '/../app/Database/Migrations/*.php');
$inserted = 0;
foreach ($files as $f) {
    $content = file_get_contents($f);
    if (preg_match('/class\s+([A-Za-z0-9_]+)\s+extends/', $content, $m)) {
        $class = $m[1];
        $version = basename($f, '.php');
        $stmt = $db->prepare('SELECT COUNT(*) FROM migrations WHERE version = ?');
        $stmt->bind_param('s', $version);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        if (!$count) {
            $time = time();
            $batch = 1;
            $grp = 'default';
            $ns = 'App';
            $ins = $db->prepare('INSERT INTO migrations (`version`,`class`,`group`,`namespace`,`time`,`batch`) VALUES (?, ?, ?, ?, ?, ?)');
            $ins->bind_param('ssssii', $version, $class, $grp, $ns, $time, $batch);
            if ($ins->execute()) {
                $inserted++;
                echo "Inserted migration record: $version -> $class\n";
            } else {
                echo "Failed to insert $version: " . $ins->error . "\n";
            }
            $ins->close();
        }
    }
}
echo "Done. Inserted $inserted missing migration records." . PHP_EOL;
$db->close();
