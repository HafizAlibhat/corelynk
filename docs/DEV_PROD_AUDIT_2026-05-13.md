# Dev vs Production Audit

Date: 2026-05-13

Scope:
- Code roots: `C:\xampp\htdocs\corelynk` and `C:\xampp\htdocs\corelynk_dev`
- Databases: `corelynk_db` and `corelynk_db_dev`

## Executive Summary

Development and production are separate environments by design, and they have drifted in both code and schema.

The good news is that deployable code drift is currently narrow. After excluding generated/runtime paths (`vendor`, `writable`, `testing`, `tmp`, `archives`, `public/uploads`), the audited file drift is:

- 7 files changed on both sides
- 12 files only in production
- 1 file only in development

The real deployment risk is not broad code sprawl. The real risk is database drift, especially around the customs invoice module, where development and production are materially different implementations.

## High-Level Findings

### Folder Inventory

- Production file count: 3360
- Development file count: 3200

### Deployable File Drift

Changed in both environments:

- `.env`
- `app/Config/Cookie.php`
- `app/Config/Routes.php`
- `app/Controllers/CustomsInvoices.php`
- `app/Services/CustomsInvoiceService.php`
- `app/Views/customs_invoices/workspace.php`
- `app/Views/pdf/customs_invoice.php`

Production-only files after excluding runtime asset folders:

- `app/Database/Migrations/2026-05-13-120000_add_deleted_at_columns_to_document_tables.php`
- `database/fixes/20260429_backfill_quotation_line_variant_ids.sql`
- `database/debug_queries.sql`
- `docs/ROOT_CAUSE_ANALYSIS_May_13_2026.md`
- temporary browser/debug artifacts under repo root

Development-only file after excluding runtime asset folders:

- `public/writable/uploads/customs_documents/20260513044233_6a040139c967c_statement_21021691_GBP_2025-04-01_2026-04-23.pdf`

Interpretation:

- The only meaningful code module actively diverging is `CustomsInvoices`
- `.env` and `app/Config/Cookie.php` are environment-specific and must not be copied blindly across environments
- The production-only migration that adds `deleted_at` columns is important and must be preserved in any future convergence plan

## File-Level Findings

### Environment-Specific Files

`.env`

- Environment wiring differs as expected
- This file should never be deployed by file copy from development to production
- Future deployments should use template-driven environment config, not folder copy

`app/Config/Cookie.php`

- Cookie path differs by deployment root (`/corelynk/` vs `/corelynk_dev/`)
- This is also environment-specific and must remain environment-owned

### Routes Drift

Development adds three customs invoice routes that do not exist in production:

- `POST customs-invoices/(:segment)/upload-documents`
- `GET customs-invoices/(:segment)/documents`
- `GET customs-invoices/(:segment)/documents/(:num)/download`

This shows development is adding a document-management layer to customs invoices.

### CustomsInvoices Controller Drift

Development adds these capabilities:

- `uploadDocuments($uuid = null)`
- `documents($uuid = null)`
- `downloadDocument($uuid = null, $docId = null)`

These methods store uploaded files in `WRITEPATH/uploads/customs_documents/`, hash them with SHA-256, and register metadata via `CustomsInvoiceFileModel`.

#### Deployment Blocker Found In Development

In development, `approvalPortal()` is declared as:

- `public function approvalPortal($uuid = null)`

But the method body still calls:

- `$this->service->getPendingApprovalByToken((string) $token);`

`$token` is undefined in that method scope.

Conclusion:

- Development customs-invoice code is not yet production-ready as-is
- This bug must be fixed before any customs-invoice feature promotion

### CustomsInvoiceService Drift

Development adds a much richer template model:

- `template_fields` added into snapshots
- `extractTemplateFieldsFromVersion()`
- `normalizeTemplateFields()`
- `getDefaultTemplateFields()`

This expands customs invoices from a simple value sheet into a structured document-template workflow.

Template fields now include, among others:

- `document_title`
- `document_subtitle`
- `commercial_invoice_no`
- `incoterm`
- `country_of_origin`
- `country_of_final_destination`
- `port_of_loading`
- `port_of_discharge`
- `consignee_name`
- `consignee_address`
- `bank_details`
- `declaration_text`
- `authorized_signatory`

### Customs Invoice Workspace View Drift

Development significantly expands the workspace UI:

- line delete buttons in the items table
- textarea-based line descriptions
- readonly line totals
- total boxes field
- large template-fields editor in the right-side panel
- JS serialization of `template_fields`
- upload-documents button and upload workflow

This is not a small visual patch. It is a functional module expansion.

### Customs Invoice PDF Drift

Development PDF rendering now consumes `template_fields` and adds richer document sections:

- customizable `document_title`
- `commercial_invoice_no`
- consignee/buyer block
- declaration text
- bank details
- authorized signatory block

Conclusion:

- Dev customs invoices and prod customs invoices are not on the same feature generation
- A safe promotion requires module-level migration, not file copy

## Database Findings

### Table Inventory

- Production tables: 155
- Development tables: 154

Only-in-production table:

- `stock_adjustment_audit`

### Schema Drift Summary

Column-level comparison found:

- 58 column definition differences
- 107 columns only in development
- 66 columns only in production

Most schema drift is concentrated in these areas:

- `customs_invoices`
- `customs_invoice_approvals`
- `customs_invoice_audit_logs`
- `customs_invoice_files`
- `customs_invoice_items`
- `customs_invoice_versions`
- `processing_records`
- `preparation_components`
- document line defaults on quotations, sales orders, purchase orders, and customer invoices

### High-Risk Schema Drift

#### Production-Only Structure That Must Not Be Lost

Production includes document soft-delete columns absent in development:

- `quotations.deleted_at`
- `quotation_lines.deleted_at`
- `sales_orders.deleted_at`
- `sales_order_lines.deleted_at`

These are already required by production behavior and must remain part of the forward schema.

Production also contains the separate table:

- `stock_adjustment_audit`

#### Development-Only Customs Invoice Structure

Development contains a newer customs-invoice model with many fields not present in production, including:

- UUID/public identifier fields
- version tracking fields
- snapshot hash fields
- template/document metadata
- audit metadata
- document storage metadata
- soft delete fields on customs tables

This is a true module redesign, not a small patch.

### Business Data Drift

Exact row counts for key tables:

| Table | Production | Development |
|---|---:|---:|
| customers | 394 | 377 |
| quotations | 29 | 12 |
| sales_orders | 20 | 9 |
| purchase_orders | 23 | 12 |
| products | 52 | 35 |
| product_variants | 4302 | 3489 |
| vendors | 11 | 10 |
| customs_invoices | 0 | 6 |
| customs_invoice_items | 0 | 11 |

Interpretation:

- Production has more live business data for core sales/purchase/customer domains
- Development has customs-invoice test or feature data that does not exist in production
- Bulk copying development data into production would be unsafe and would overwrite or diverge live production data

## What Is Safe To Promote

Safe candidates after validation:

- well-scoped customs-invoice code changes, but only after fixing the blocker and aligning schema
- explicit forward-only migrations
- additive route additions
- additive UI and PDF enhancements tied to an approved schema rollout

Not safe to promote by raw copy:

- `.env`
- `app/Config/Cookie.php`
- `public/uploads` or writable runtime artifacts
- production transactional data
- development customs-invoice data rows

## Recommended Migration Strategy

1. Treat customs invoices as a controlled module promotion, not a general folder sync.
2. Reconcile schema first using forward-only migrations.
3. Fix the `approvalPortal()` bug in development before any promotion.
4. Preserve production-only soft-delete and audit structures.
5. Never overwrite production data with development data except explicit approved reference/seed data.
6. Use scripted audit and scripted backup before every release.

## Immediate Next Steps

1. Fix the development customs-invoice blocker.
2. Generate forward-only migrations that bring production schema to the approved target state without dropping live data.
3. Promote customs-invoice code behind that schema migration.
4. Keep environment config out of code promotion.
5. Use the deployment scripts added under `tools/deployment` before every future production update.