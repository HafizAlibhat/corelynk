# CoreLynk Code Tree

This file maps controllers, models, and views to help you understand which modules use which files. It focuses on the main accounting/vendor payment flow and provides a general mapping for other modules.

---

## How to read this file
- Controller => Models it uses => Views it renders
- Paths are workspace-relative.

---

## Example: Vendor Payments
- Controller: app/Controllers/AccountingVendorPayments.php
  - Models used:
    - app/Models/VendorPaymentModel.php
    - app/Models/VendorPaymentAllocationModel.php
    - app/Models/DocumentAttachmentModel.php
    - app/Models/VendorModel.php
    - app/Models/PaymentMethodModel.php
  - Views rendered:
    - app/Views/accounting/vendor_payments/pay.php
    - app/Views/accounting/vendor_payments/index.php
    - app/Views/accounting/vendor_payments/view.php

Notes: The `edit()` flow should fetch a row from `vendor_payments` (VendorPaymentModel) plus allocations and attachments, then render `pay.php` with `payment`, `allocations`, and `attachments`.

---

## Key Accounting Controllers (worth checking)
- app/Controllers/AccountingVendorPayments.php
- app/Controllers/AccountingCustomerPayments.php
- app/Controllers/AccountingDashboard.php
- app/Controllers/AccountingJournals.php
- app/Controllers/AccountingReceipts.php
- app/Controllers/AccountingCheques.php
- app/Controllers/AccountingAccounts.php
- app/Controllers/AccountingReports.php

## Common Models used by Accounting
- app/Models/VendorPaymentModel.php
- app/Models/VendorPaymentAllocationModel.php
- app/Models/CustomerPaymentModel.php
- app/Models/CustomerPaymentAllocationModel.php
- app/Models/DocumentAttachmentModel.php
- app/Models/VendorModel.php
- app/Models/CustomerModel.php
- app/Models/PaymentMethodModel.php
- app/Models/CompanySettingsModel.php

## Views (top-level accounting views)
- app/Views/accounting/vendor_payments/
  - pay.php — payment form (create/edit)
  - index.php — list / drafts
  - view.php — read-only view
- app/Views/accounting/customer_payments/
  - pay.php
  - index.php
  - view.php
- app/Views/accounting/
  - dashboard.php
  - journals/*.php
  - receipts/*.php

---

## Other modules (controllers -> suggested models -> views)
- Vendors
  - Controller: app/Controllers/Vendors.php
  - Models: app/Models/VendorModel.php, app/Models/VendorContactModel.php
  - Views: app/Views/vendors/*, app/Views/vendor_bills/*

- Products
  - Controller: app/Controllers/Products.php
  - Models: app/Models/ProductModel.php, app/Models/ProductVariantModel.php
  - Views: app/Views/products/*, app/Views/product_variants/*

- Purchases / GRNs
  - Controller: app/Controllers/NewPurchaseOrders.php, NewPurchaseGrns.php
  - Models: PurchaseOrderModel.php, PurchaseGrnModel.php, PurchaseGrnLineModel.php
  - Views: app/Views/purchase_ui/*, app/Views/vendor_bills/*

- Customers / Invoices
  - Controller: app/Controllers/CustomerInvoices.php
  - Models: CustomerInvoiceModel.php, CustomerInvoiceLineModel.php
  - Views: app/Views/invoices/*, app/Views/customers/*

---

## How to extend this mapping
1. Open `app/Controllers/<Name>.php` and search for `$this->` or `$this->model` to see which models are used.
2. Check `return view('...')` lines to see which view files are rendered.
3. For models, open `app/Models/<ModelName>.php` to inspect table names and relationships.

---

## TODO / Suggested next steps
- Generate a full programmatic mapping script to scan all controllers for `new ModelName()` and `->view()` calls and write a complete JSON/YAML map.
- For `AccountingVendorPayments::edit()` verify it calls the VendorPaymentModel and passes `payment`, `allocations`, and `attachments` into `pay.php`.

---

If you want, I can generate a complete, machine-readable mapping (JSON) by scanning the repository and extracting `return view(...)` and `Model` usages — shall I do that next?