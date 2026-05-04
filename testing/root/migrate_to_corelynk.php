<?php
/**
 * Migrate database to corelynk_db and update .env
 *
 * Usage examples (PowerShell):
 *  php migrate_to_corelynk.php --source=old_db --target=corelynk_db --baseurl=http://localhost/corelynk/
 *  php migrate_to_corelynk.php --source=old_db --target=corelynk_db --dry-run
 *
 * Defaults:
 *  --user=root (no password)
 *  --mysql-bin autodetected (C:\xampp\mysql\bin or PATH)
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

function abort($msg, $code = 1) {
    fwrite(STDERR, "ERROR: $msg\n");
    exit($code);
}

function info($msg) { fwrite(STDOUT, "[+] $msg\n"); }
function warn($msg) { fwrite(STDOUT, "[!] $msg\n"); }

function argval($name, $default = null) {
    foreach ($GLOBALS['argv'] as $arg) {
        if (strpos($arg, "--$name=") === 0) {
            return substr($arg, strlen($name) + 3);
        }
        if ($arg === "--$name") return true;
    }
    return $default;
}

function sanitize_db($name) {
    if ($name === null || $name === '') return $name;
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        abort("Invalid database name '$name'. Allowed: letters, numbers, underscore.");
    }
    return $name;
}

function find_executable($candidates) {
    // If an explicit path is provided and exists, return it
    foreach ($candidates as $c) {
        if ($c && file_exists($c)) return $c;
    }
    // Try PATH
    foreach ($candidates as $c) {
        $name = basename($c);
        $which = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
            ? "where $name 2>nul"
            : "which $name 2>/dev/null";
        @exec($which, $out, $code);
        if ($code === 0 && !empty($out[0])) return $out[0];
    }
    return null;
}

function run_cmd($cmd, $mask = false) {
    info(($mask ? '[exec]' : $cmd));
    exec($cmd, $out, $code);
    if ($code !== 0) {
        warn('Command failed with code ' . $code);
    }
    return [$code, $out];
}

function update_env_file($projectRoot, $targetDb, $baseUrl = null) {
    $envPath = $projectRoot . DIRECTORY_SEPARATOR . '.env';
    $envTemplate = $projectRoot . DIRECTORY_SEPARATOR . 'env';

    if (!file_exists($envPath)) {
        if (file_exists($envTemplate)) {
            copy($envTemplate, $envPath);
            info('Created .env from env template');
        } else {
            warn('No .env or env template found; skipping .env update');
            return;
        }
    }

    $backup = $envPath . '.bak_' . date('Ymd_His');
    if (!copy($envPath, $backup)) {
        warn('Could not create .env backup; continuing');
    } else {
        info("Backed up .env to $backup");
    }

    $contents = file_get_contents($envPath);
    if ($contents === false) abort('Failed reading .env');

    $lines = preg_split('/\r\n|\n|\r/', $contents);
    $out = [];
    $sawDb = false; $sawBase = false;
    foreach ($lines as $line) {
        if (preg_match('/^\s*database\.default\.database\s*=\s*/i', $line)) {
            $out[] = 'database.default.database = ' . $targetDb;
            $sawDb = true;
        } elseif ($baseUrl !== null && preg_match('/^\s*app\.baseURL\s*=\s*/i', $line)) {
            $out[] = 'app.baseURL = ' . $baseUrl;
            $sawBase = true;
        } else {
            $out[] = $line;
        }
    }
    if (!$sawDb) $out[] = 'database.default.database = ' . $targetDb;
    if ($baseUrl !== null && !$sawBase) $out[] = 'app.baseURL = ' . $baseUrl;

    $new = implode(PHP_EOL, $out) . PHP_EOL;
    if (file_put_contents($envPath, $new) === false) abort('Failed writing .env');
    info('Updated .env with new database' . ($baseUrl ? ' and baseURL' : ''));
}

// -------- Main flow --------
$projectRoot = __DIR__;
$source = sanitize_db(argval('source'));
$target = sanitize_db(argval('target', 'corelynk_db'));
$user = argval('user', 'root');
$noPass = true; // per requirement, root without password
$baseUrl = argval('baseurl', null);
$dryRun = (bool) argval('dry-run', false);
$mysqlBin = rtrim((string) argval('mysql-bin', ''), "\\/");

if (!$source) {
    abort("--source is required. Example: --source=old_db");
}
if (!$target) {
    abort("--target is required. Example: --target=corelynk_db");
}

// Determine executables
$mysqlExe = find_executable([
    $mysqlBin ? $mysqlBin . DIRECTORY_SEPARATOR . 'mysql.exe' : null,
    'C:\\xampp\\mysql\\bin\\mysql.exe',
    'mysql'
]);
$dumpExe = find_executable([
    $mysqlBin ? $mysqlBin . DIRECTORY_SEPARATOR . 'mysqldump.exe' : null,
    'C:\\xampp\\mysql\\bin\\mysqldump.exe',
    'mysqldump'
]);
if (!$mysqlExe || !$dumpExe) {
    abort('Could not locate mysql.exe and/or mysqldump.exe. Provide --mysql-bin=C:\\xampp\\mysql\\bin');
}
info("mysql: $mysqlExe");
info("mysqldump: $dumpExe");

// Make temp folder for dumps
$tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'corelynk_migrate_' . getmypid();
if (!is_dir($tmpDir) && !mkdir($tmpDir, 0777, true)) {
    abort('Failed to create temp dir: ' . $tmpDir);
}
$dumpFile = $tmpDir . DIRECTORY_SEPARATOR . $source . '_dump.sql';

info("Source DB: $source");
info("Target DB: $target");
if ($baseUrl) info("New baseURL: $baseUrl");
if ($dryRun) warn('Dry-run mode: no changes will be applied');

// 1) Create target DB
$q = function(string $s){ return '"' . str_replace('"', '\\"', $s) . '"'; };
$mysqlQ = $q($mysqlExe);
$dumpQ  = $q($dumpExe);
$dumpFileQ = $q($dumpFile);

// Build commands with Windows-friendly double-quote quoting (avoid escapeshellarg which uses single quotes)
$createCmd = $mysqlQ . ' -u' . $user . ($noPass ? '' : ' -p') . ' -e ' . $q('CREATE DATABASE IF NOT EXISTS `' . $target . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');
if ($dryRun) {
    info('[dry-run] ' . $createCmd);
} else {
    [$code] = run_cmd($createCmd);
    if ($code !== 0) abort('Failed to create target database');
}

// 2) Dump source DB
$dumpCmd = $dumpQ . ' -u' . $user . ($noPass ? '' : ' -p') . ' --routines --triggers --events ' . $source . ' > ' . $dumpFileQ;
if ($dryRun) {
    info('[dry-run] ' . $dumpCmd);
} else {
    // For Windows, exec redirection in PHP works when passed to cmd; use shell
    $cmd = $dumpCmd;
    if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
        $cmd = 'cmd /c ' . $dumpCmd;
    }
    [$code] = run_cmd($cmd, true);
    if ($code !== 0 || !file_exists($dumpFile) || filesize($dumpFile) === 0) {
        abort('mysqldump failed or produced empty dump');
    }
    info('Dump saved to ' . $dumpFile);
}

// 3) Import into target DB
$importCmd = $mysqlQ . ' -u' . $user . ($noPass ? '' : ' -p') . ' ' . $target . ' < ' . $dumpFileQ;
if ($dryRun) {
    info('[dry-run] ' . $importCmd);
} else {
    $cmd = $importCmd;
    if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
        $cmd = 'cmd /c ' . $importCmd;
    }
    [$code] = run_cmd($cmd, true);
    if ($code !== 0) abort('Import failed');
    info('Import completed');
}

// 4) Update .env
if ($dryRun) {
    info('[dry-run] Would update .env in ' . $projectRoot . ' to use database ' . $target . ($baseUrl ? ' and baseURL ' . $baseUrl : ''));
} else {
    update_env_file($projectRoot, $target, $baseUrl);
}

info('Done.');

// Cleanup temp
if (!$dryRun && is_dir($tmpDir)) {
    // Keep dump for inspection; uncomment to remove
    // array_map('unlink', glob($tmpDir . DIRECTORY_SEPARATOR . '*'));
    // rmdir($tmpDir);
}
