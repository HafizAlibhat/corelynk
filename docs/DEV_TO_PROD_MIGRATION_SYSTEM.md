# Dev to Production Migration System

## Goal

Create a repeatable, low-risk, future-proof way to move approved changes from:

- development app: `C:\xampp\htdocs\corelynk_dev`
- development DB: `corelynk_db_dev`

into:

- production app: `C:\xampp\htdocs\corelynk`
- production DB: `corelynk_db`

without disturbing production data.

## Core Principles

1. Production data is authoritative.
2. Development is a source of code changes, not a source of transactional truth.
3. Schema changes move by forward-only migrations.
4. Environment files never move by folder copy.
5. Runtime artifacts never move by folder copy.
6. Every deployment starts with automated audit plus automated backup.
7. Every module promotion has a rollback artifact before release.

## What Must Never Be Copied Blindly

- `.env`
- cookie/session path config that is environment-bound
- `writable/*`
- `public/uploads/*`
- `vendor/*` only if package state is controlled by lock file and reinstall, otherwise capture it in backup but do not use folder drift as the deployment mechanism
- production transactional rows from customers, quotations, orders, payments, inventory, etc.

## Deployment Model

Use a four-layer model.

### Layer 1: Audit

Before every release, run an automated audit that answers:

- Which code files changed?
- Which tables differ?
- Which columns differ?
- Which counts drift in critical business tables?
- Are there environment-specific files in the change set?
- Are there blockers in development code?

Script:

- `tools/deployment/Audit-CorelynkEnvironments.ps1`

### Layer 2: Backup

Before every release, create a restore-ready backup package of production.

Minimum required artifacts:

- full production SQL dump
- zipped production app snapshot
- manifest with timestamps, DB name, app path, and archive names

Script:

- `tools/deployment/Backup-CorelynkEnvironment.ps1`

### Layer 3: Promotion Preparation

Promotion is prepared in three streams.

#### Code stream

- review audited changed files
- exclude environment-specific files
- exclude runtime files
- fix development blockers before promotion
- stage only approved code files

#### Schema stream

- convert approved schema drift into CodeIgniter migrations or explicit reviewed SQL migrations
- migrations must be additive and idempotent when practical
- never rely on manual phpMyAdmin editing as the permanent deployment mechanism

#### Data stream

Default rule:

- do not migrate transactional data from development to production

Allowed only under explicit review:

- reference/master data
- seed/config rows
- repair/backfill rows with a deterministic script

### Layer 4: Release and Validation

Release order:

1. backup production
2. deploy approved code files
3. run migrations on production
4. clear application cache if needed
5. smoke test critical user paths
6. verify logs

## Recommended Release Workflow

### Standard Workflow

1. Run environment audit.
2. Review the generated Markdown report.
3. Build a release manifest listing approved code files, migrations, and any controlled data scripts.
4. Run production backup.
5. Put production into a quiet window if schema changes are risky.
6. Copy approved code files only.
7. Run `php spark migrate` or approved SQL migration scripts.
8. Run smoke tests.
9. Archive the backup and audit report together.

### Smoke Test Minimum Set

- login
- dashboard load
- customers search
- quotations list and detail
- sales orders list and detail
- purchases list and detail
- any module touched by the release

## Recommended Rules For This Repository

### Rule 1: Treat customs invoices as a separate release track

The customs-invoice module has significant schema and code divergence. It should be promoted as a module release with:

- dedicated schema migrations
- dedicated smoke tests
- dedicated rollback plan

### Rule 2: Keep production-only safety columns

Production currently contains soft-delete columns and audit structures that development does not always have. These are not optional regressions. They must be part of the approved target schema.

### Rule 3: Never deploy `.env` by copy

Instead, maintain environment-owned values separately.

Recommended pattern:

- keep `.env` per environment
- if needed, maintain a `.env.example` or deployment checklist, not a shared live `.env`

### Rule 4: Separate code backup from rollback decision

Always create the backup package first. Decide rollback only if:

- release smoke tests fail
- production errors spike
- migration causes functional regression

## Suggested Future Folder Layout

Inside production repo:

- `tools/deployment/Audit-CorelynkEnvironments.ps1`
- `tools/deployment/Backup-CorelynkEnvironment.ps1`
- `docs/DEV_PROD_AUDIT_YYYY-MM-DD.md`
- `docs/DEV_TO_PROD_MIGRATION_SYSTEM.md`
- `database/migrations/*` for forward schema changes
- `database/fixes/*` for one-off backfills with review notes

## Release Artifact Strategy

For each production release, keep:

- audit report
- backup zip
- SQL dump
- release manifest
- migration files applied
- smoke-test notes

Suggested naming:

- `corelynk_release_YYYYMMDD_HHMM`
- `corelynk_prod_backup_YYYYMMDD_HHMM.zip`
- `corelynk_db_YYYYMMDD_HHMM.sql`

## Safest Backup Option

### CLI-first backup

Safest immediate implementation is CLI-driven and admin-run.

Why:

- avoids HTTP timeout issues
- avoids browser memory limits
- keeps large DB dumps outside web request lifecycle
- easier to schedule and log

### Future web backup option

If you want a backend button later, the safest design is:

1. admin-only controller/action
2. starts a background backup job
3. writes SQL dump and code archive into a non-public backup directory
4. records status in a backup jobs table
5. exposes a signed one-time download link when ready
6. auto-expires old artifacts

Do not directly stream a full DB dump plus full code zip from one live request unless the archive is already prepared.

## Future-Proof Deployment Policy

For every future request of "move dev updates to production", use this exact order:

1. run audit script
2. classify differences into code, schema, data, runtime, environment
3. produce a release manifest
4. run backup script on production
5. fix any dev blockers before promotion
6. deploy code only for approved files
7. run migrations and controlled backfills
8. run smoke tests
9. archive audit plus backup plus manifest

## Current Recommendation Based On 2026-05-13 Audit

Do not do a blanket dev-to-prod sync.

Instead:

1. promote only approved code deltas
2. treat customs invoices as a dedicated migration project
3. preserve production-only audit and soft-delete structures
4. avoid moving dev transactional rows into production

## Next Implementation Step

When you are ready to actually move approved changes, the next task should be:

- generate the release manifest for the first module promotion

For the current audit, that likely means customs invoices first, with a schema-first rollout.