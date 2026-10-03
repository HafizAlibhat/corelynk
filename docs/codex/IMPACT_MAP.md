# Change Impact Map

| Task area | Inspect first | Also verify | Mandatory regressions |
|---|---|---|---|
| Quotation | `Quotations`, `QuotationModel`, `QuotationLineModel`, quotation views | conversion to SO, PDF, discounts/tax, product/variant metadata | totals, section lines, currency, canonical URL |
| Sales Order | `SalesOrders`, SO models/views | quotation source, RFQ/PO maps, fulfillment, invoice/DO | no stock on creation, partial fulfillment, status |
| Purchase Order | `NewPurchaseOrders`, PO models/views | RFQ, advances, GRN, bills, vendor balance | confirmed-only actions, remaining amount/qty |
| GRN | `NewPurchaseGrns`, GRN models | inventory service/movements, PO receipt totals, bills | partial and over-receipt, duplicate submit, rollback |
| Vendor Bill | `VendorBills`, bill models, `AccountingPostingService` | PO/GRN linkage, payment allocations, vendor ledger | idempotent posting, correct AP, partial bill/payment |
| Vendor Payment/Advance | payment controllers/models, posting service | allocations, PO, bill balance, bank/cash | no overpay, advance account, cancellation/reversal |
| Delivery Order | `DeliveryOrders`, models, `DeliveryOrderService` | SO lines/status, inventory, shipping PO/bill, uploads | no double deduction, partial shipment, tracking |
| Customer Invoice | `CustomerInvoices`, line model, PDF/view | SO, accounting posting, discounts/tax/shipping | PDF/view parity, balanced posting, payment balance |
| Accounting | accounting controllers/models/services | source document and account lookup | Dr=Cr, idempotency, reversal, precision |
| Inventory | inventory controllers/models/services | all source modules | movement/balance parity, no negative stock |
| Production | WorkOrders/Production/Batches/process models | QC, release/gatepass, inventory handoff | planned vs accepted/repaired/rejected, transaction |
| UI-only | target view + shared layout/assets | route response shape and JS selectors | no business-layer diff, responsive/accessibility |
| Migration | migration + model allowed fields + all field usages | indexes/FKs/defaults/old rows | up/down on disposable DB, backward compatibility |

For every task, search exact table/field usages across `app` before modifying schema or semantics.
