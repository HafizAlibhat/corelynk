<?php
// Simple session test
session_start();
$_SESSION['test'] = 'working';
echo "✅ Session test successful!";
?>
