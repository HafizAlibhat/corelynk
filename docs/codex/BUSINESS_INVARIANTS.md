# CoreLynk Business Invariants

## Accounting
- Every posted journal is balanced to configured precision.
- Posting is idempotent: one source document cannot create duplicate journal entries.
- `posted_entry_id` and source references remain consistent.
- Vendor advance: Dr Vendor Advance / Cr Cash-Bank; applying advance: Dr AP / Cr Vendor Advance.
- Vendor bill: appropriate purchase/expense/inventory debit / AP credit.
- Payment cannot exceed legitimate outstanding balance unless an explicit unapplied-credit workflow exists.
- Posted entries are reversed, not deleted or silently edited.

## Purchase and receiving
- PO ordered, received, rejected/returned, billed, and paid quantities/amounts are separate facts.
- GRN cannot silently overwrite prior receipt history.
- Partial receipt and partial billing must preserve remaining quantity.
- Over-receipt requires explicit approved reason and traceability.
- One-bill-per-PO assumptions must not be introduced where partial bills are required; verify current implementation first.

## Inventory
- Every stock change has a movement record and source reference.
- Delivery/shipment deduction and GRN receipt happen atomically with source status change.
- Physical products affect stock; service products do not.
- Never repair stock by directly editing balance without a movement/reconciliation mechanism.

## Sales and delivery
- Quotation and Sales Order creation have no stock effect.
- Shipment quantity cannot exceed ready/available/remaining quantity.
- Partial shipment must leave accurate remaining quantity and status.
- DO confirmation must not double-deduct inventory on retry.
- SO status changes must represent aggregate fulfillment, not one line in isolation.

## Documents and totals
- Header total equals deterministic line subtotal − line discounts − document discount + tax + shipping/customs according to existing policy.
- Line discount and document discount must not be double-counted.
- View, PDF, API, and accounting posting must use the same source values or shared calculator.
- Preserve decimal precision and currency; do not round early.

## Security and audit
- Every write requires authentication, authorization, validation, CSRF where applicable, and audit identity.
- Public IDs are exposure identifiers, not authorization controls.
- Cancellation/reversal records retain who, when, why, and source linkage.
