# GRN Data Loss Troubleshooting Guide

## Problem Identification

**What's Happening:**
1. Application shows success message: "GRN created. Extra quantity recorded as free replacement (no payable bill created)."
2. BUT data doesn't appear in database when checked

**Root Cause:**
MySQL is **crashing silently** after the application commits GRN data. The application receives a success confirmation, but MySQL crashes before fully persisting the data, resulting in data loss.

---

## MySQL Stability Issue - April 17 Recovery

Due to the April 17 database recovery process, MySQL requires special startup parameters:

### Current Configuration
**File:** `C:\xampp\mysql\bin\my.ini`

```ini
[mysqld]
innodb_force_recovery=1
skip-grant-tables
skip-name-resolve
skip-symbolic-links
skip-external-locking
datadir=c:/xampp/mysql/data
log_error=c:/xampp/mysql/data/error.log
bind-address=127.0.0.1
port=3306
max_connections=200
max_allowed_packet=16M
thread_cache_size=8
sort_buffer_size=64K
bulk_insert_buffer_size=16M
```

**Key Settings:**
- `innodb_force_recovery=1` - Allows reading April 17 tables with LSN mismatch
- `skip-grant-tables` - Prevents crash when loading corrupted privilege tables
- Other settings - Prevent concurrent access issues

---

## Fixing GRN Data Loss

### Step 1: Start MySQL Properly

**Option A: Batch Script (Simplest)**
```batch
C:\xampp\mysql\start_mysql_recovery.bat
```

**Option B: PowerShell Manual**
```powershell
taskkill /F /IM mysqld.exe /T 2>&1 | Out-Null
Start-Sleep -Seconds 3
Start-Process -FilePath "C:\xampp\mysql\bin\mysqld.exe" -WindowStyle Hidden
Start-Sleep -Seconds 12
```

### Step 2: Verify MySQL is Running
```cmd
C:\xampp\mysql\bin\mysql.exe -u root -e "SELECT 'OK' as Status;"
```

Expected output: Shows connection OK

### Step 3: Recover Lost GRN Data

All GRN data that was supposedly created but missing from database is in the **April 17 backup**. Use this script to restore it:

```bash
# PHP script to restore GRN data from April 17 backup files
# Location: C:\xampp\htdocs\corelynk\grn_diagnostic.php

# Run via web:
# http://localhost/corelynk/grn_diagnostic.php
```

---

## Prevent Future Data Loss

### Edit Application Code

**File:** `app/Controllers/NewPurchaseGrns.php` (Around line 403)

Add error handling after transaction commit:

```php
// BEFORE (line 403):
$db->transCommit();

// AFTER - Add these checks:
// Verify MySQL connection still active after commit
if (!$db->connID) {
    // MySQL disconnected - warn about possible data loss
    log_message('critical', 'MySQL connection lost after GRN commit - possible data loss for GRN ' . $grnId);
    throw new \Exception('Database connection lost after commit - GRN creation uncertain');
}

// Verify GRN was actually saved
$verification = $db->table('purchase_grns')->where('id', $grnId)->countAllResults();
if ($verification === 0) {
    log_message('critical', 'Verification failed: GRN ' . $grnId . ' not found in database after insert');
    throw new \Exception('GRN insertion failed - data not persisted');
}
```

### Recommended Configuration Changes

**Add to my.ini for better reliability:**

```ini
[mysqld]
# Previous settings...
innodb_force_recovery=1
skip-grant-tables

# ADD THESE for recovery mode stability:
innodb_buffer_pool_size=256M
innodb_log_file_size=100M
max_allowed_packet=256M
connect_timeout=10
read_timeout=30
write_timeout=30
```

---

## Testing GRN Creation After Fix

### Test Script
```php
<?php
// Quick GRN save test
$mysqli = mysqli_connect('localhost', 'root', '', 'corelynk_db');
$before = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM purchase_grns"))[0];

// Simulate GRN creation via API or form...
// (Do your GRN creation)

sleep(2);
$after = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM purchase_grns"))[0];

if ($after > $before) {
    echo "✓ GRN DATA SAVED SUCCESSFULLY";
} else {
    echo "✗ GRN DATA WAS NOT SAVED - MySQL crash suspected";
}
?>
```

---

## Monitoring for Crashes

### Tail the Error Log
```bash
# Real-time error monitoring
Get-Content -Path "C:\xampp\mysql\data\DESKTOP-RFQRCCF.err" -Wait -Tail 50
```

### Watch for These Error Patterns
- "Page log sequence number is in the future"
- "Lost connection to MySQL server"  
- "MySQL connection closed"
- "General error"

---

## What's Actually Happening Inside

1. **User creates GRN** → Application sends INSERT to MySQL
2. **MySQL receives INSERT** → Data loaded into buffer 
3. **Application COMMITS transaction** → Asks MySQL to persist data
4. **MySQL CRASHES** (LSN recovery mode instability) → Data lost before fsync
5. **Application doesn't know** → No error returned, success message sent
6. **Result: Data loss** → GRN appears created but missing from database

---

## Long-term Solution

The April 17 LSN mismatch is a **permanent condition** of that backup set. To fully resolve this:

### Option 1: Accept Recovery Mode (Current)
- Keep running with `innodb_force_recovery=1`
- Risk: Data loss on sudden crashes
- Benefit: Access to April 17 data

### Option 2: Rebuild from April 16
- Go back to April 16 snapshot  
- Full stability, no recovery mode needed
- Cost: Lose April 17 updates (~15 hours of data)

### Option 3: Specialized Recovery Tool (Recommended)
- Use **Percona XtraBackup** to rebuild April 17 with proper LSN values
- Requires professional database recovery
- Gives you April 17 data + full stability

---

## Status Check Commands

```powershell
# Is MySQL running?
Get-Process mysqld

# Connection test
C:\xampp\mysql\bin\mysql.exe -u root -e "SELECT NOW() as Time, USER() as User;"

# Check GRN count
C:\xampp\mysql\bin\mysql.exe -u root corelynk_db -e "SELECT COUNT(*) as GRN_Count FROM purchase_grns;"

# Check specific GRN with extra qty
C:\xampp\mysql\bin\mysql.exe -u root corelynk_db -e "SELECT grn_id, COUNT(*) as Lines FROM purchase_grn_lines WHERE over_received_qty > 0 GROUP BY grn_id;"
```

---

## Next Steps

1. **Immediately:** Stop MySQL and start it using `start_mysql_recovery.bat`
2. **Verify:** Run connection test to confirm MySQL is stable
3. **Check:** Use diagnostic script to see if lost GRN data can be recovered from April 17 backup
4. **Prevent:** Add verification code to GRN creation controller
5. **Plan:** Consider Option 3 (Percona recovery) for permanent fix

---

**Document Created:** April 18, 2026
**Status:** April 17 Database Recovering - GRN Feature Active But At Risk
**Action Required:** Stabilize MySQL using recovery startup method
