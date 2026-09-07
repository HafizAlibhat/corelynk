#!/usr/bin/env bash
# Run this ON THE LIVE SERVER, from inside the deployed dst/ folder.
#   ./deploy.sh            -> backup DB, run pending migrations, fix perms, clear cache
#   ./deploy.sh --no-db    -> code only, skip the DB step
set -euo pipefail
cd "$(dirname "$0")"

PHP="${PHP:-php}"
WEBUSER="${WEBUSER:-www-data}"

[ -f .env ] || { echo "ERROR: .env missing. cp env.production.example .env and edit it."; exit 1; }

if [ "${1:-}" != "--no-db" ]; then
  DB=$(grep -E '^database.default.database' .env | cut -d= -f2 | tr -d " '\"")
  DBU=$(grep -E '^database.default.username' .env | cut -d= -f2 | tr -d " '\"")
  STAMP=$(date +%Y%m%d-%H%M%S)
  mkdir -p writable/backups
  echo ">> backing up $DB -> writable/backups/$DB-$STAMP.sql.gz"
  mysqldump -u "$DBU" -p --single-transaction --routines "$DB" | gzip > "writable/backups/$DB-$STAMP.sql.gz"

  echo ">> applying pending migrations (only new tables/columns are touched)"
  $PHP spark migrate --all
fi

echo ">> clearing caches"
$PHP spark cache:clear || true
rm -rf writable/cache/* writable/debugbar/* 2>/dev/null || true

echo ">> permissions"
chown -R "$WEBUSER":"$WEBUSER" writable public/uploads 2>/dev/null || true
find writable -type d -exec chmod 775 {} \;
find writable -type f -exec chmod 664 {} \;

echo ">> done"
