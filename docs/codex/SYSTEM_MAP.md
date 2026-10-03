# CoreLynk System Map

## Verified stack
- CodeIgniter 4.6.3, PHP 8.1+, MySQL, Bootstrap/custom JavaScript, Dompdf, PHPUnit 10.5.
- Repository scale at audit: 84 controllers, 124 models, 24 services, 229 views, 116 migrations, and a 1,278-line routes file.

## Primary layers
- `app/Controllers/`: request orchestration, authentication/permission checks, validation, view/API response.
- `app/Models/`: table access and entity persistence.
- `app/Services/`: cross-table business operations, accounting posting, inventory and delivery workflows.
- `app/Views/`: HTML/PDF rendering and frontend behavior.
- `app/Database/Migrations/`: schema history.
- `app/Config/Routes.php`: centralized route registry; inspect group prefixes before declaring duplicates.
- `tests/`: automated suite. Coverage is currently sparse relative to system size.
- `testing/`, `tools/`, root scripts: historical diagnostics and potentially destructive utilities; not normal application code.

## End-to-end commercial flow
Customer/Product → Quotation → Sales Order → sourcing/RFQ → Purchase Order → GRN → Vendor Bill → Accounting → stock ready → Delivery Order → shipment/delivery → Customer Invoice/receivable/payment.

Manufacturing flow: Work Order → item/process → batch → daily logs/QC → release/gatepass → inventory handoff.

## High-risk services and domains
- Accounting posting and journal integrity.
- Inventory movements and stock balances.
- Purchase receiving, over-receipt, vendor bills, payments, and advances.
- Sales order fulfillment and delivery confirmation.
- Document totals: tax, shipping, line/document discounts, currency.
- Public/document numbering and canonical public IDs.

## Efficient navigation
Use exact searches before opening folders:
```bash
rg -n "ClassName|methodName|table_name|field_name|route-fragment" app tests
rg -n "source_type|source_id|posted_entry_id" app
rg -n "trans(Start|Begin|Commit|Rollback|Complete)" app/Controllers app/Services
php spark routes | rg "route-fragment"
```
Avoid broad `find`/full reads once relevant files are identified.
