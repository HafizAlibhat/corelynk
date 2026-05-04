<?php
/**
 * Adds quotations.converted_to_sales_order_id if missing.
 * Safe to run multiple times.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

/**
 * NOTE:
 * This script intentionally does NOT bootstrap CodeIgniter (CI4 CLI bootstrap varies by version).
 * Instead it parses DB credentials from app/Config/Database.php and uses mysqli.
 */

function parseDbConfig(string $file): array
{
    $txt = @file_get_contents($file);
    if ($txt === false) {
        throw new RuntimeException('Unable to read Database config: ' . $file);
    }

    $get = function(string $key) use ($txt): ?string {
        // Prefer parsing the CI4 array config: 'key' => 'value'
        if (preg_match("/'" . preg_quote($key, '/') . "'\\s*=>\\s*'([^']*)'/", $txt, $m)) {
            return $m[1];
        }
        // Fallback for alternative styles
        if (preg_match("/public \\$" . preg_quote($key, '/') . "\\s*=\\s*'([^']*)'\\s*;/", $txt, $m)) {
            return $m[1];
        }
        return null;
    };

    $host = $get('hostname') ?? 'localhost';
    $user = $get('username') ?? 'root';
    $pass = $get('password') ?? '';
    $name = $get('database');

    if (!$name) {
        throw new RuntimeException('Could not parse database name from Database.php');
    }

    return [$host, $user, $pass, $name];
}

try {
    [$host, $user, $pass, $name] = parseDbConfig(__DIR__ . '/app/Config/Database.php');
} catch (Throwable $e) {
    echo 'Config error: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

$mysqli = @new mysqli($host, $user, $pass, $name);
if ($mysqli->connect_errno) {
    echo 'DB connect error: ' . $mysqli->connect_error . PHP_EOL;
    exit(1);
}

$mysqli->set_charset('utf8mb4');

try {
    $res = $mysqli->query("SHOW COLUMNS FROM quotations LIKE 'converted_to_sales_order_id'");
    if (!$res) {
        throw new RuntimeException($mysqli->error);
    }
    if ($res->num_rows > 0) {
        echo "OK: quotations.converted_to_sales_order_id already exists." . PHP_EOL;
        exit(0);
    }

    if (!$mysqli->query("ALTER TABLE quotations ADD COLUMN converted_to_sales_order_id INT NULL AFTER status")) {
        throw new RuntimeException($mysqli->error);
    }

    // Add index best-effort
    $mysqli->query("ALTER TABLE quotations ADD INDEX idx_converted_to_sales_order_id (converted_to_sales_order_id)");

    echo "OK: Added quotations.converted_to_sales_order_id (and index)." . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    echo "Failed: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
