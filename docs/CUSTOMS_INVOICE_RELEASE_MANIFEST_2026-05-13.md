# Customs Invoice Release Manifest

Date: 2026-05-13

Source environment:

- App: `C:\xampp\htdocs\corelynk_dev`
- DB: `corelynk_db_dev`

Target environment:

- App: `C:\xampp\htdocs\corelynk`
- DB: `corelynk_db`

## Release Intent

Promote the approved customs-invoice enhancements from development to production without copying development transactional data and without disturbing production business records.

This is a module promotion, not a full environment sync.

## Current Status

### Blocker Fix Applied In Development

Fixed in development:

- `app/Controllers/CustomsInvoices.php`

Change made:

- `approvalPortal($uuid = null)` corrected to `approvalPortal($token = null)`

Reason:

- public route `customs-approval/(:segment)` passes a token, and the method body already consumes a token
- previous dev implementation used an undefined `$token` variable under a `$uuid` parameter name

Validation completed:

- PHP syntax check passed on the touched controller

## Approved Code Scope

These are the primary application files in scope for customs-invoice promotion:

- `app/Config/Routes.php`
- `app/Controllers/CustomsInvoices.php`
- `app/Services/CustomsInvoiceService.php`
- `app/Views/customs_invoices/workspace.php`
- `app/Views/pdf/customs_invoice.php`

Customs-invoice model dependencies present in development:

- `app/Models/CustomsInvoiceModel.php`
- `app/Models/CustomsInvoiceVersionModel.php`
- `app/Models/CustomsInvoiceItemModel.php`
- `app/Models/CustomsInvoiceFileModel.php`
- `app/Models/CustomsInvoiceAuditLogModel.php`
- `app/Models/CustomsInvoiceApprovalModel.php`

## Approved Feature Additions In Development

### Routes

Additional customs-invoice routes introduced in development:

- `POST customs-invoices/(:segment)/upload-documents`
- `GET customs-invoices/(:segment)/documents`
- `GET customs-invoices/(:segment)/documents/(:num)/download`

Public approval routes already exist and use token flow:

- `GET customs-approval/(:segment)`
- `POST customs-approval/(:segment)/decision`

### Controller Capabilities

Development customs-invoice controller includes:

- document upload endpoint
- document listing endpoint
- document download endpoint
- token-based approval portal flow

### Service Capabilities

Development customs-invoice service includes:

- template field extraction from version snapshot
- template field normalization
- default template field generation
- richer snapshot payload for PDF/document rendering

### Workspace UI Capabilities

Development workspace adds:

- line delete actions
- richer line editing controls
- template fields editor
- total box count and document metadata input
- upload-documents action

### PDF Capabilities

Development PDF adds:

- dynamic document title
- commercial invoice number
- consignee section
- declaration text
- bank details
- authorized signatory section

## Schema Reality

### Important Constraint

The customs-invoice create-table migrations already exist in both dev and prod repositories:

- `app/Database/Migrations/2026-05-12-000201_CreateCustomsInvoiceTables.php`
- `app/Database/Migrations/2026-05-12-000202_AddCustomsInvoicePermissions.php`

But production schema is still older and materially different.

Why this matters:

- the create-table migration uses `tableExists()` guards and `createTable(..., true)`
- that migration will not reconcile already-existing older production customs tables
- therefore, existing migrations are not enough to bring production to the development schema

### Required Before Promotion

Use the new forward-only reconciliation migration for production to align:

- `app/Database/Migrations/2026-05-13-130000_ReconcileCustomsInvoiceSchema.php`

It is designed to:

- do nothing if the target customs schema signature is already present
- recreate the customs tables only when legacy customs tables exist but are empty
- stop with an exception if any customs table contains data, forcing a manual path instead of risking data loss

It aligns:

- `customs_invoices`
- `customs_invoice_versions`
- `customs_invoice_items`
- `customs_invoice_audit_logs`
- `customs_invoice_approvals`
- `customs_invoice_files`

This reconciliation migration must:

- add missing columns
- adjust incompatible defaults/nullability only when safe
- preserve existing production rows
- avoid destructive drops in the first rollout
- preserve production business continuity

## Data Policy

Do not migrate development transactional rows into production.

Specifically do not copy from `corelynk_db_dev` into `corelynk_db`:

- `customs_invoices`
- `customs_invoice_items`
- `customers`
- `quotations`
- `sales_orders`
- `purchase_orders`
- any transactional or user-generated rows

Only schema and code should be promoted in this release.

If sample or reference rows are ever required, they must be migrated via explicit reviewed seed/backfill scripts.

## Files Excluded From This Release

Do not deploy these by copy:

- `.env`
- `app/Config/Cookie.php`
- `writable/*`
- `public/uploads/*`
- runtime-generated customs document PDFs

## Proposed Release Sequence

### Phase 1: Preparation

1. Run audit script.
2. Run production backup script.
3. Review schema differences specifically for customs tables.
4. Author a new reconciliation migration.

### Phase 2: Schema First

1. Deploy reconciliation migration to production codebase.
2. Run migration against `corelynk_db`.
3. Verify resulting schema matches expected dev-side contract.

### Phase 3: Code Promotion

Deploy approved customs-invoice code files only.

### Phase 4: Validation

Run smoke tests:

- customs invoice workspace opens
- draft save works
- approval submission works
- public approval portal opens with token
- approval decision submits successfully
- preview PDF generates
- final PDF generates
- upload documents works
- document listing works
- document download works

## Release Risks

### High Risk

- customs schema mismatch between environments
- production has older customs tables already in place

### Medium Risk

- document upload path permissions in production writable directory
- permissions/role records required for customs-invoice access

### Low Risk

- route additions if schema is already aligned

## Rollback Plan

Before release:

1. create production SQL dump
2. create production code archive zip
3. retain release manifest with timestamps

If release fails:

1. revert promoted code files
2. restore production DB from dump only if schema/data failure requires it
3. restore code archive if file rollback is not sufficient

## Not In Scope For This Release

- syncing development business data to production
- environment file replacement
- blanket folder sync
- promotion of unrelated module differences

## Next Implementation Artifact Required

The next concrete artifact should be:

- `app/Database/Migrations/<timestamp>_ReconcileCustomsInvoiceSchema.php`

That migration should align production customs tables to the development customs-invoice contract without dropping live production data.