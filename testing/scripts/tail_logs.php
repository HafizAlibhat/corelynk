<?php
$dir = __DIR__ . '/../writable/logs';
$files = glob($dir . '/log-*.log');
if (!$files) {
    echo "No log files found in $dir\n";
    exit(0);
}
usort($files, function($a,$b){return filemtime($b) - filemtime($a);});
$latest = $files[0];
echo "=== " . basename($latest) . " ===\n";
$lines = 300;
$contents = file($latest);
$tail = array_slice($contents, -$lines);
foreach ($tail as $l) echo $l;
