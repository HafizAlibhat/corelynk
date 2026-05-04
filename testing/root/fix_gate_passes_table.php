<?php
// Fix or upgrade the gate_passes table to match the current application model
// - Adds missing columns (vendor_id, purpose, items, status, expected_date, actual_date, notes, remarks,
//   created_by, completed_by, updated_at)
// - Renames `number` -> `gate_pass_number`
// - Normalizes `type` values from 'in'/'out' to 'incoming'/'outgoing' and widens type to VARCHAR
// Safe to run multiple times.

$cfg = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => '',
    'db'   => 'production_management_system',
];

function out($msg) { echo $msg . "\n"; }

$mysqli = @new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['db']);
if ($mysqli->connect_error) {
    die("DB connection failed: {$mysqli->connect_error}\n");
}

out("Connected to DB: {$cfg['db']}");

// Helper: check column exists
function colExists(mysqli $db, string $table, string $column): bool {
    $like = $db->real_escape_string($column);
    $sql = "SHOW COLUMNS FROM `{$table}` LIKE '{$like}'";
    if (!$res = $db->query($sql)) {
        return false;
    }
    $ok = $res->num_rows > 0;
    $res->close();
    return $ok;
}

// Helper: get column type
function colType(mysqli $db, string $table, string $column): ?string {
    $like = $db->real_escape_string($column);
    $sql = "SHOW COLUMNS FROM `{$table}` LIKE '{$like}'";
    if (!$res = $db->query($sql)) {
        return null;
    }
    $type = null;
    if ($row = $res->fetch_assoc()) {
        $type = $row['Type'] ?? null;
    }
    $res->close();
    return $type;
}

// Ensure table exists
$res = $mysqli->query("SHOW TABLES LIKE 'gate_passes'");
if (!$res || $res->num_rows === 0) {
    out("gate_passes not found, creating new modern table...");
    $sql = "CREATE TABLE `gate_passes` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `gate_pass_number` VARCHAR(100) NOT NULL,
        `type` VARCHAR(20) NOT NULL,
        `vendor_id` INT NULL,
        `purpose` TEXT NULL,
        `items` TEXT NULL,
        `status` VARCHAR(20) DEFAULT 'pending',
        `expected_date` DATETIME NULL,
        `actual_date` DATETIME NULL,
        `notes` TEXT NULL,
        `remarks` TEXT NULL,
        `created_by` INT NULL,
        `completed_by` INT NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    if (!$mysqli->query($sql)) {
        die("Failed creating gate_passes: {$mysqli->error}\n");
    }
    out("Created gate_passes table.");
} else {
    out("gate_passes table exists, applying incremental fixes...");

    // Rename `number` -> `gate_pass_number`
    if (colExists($mysqli, 'gate_passes', 'number') && !colExists($mysqli, 'gate_passes', 'gate_pass_number')) {
        $type = colType($mysqli, 'gate_passes', 'number') ?: 'VARCHAR(100)';
        $sql = "ALTER TABLE `gate_passes` CHANGE `number` `gate_pass_number` $type NOT NULL";
        if (!$mysqli->query($sql)) {
            out("WARN: rename number->gate_pass_number failed: {$mysqli->error}");
        } else {
            out("Renamed column number -> gate_pass_number");
        }
    }

    // Ensure required columns exist
    $adds = [];
    if (!colExists($mysqli, 'gate_passes', 'recipient_type')) $adds[] = "ADD COLUMN `recipient_type` VARCHAR(20) NULL DEFAULT 'vendor' AFTER `type`";
    if (!colExists($mysqli, 'gate_passes', 'recipient_name')) $adds[] = "ADD COLUMN `recipient_name` VARCHAR(150) NULL AFTER `recipient_type`";
    if (!colExists($mysqli, 'gate_passes', 'vendor_id')) $adds[] = "ADD COLUMN `vendor_id` INT NULL AFTER `recipient_name`";
    if (!colExists($mysqli, 'gate_passes', 'purpose')) $adds[] = "ADD COLUMN `purpose` TEXT NULL AFTER `vendor_id`";
    if (!colExists($mysqli, 'gate_passes', 'items')) $adds[] = "ADD COLUMN `items` TEXT NULL AFTER `purpose`";
    if (!colExists($mysqli, 'gate_passes', 'status')) $adds[] = "ADD COLUMN `status` VARCHAR(20) DEFAULT 'pending' AFTER `items`";
    if (!colExists($mysqli, 'gate_passes', 'expected_date')) $adds[] = "ADD COLUMN `expected_date` DATETIME NULL AFTER `status`";
    if (!colExists($mysqli, 'gate_passes', 'actual_date')) $adds[] = "ADD COLUMN `actual_date` DATETIME NULL AFTER `expected_date`";
    if (!colExists($mysqli, 'gate_passes', 'notes')) $adds[] = "ADD COLUMN `notes` TEXT NULL AFTER `actual_date`";
    if (!colExists($mysqli, 'gate_passes', 'remarks')) $adds[] = "ADD COLUMN `remarks` TEXT NULL AFTER `notes`";
    if (!colExists($mysqli, 'gate_passes', 'created_by')) $adds[] = "ADD COLUMN `created_by` INT NULL AFTER `remarks`";
    if (!colExists($mysqli, 'gate_passes', 'completed_by')) $adds[] = "ADD COLUMN `completed_by` INT NULL AFTER `created_by`";
    if (!colExists($mysqli, 'gate_passes', 'updated_at')) $adds[] = "ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`";

    if (!empty($adds)) {
        $sql = "ALTER TABLE `gate_passes` " . implode(", ", $adds);
        if (!$mysqli->query($sql)) {
            out("WARN: alter table add columns failed: {$mysqli->error}");
        } else {
            out("Added missing columns: " . implode(', ', array_map(function($a){return preg_replace('/^ADD COLUMN `([^`]+)`.*/','$1',$a);}, $adds)));
        }
    } else {
        out("No column additions required.");
    }

    // Normalize type column to VARCHAR(20) and values
    $typeDef = colType($mysqli, 'gate_passes', 'type');
    if ($typeDef && stripos($typeDef, "enum('in','out')") !== false) {
        out("Normalizing type column enum('in','out') -> VARCHAR(20) with values 'incoming'/'outgoing'");
        if (!$mysqli->query("ALTER TABLE `gate_passes` MODIFY `type` VARCHAR(20) NOT NULL")) {
            out("WARN: modify type column failed: {$mysqli->error}");
        }
        // Map existing values
        if (!$mysqli->query("UPDATE `gate_passes` SET `type` = 'incoming' WHERE `type` = 'in'")) {
            out("WARN: update in->incoming failed: {$mysqli->error}");
        }
        if (!$mysqli->query("UPDATE `gate_passes` SET `type` = 'outgoing' WHERE `type` = 'out'")) {
            out("WARN: update out->outgoing failed: {$mysqli->error}");
        }
    }
}

out("Done. You can now reload /gate_passes in the app.");
$mysqli->close();
