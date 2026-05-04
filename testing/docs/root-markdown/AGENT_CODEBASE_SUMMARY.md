# CoreLynk ERP - Agent Codebase Summary

This document serves as an AI agent reference guide to the CoreLynk ERP structure, business logic, and coding rules, derived from initial codebase scanning.

## 🏢 1. System Overview & Tech Stack
- **Framework**: CodeIgniter 4.6.3 (PHP 8.1+)
- **Database**: MySQL (`production_management_system`)
- **Frontend**: Bootstrap 5.3 + Custom ES6 JavaScript + Chart.js
- **Architecture**: MVC Pattern with Service Layer
- **Authentication**: Session-based with Role-Based Access Control (RBAC) (roles: admin, planner, production, qc, stores, accounts, viewer)
- **Pathing convention**: Applications reside in `app/`. Controllers in `app/Controllers/`, Models in `app/Models/`, Views in `app/Views/`. 

## 📖 2. Modules and Data Ownership (The "Ground Truth")
CoreLynk is a hybrid tracking system for: Trading + Manufacturing + Services.
**Rule of thumb**: Physical truth comes before system convenience. One module owns one responsibility. 

### Core Modules & Responsibilities:
1. **Accounting**
   - **Owns**: Ledger, Debits/Credits, Taxes, Currency conversion (USD/EUR selling vs PKR buying), Product costing/valuation, Customer/Vendor balances, Advances/Prepayments.
   - **Never**: Controls stock directly or production steps. Handling money is strictly done by Accounting.
2. **Inventory**
   - **Owns**: Stock quantity, locations, movements, Raw/WIP/Finished Goods separation.
   - **Never**: Stores cost or performs accounting.
3. **Products**
   - **Owns**: Product & service master data, UOM, type (physical/service), tracking rules.
   - **Never**: Tracks stock, prices, or costs.
4. **Sales**
   - **Owns**: Quotations, Sales Orders, Customer Invoices, Shipping/Service charges.
   - **Never**: Reduces stock directly or calculates balances.
5. **Purchase**
   - **Owns**: RFQs, Purchase Orders, Goods Receipts (GRN), Vendor Bills.
   - **Never**: Increases stock directly or handles payments.
6. **Production**
   - **Owns**: BOMs, Production stages, In-house operations, Subcontracting workflows.
   - **Never**: Changes stock directly, calculates costs, or posts accounting entries.

## 🔄 3. Typical Workflows
- **Sales Workflow**: Quotation -> Sales Order -> Shipment (needs stock) -> Invoice -> Accounting Entry.
- **Purchase Workflow**: RFQ -> PO -> GRN (increases stock) -> Vendor Bill -> Accounting Entry.
- **Production Workflow**: Raw Material -> Issue to Production -> Stage -> Receive WIP -> ... -> Finished Goods in Inventory.

## 📂 4. Directory Map & Application Structure
- **`app/Controllers/`** -> Contains >70 files. Important files:
  - `Accounting*.php` files for ledger and financial management.
  - `Products.php`, `Customers.php`, `Vendors.php`, `Employees.php` for core entity management.
  - `NewPurchaseOrders.php`, `SalesOrders.php`, `Quotations.php` for tracking active trading sequences.
  - `Production.php`, `Batches.php`, `WorkOrders.php`, `Processes.php` for handling manufacturing.
- **`app/Models/`** -> Contains >75 files. Naming strictly `<EntityName>Model.php`. 
  - Subfolders like `app/Models/Accounting/` contain specialized layers.
  - Notable Models: `ProcessBatchModel.php`, `WorkOrderModel.php`, `VendorModel.php`, `ComponentUsageModel.php`. 
- **`app/Views/`** -> Divided meticulously by feature folder (`accounting/`, `dashboard/`, `production/`, `work_orders/`, etc.). 

## ⚖️ 5. Key Coding Principles & System Rules
1. **Never mock data boundaries**: If Sales need to deduct stock, it REQUESTS Inventory to do so. It cannot execute it itself. 
2. **Delete operations are restricted**: Financials and Stock movements are never deleted, only reversed. Documents can be canceled but need a reason.
3. **AJAX standards**: Endpoints are strictly validated `if (!$this->request->isAJAX())`. CSRF protection must be included on every request (`window.csrfToken`). Standard JSON structure returned: `{ success: bool, message: string, data: mixed }`.
4. **Database calls**: Use Models mostly (`$batchModel->getBatches()`), but raw queries via `Config\Database::connect()` are allowed for high-complexity operations.
5. **Backup protocol**: If making major/critical modifications, create a timestamped backup zip `pro_sys_backup_YYYYMMDD_HHMM.zip` in `c:\xampp\htdocs\`.

*This file can be safely referenced to recall the core architecture of CoreLynk before any modifications or upgrades are started.*
