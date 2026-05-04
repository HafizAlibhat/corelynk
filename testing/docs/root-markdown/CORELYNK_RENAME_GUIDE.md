# Corelynk Rename & Database Migration Guide (Windows/XAMPP)

This guide walks you through renaming the project folder to `corelynk` and migrating your MySQL database to `corelynk_db`. Downtime is okay per your approval.

## 1) Prepare a backup

- Export the current database (replace `old_db` with your current DB name from `.env`):

```powershell
# Open PowerShell in project root and run:
$timestamp = Get-Date -Format yyyyMMdd_HHmmss
$dumpFile = "backup_$timestamp.sql"
& "$env:MYSQL_HOME\\bin\\mysqldump.exe" --user=root --password=YOUR_PASSWORD old_db > $dumpFile
Write-Host "Backup saved: $dumpFile"
```

Notes:
- If `MYSQL_HOME` is not set, use the absolute path to `mysqldump.exe` inside your XAMPP install, e.g. `C:\xampp\mysql\bin\mysqldump.exe`.
- If root has no password, omit `--password`.

## 2) Create the new database and import

```powershell
# Create new DB
& "C:\\xampp\\mysql\\bin\\mysql.exe" -uroot -p -e "CREATE DATABASE IF NOT EXISTS corelynk_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Import from your existing DB (replace old_db)
& "C:\\xampp\\mysql\\bin\\mysqldump.exe" -uroot -p old_db > old_db_dump.sql
& "C:\\xampp\\mysql\\bin\\mysql.exe" -uroot -p corelynk_db < old_db_dump.sql
```

Alternative: If you already have `simple_schema.sql` and `insert_sample_data.php`, you can initialize an empty `corelynk_db` and re-seed.

## 3) Update environment config

- Duplicate `env` to `.env` if you don’t have one already.
- Edit `.env` and update the database name:

```
# Database
database.default.database = corelynk_db
```

- While you’re here, confirm the credentials and host/port are correct.

## 4) Rename the project folder

1. Stop Apache (and PHP dev server if running):
    - In XAMPP Control Panel, Stop Apache and MySQL.
2. Rename folder in Explorer:
    - From `C:\xampp\htdocs\pro_sys` to `C:\xampp\htdocs\corelynk`.
3. Update your Apache vhost / DocumentRoot mapping (if you use a vhost):
    - Point DocumentRoot to `C:/xampp/htdocs/corelynk/public` (CodeIgniter’s public webroot).
4. Start Apache and MySQL again.

## 5) Verify application

- Visit http://localhost/corelynk or your vhost.
- You should see the Corelynk landing page with links to Production and Accounting.
- Check a few Production pages (Dashboard, Work Orders) load without DB errors.

## 6) Optional cleanups

- Search codebase for hard-coded paths or DB names (should be none). If found, update them to be config-driven.
- Confirm `public/index.php` and `app/Config/App.php` baseURL if needed.

## Troubleshooting

- Access denied / wrong password: Verify root password flags (`-p` prompts) or user credentials in `.env`.
- Unknown database: Ensure `corelynk_db` exists and `.env` references it.
- 404 after rename: Ensure your web root points to `public/` and your base URL is correct in `.env`:

```
app.baseURL = 'http://localhost/corelynk/'
```

- PHP errors: Check `writable/logs/` for more detail.
