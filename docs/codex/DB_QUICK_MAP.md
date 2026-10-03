# Database Quick Map

Use this as navigation, not a substitute for the actual migration/schema.

## Sales documents
- `customers`, customer contacts/addresses
- `quotations`, `quotation_lines`
- `sales_orders`, `sales_order_lines`
- sales-order-to-PO/RFQ mapping tables
- `customer_invoices`, `customer_invoice_lines`

## Purchase documents
- `vendors`, vendor contacts
- RFQ tables
- `purchase_orders`, `purchase_order_lines`
- `purchase_grns`, `purchase_grn_lines`
- `vendor_bills`, `vendor_bill_lines`
- `vendor_payments`, `vendor_payment_allocations`

## Fulfillment and inventory
- `stock_balances`, inventory/stock movement tables
- warehouses and warehouse locations
- `delivery_orders`, `delivery_order_lines`
- `delivery_order_parcel_images`, `delivery_order_tracking_docs`

## Accounting
- accounts/chart-of-accounts tables
- `journal_entries`, `journal_lines`
- source fields and `posted_entry_id` links on business documents

## Production
- `work_orders`, `work_order_items`
- product/process relationship tables
- `process_batches`, `process_batch_logs`, `process_batch_releases`
- QC and gatepass tables

## Schema verification commands
```bash
rg -n "CREATE TABLE.*table_name|table_name" app/Database/Migrations
rg -n "protected \\$table|allowedFields" app/Models/RelevantModel.php
mysql -u root -p corelynk_db -e "SHOW CREATE TABLE table_name\\G"
```
Never paste credentials into commands committed to the repo.
