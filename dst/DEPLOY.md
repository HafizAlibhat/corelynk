# CoreLynk — Production Deployment Package (`dst/`)

Working code only. **No uploads, no images, no videos, no documents, no logs,
no sessions, no `.env`, no dev/debug/backup files.**

## What is inside

| Path | Notes |
|---|---|
| `app/` | Application code (controllers, models, views, migrations, seeds) |
| `public/` | Web root: `index.php`, `.htaccess`, `assets/`, `css/`, `js/` — **`uploads/` deliberately excluded** |
| `vendor/` | Composer dependencies, pre-installed — you do **not** need composer on the server |
| `writable/` | Empty skeleton folders only |
| `db/schema_reference.sql` | Structure-only dump (no data) — for comparison/repair |
| `db/migrations_table_local.sql` | Rows of the `migrations` table on dev — recovery aid, see below |
| `env.production.example` | `.env` template with the session/cookie bug already fixed |
| `deploy.sh` | Backup → migrate → clear cache → fix permissions |
| `index.php`, `.htaccess` | Root redirect into `public/` |

`public/uploads/` and `writable/uploads/` on the live server are **untouched** —
existing customer files stay where they are.

---

## 1. Push to GitHub (on Windows / dev machine)

```bash
git add dst
git commit -m "Production build"
git push
```

## 2. Pull on the Ubuntu server

```bash
cd /var/www/corelynk        # your app root
cp .env /root/corelynk.env.backup      # KEEP THE OLD .env
git pull

rsync -a --delete \
  --exclude 'writable/uploads' \
  --exclude 'writable/backups' \
  --exclude 'writable/logs' \
  --exclude 'writable/session' \
  --exclude 'public/uploads' \
  --exclude '.env' \
  /path/to/repo/dst/  /var/www/corelynk/
```

`--delete` removes stale old files but the `--exclude` lines protect your data
and your `.env`.

**Docker:** if the app runs in a container, do the `git pull` + `rsync` on the
host into the bind-mounted volume, then run step 4 inside the container
(`docker compose exec app ./deploy.sh`).

## 3. `.env` and the cookie problem

Your dev `.env` had:

```
session.savePath = '/writable/session'
```

The quotes made it a literal path, so PHP wrote sessions into
`public/C_/xampp/...` and `public/null/` instead of `writable/session`. Those
two junk folders are **not** in this package — delete them on the server too:

```bash
rm -rf public/C_ public/null
```

Then create the real `.env`:

```bash
cp env.production.example .env
nano .env
```

Must set:
- `app.baseURL` — your real domain, **with trailing slash**
- `database.default.*` — live DB credentials
- `encryption.key` — **copy the value from your OLD live `.env`**. A new key
  logs every user out and breaks anything already encrypted.
- Leave `session.savePath` **unset** (it is not in the template on purpose).
- `cookie.secure = true` only if you actually serve over HTTPS; otherwise set
  it to `false` or nobody can log in.

`.env` is gitignored — it never travels through GitHub. Keep it on the server.

## 4. Database update — only the new tables/columns

Everything is driven by CI4 migrations, so **nothing is dropped and no data is
overwritten**. Only migrations the live DB has not yet seen are executed.

```bash
cd /var/www/corelynk
./deploy.sh
```

Or manually:

```bash
mysqldump -u USER -p corelynk_db | gzip > backup.sql.gz   # always first
php spark migrate --all
php spark migrate:status        # verify everything shows as run
```

Latest migration in this build: `2026-09-07-000003_LinkEmployeesToUsers`.

### If `migrate` errors with "table/column already exists"

That means the live DB was built from an SQL dump, so its `migrations` table
does not know what was already applied. Fix by telling MySQL those migrations
are done, then re-run:

```bash
mysql -u USER -p corelynk_db < db/migrations_table_local.sql   # marks all as applied
php spark migrate --all
```

Compare live structure against `db/schema_reference.sql` first if you are unsure:

```bash
mysqldump -u USER -p --no-data --skip-comments corelynk_db > live_schema.sql
diff live_schema.sql db/schema_reference.sql
```

## 5. Apache / web root

Point the vhost `DocumentRoot` at `/var/www/corelynk/public` (preferred). The
root `.htaccess` rewrite into `public/` is only a fallback — it exposes the app
folder to the web server and is worse for security.

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /var/www/corelynk/public
    <Directory /var/www/corelynk/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

`a2enmod rewrite && systemctl restart apache2`

## 6. Post-deploy check

```bash
php spark migrate:status
tail -f writable/logs/log-$(date +%Y-%m-%d).php
```

Open the site, log in, open one Sales Order and one Invoice.

---

## Rebuilding this package

From the dev machine:

```bash
bash scripts/build-dist.sh
```

It wipes and regenerates `dst/` from the current working tree.
