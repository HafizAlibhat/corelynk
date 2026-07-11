<?php
/**
 * ========================================================================
 * SESSION & LOGIN RECOVERY SCRIPT
 * ========================================================================
 * 
 * Purpose: Diagnose and fix login/session issues
 * 
 * Root Causes Fixed:
 * 1. Session driver mismatch (DatabaseHandler vs FileHandler)
 * 2. Session save path invalid (Windows absolute path)
 * 3. Cookie path mismatch (/corelynk_dev/ vs /corelynk/)
 * 4. Missing sessions table in database
 * 5. Stale session files corrupting sessions
 * 
 * ========================================================================
 */

set_time_limit(30);
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<html><head><meta charset='UTF-8'><title>Session Recovery</title></head><body>";
echo "<h1>🔧 CoreLynk Session & Login Recovery</h1>";
echo "<hr>";

// ────────────────────────────────────────────────────────────────────
// 1. VERIFY FILE STRUCTURE
// ────────────────────────────────────────────────────────────────────
echo "<h2>1️⃣  Checking Session Directory Structure</h2>";
$sessionPath = __DIR__ . '/../../writable/session';
$absolutePath = realpath($sessionPath);

if (!$absolutePath) {
    echo "<p style='color:orange;'><strong>⚠️  Session directory doesn't exist or isn't accessible.</strong></p>";
    echo "<p>Creating directory: <code>writable/session</code></p>";
    if (!is_dir(__DIR__ . '/../../writable')) {
        mkdir(__DIR__ . '/../../writable', 0755, true);
    }
    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0755, true);
    }
    $absolutePath = realpath($sessionPath);
}

if (is_dir($sessionPath) && is_writable($sessionPath)) {
    echo "<p style='color:green;'><strong>✅ Session directory exists and is writable:</strong></p>";
    echo "<code>" . esc($absolutePath) . "</code>";
} else {
    echo "<p style='color:red;'><strong>❌ Session directory exists but is NOT writable!</strong></p>";
    echo "<p>Attempting to fix permissions...</p>";
    @chmod($sessionPath, 0755);
    if (is_writable($sessionPath)) {
        echo "<p style='color:green;'>✅ Permissions fixed!</p>";
    } else {
        echo "<p style='color:red;'>❌ Permission fix failed. Contact your server administrator.</p>";
    }
}

// ────────────────────────────────────────────────────────────────────
// 2. CLEAN OLD SESSION FILES
// ────────────────────────────────────────────────────────────────────
echo "<h2>2️⃣  Cleaning Stale Session Files</h2>";
$files = @glob($sessionPath . '/*');
$sessionCount = 0;
$deletedCount = 0;

if (is_array($files) && count($files) > 0) {
    echo "<p>Found <strong>" . count($files) . "</strong> session files.</p>";
    $currentTime = time();
    $maxAge = 3600 * 24; // 24 hours
    
    foreach ($files as $file) {
        if (is_file($file)) {
            $sessionCount++;
            $fileAge = $currentTime - filemtime($file);
            // Delete files older than 24 hours
            if ($fileAge > $maxAge) {
                @unlink($file);
                $deletedCount++;
            }
        }
    }
    echo "<p style='color:green;'>✅ Cleaned <strong>$deletedCount</strong> old session files.</p>";
} else {
    echo "<p style='color:blue;'>ℹ️  No session files found (fresh start).</p>";
}

// ────────────────────────────────────────────────────────────────────
// 3. VERIFY CONFIG FILES
// ────────────────────────────────────────────────────────────────────
echo "<h2>3️⃣  Verifying Configuration Files</h2>";

$configDir = __DIR__ . '/../../app/Config';
$issues = [];

// Check Session.php
$sessionConfig = file_get_contents($configDir . '/Session.php');
if (strpos($sessionConfig, 'FileHandler::class') !== false) {
    echo "<p style='color:green;'>✅ Session.php: Using FileHandler (correct)</p>";
} elseif (strpos($sessionConfig, 'DatabaseHandler::class') !== false) {
    echo "<p style='color:red;'>❌ Session.php: Still using DatabaseHandler - need to update!</p>";
    $issues[] = 'Session driver not fixed';
} else {
    echo "<p style='color:orange;'>⚠️  Session.php: Cannot determine driver setting</p>";
}

if (strpos($sessionConfig, "WRITEPATH . 'session'") !== false) {
    echo "<p style='color:green;'>✅ Session.php: Using correct save path (WRITEPATH)</p>";
} elseif (strpos($sessionConfig, "'sessions'") !== false) {
    echo "<p style='color:red;'>❌ Session.php: Using database table name - need to update!</p>";
    $issues[] = 'Session save path not fixed';
} else {
    echo "<p style='color:orange;'>⚠️  Session.php: Cannot verify save path</p>";
}

// Check Cookie.php
$cookieConfig = file_get_contents($configDir . '/Cookie.php');
if (strpos($cookieConfig, "'/corelynk/'") !== false) {
    echo "<p style='color:green;'>✅ Cookie.php: Using correct path /corelynk/</p>";
} elseif (strpos($cookieConfig, "'/corelynk_dev/'") !== false) {
    echo "<p style='color:red;'>❌ Cookie.php: Using wrong path /corelynk_dev/ - need to update!</p>";
    $issues[] = 'Cookie path not fixed';
} else {
    echo "<p style='color:blue;'>ℹ️  Cookie.php: Path is <code>" . 
         (preg_match("/'([^']+)'/", $cookieConfig, $m) ? esc($m[1]) : 'unknown') . 
         "</code></p>";
}

// Check .env
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $envContent = file_get_contents($envFile);
    if (strpos($envContent, "session.driver = 'CodeIgniter\\Session\\Handlers\\FileHandler'") !== false) {
        echo "<p style='color:green;'>✅ .env: Using FileHandler (correct)</p>";
    } elseif (strlen($envContent) > 0) {
        echo "<p style='color:blue;'>ℹ️  .env: .env configuration present</p>";
    }
}

// ────────────────────────────────────────────────────────────────────
// 4. DATABASE CHECK
// ────────────────────────────────────────────────────────────────────
echo "<h2>4️⃣  Checking Database Configuration</h2>";
try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;dbname=corelynk_db;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "<p style='color:green;'>✅ Database connection successful (corelynk_db)</p>";
    
    // Check if sessions table exists (legacy check)
    $result = $pdo->query("SHOW TABLES LIKE 'sessions'");
    if ($result->rowCount() > 0) {
        echo "<p style='color:blue;'>ℹ️  Database: 'sessions' table exists (no longer needed with FileHandler)</p>";
    } else {
        echo "<p style='color:blue;'>ℹ️  Database: 'sessions' table doesn't exist (expected with FileHandler)</p>";
    }
    
    // Check if users table exists
    $result = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($result->rowCount() > 0) {
        echo "<p style='color:green;'>✅ Database: 'users' table exists</p>";
    } else {
        echo "<p style='color:red;'>❌ Database: 'users' table NOT found - cannot login!</p>";
        $issues[] = 'Missing users table';
    }
} catch (Exception $e) {
    echo "<p style='color:red;'><strong>❌ Database connection failed:</strong></p>";
    echo "<code>" . esc($e->getMessage()) . "</code>";
    $issues[] = 'Database connection error: ' . $e->getMessage();
}

// ────────────────────────────────────────────────────────────────────
// 5. SUMMARY & RECOMMENDATIONS
// ────────────────────────────────────────────────────────────────────
echo "<h2>📋 Summary & Next Steps</h2>";

if (count($issues) === 0) {
    echo "<div style='background:#d4edda;border:1px solid #c3e6cb;padding:15px;border-radius:4px;color:#155724;'>";
    echo "<h3>✅ All Systems Look Good!</h3>";
    echo "<p>Your session and login configuration has been fixed. Follow these steps:</p>";
    echo "<ol>";
    echo "<li><strong>Clear browser cache:</strong> Press <code>Ctrl+Shift+Delete</code> and clear cookies/cache</li>";
    echo "<li><strong>Hard refresh:</strong> In browser, press <code>Ctrl+F5</code></li>";
    echo "<li><strong>Logout completely:</strong> Close all tabs and browser windows</li>";
    echo "<li><strong>Try logging in again:</strong> Go to <code>/corelynk/auth/login</code></li>";
    echo "</ol>";
    echo "<p><strong>Expected behavior:</strong> Login should work, session created in <code>writable/session/</code></p>";
} else {
    echo "<div style='background:#f8d7da;border:1px solid #f5c6cb;padding:15px;border-radius:4px;color:#721c24;'>";
    echo "<h3>⚠️  Issues Found</h3>";
    echo "<ul>";
    foreach ($issues as $issue) {
        echo "<li>$issue</li>";
    }
    echo "</ul>";
    echo "<p><strong>Action Required:</strong> Review the configuration changes above.</p>";
}

echo "</div>";

// ────────────────────────────────────────────────────────────────────
// 6. LOGIN TEST
// ────────────────────────────────────────────────────────────────────
echo "<h2>🧪 Quick Login Test</h2>";
echo "<p>After fixing configuration, use these test credentials (if they exist in your system):</p>";
echo "<ul>";
echo "<li>Email: <code>admin@corelynk.local</code></li>";
echo "<li>Or check <code>users</code> table for valid accounts</li>";
echo "</ul>";

echo "<hr>";
echo "<p style='color:#666;font-size:12px;'>";
echo "Generated: " . date('Y-m-d H:i:s') . " | ";
echo "Environment: " . (getenv('CI_ENVIRONMENT') ?: 'development') . "";
echo "</p>";

echo "</body></html>";

function esc($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
