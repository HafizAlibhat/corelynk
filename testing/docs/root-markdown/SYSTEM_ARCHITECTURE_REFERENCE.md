# Production Management System - Architecture Reference
## Complete Infrastructure Documentation for Development Collaboration

### 🏗️ SYSTEM OVERVIEW
- **Framework**: CodeIgniter 4.6.3 (PHP 8.1+)
- **Database**: MySQL (production_management_system)
- **Frontend**: Bootstrap 5.3 + Custom ES6 JavaScript
- **Architecture**: MVC Pattern with Service Layer
- **Authentication**: Session-based with Role-Based Access Control (RBAC)

---

## 📁 CONTROLLERS (29 Files)
### Core Business Logic Controllers
1. **Auth.php** - Authentication & user sessions
2. **Dashboard.php** - Main dashboard & KPIs
3. **Production.php** - Production logs & batch management ⭐ (Recently fixed)
4. **WorkOrders.php** - Work order lifecycle management
5. **Products.php** - Product catalog & BOM management
6. **Processes.php** - Manufacturing process definitions
7. **ProcessCategories.php** - Process categorization
8. **ProcessTemplates.php** - Reusable process templates
9. **Vendors.php** - Supplier relationship management
10. **Employees.php** - Employee directory & skills
11. **Components.php** - Inventory & component tracking
12. **QualityControl.php** - QC inspections & testing
13. **Batches.php** - Production batch tracking
14. **GatePasses.php** - Material gate pass system
15. **Reports.php** - Analytics & reporting engine
16. **PDFs.php** - PDF generation service

### Supporting Controllers
17. **BaseController.php** - Base class with auth & permissions
18. **ProductCategories.php** - Product classification
19. **WorkflowTemplates.php** - Process workflow templates

### Test/Debug Controllers (Development)
20. **TestSystem.php** - System integration tests
21. **TestProducts.php** - Product functionality tests
22. **TestBatches.php** - Batch system tests
23. **BatchDebug.php** - Batch debugging utilities
24. **BatchesSimple.php** - Simplified batch interface
25. **DebugController.php** - General debugging tools
26. **DatabaseTest.php** - Database connectivity tests
27. **LoginTest.php** - Authentication testing
28. **DebugBatches.php** - Batch-specific debugging
29. **Home.php** - Default homepage controller

---

## 🗃️ MODELS (26 Files)
### Core Data Models
1. **UserModel.php** - User accounts & authentication
2. **ProductModel.php** - Product catalog management
3. **ProcessModel.php** - Manufacturing processes
4. **ProcessTemplateModel.php** - Process templates
5. **ProcessCategoryModel.php** - Process categorization
6. **WorkOrderModel.php** - Work order management
7. **WorkOrderItemModel.php** - Work order line items
8. **ProcessBatchModel.php** - Production batches ⭐ (Core to production)
9. **ProcessBatchLogModel.php** - Batch activity logs
10. **VendorModel.php** - Supplier information
11. **EmployeeModel.php** - Employee directory
12. **ComponentModel.php** - Inventory components
13. **QcRecordModel.php** - Quality control records
14. **GatePassModel.php** - Gate pass documentation

### Relationship & Junction Models
15. **ProductProcessModel.php** - Product-process relationships
16. **ProductCategoryModel.php** - Product categorization
17. **ComponentUsageModel.php** - Component consumption tracking
18. **EmployeeSkillModel.php** - Employee skill matrix
19. **WorkOrderProcessRunModel.php** - Process execution tracking
20. **ProcessBatchReleaseModel.php** - Batch release management
21. **ProcessWorkflowTemplateModel.php** - Workflow templates
22. **ProcessWorkflowStepModel.php** - Workflow steps
23. **ProductWorkflowAssignmentModel.php** - Product workflow assignments
24. **VendorGatepassModel.php** - Vendor gate pass tracking

### Legacy/Fixed Models
25. **ProcessCategoryModel_fixed.php** - Fixed version of process categories
26. **.gitkeep** - Git placeholder file

---

## 🎨 VIEWS (110+ Files across 15+ directories)
### Main Application Views
- **dashboard/** - Dashboard & analytics interfaces
- **production/** - Production logs & batch management ⭐
- **work_orders/** - Work order management interfaces
- **products/** - Product catalog & management
- **processes/** - Process definition interfaces
- **vendors/** - Supplier management
- **employees/** - Employee directory
- **quality_control/** - QC inspection forms
- **reports/** - Analytics & reporting views

### Supporting Views
- **auth/** - Login & authentication pages
- **layouts/** - Master page templates (main.php, main_clean.php, etc.)
- **components/** - Inventory management interfaces
- **batches/** - Batch tracking interfaces
- **gate_passes/** - Gate pass documentation
- **process_categories/** - Process categorization
- **process_templates/** - Template management
- **product_categories/** - Product classification
- **workflow_templates/** - Workflow management

---

## 🗄️ DATABASE STRUCTURE

### Core Tables (15+ tables)
1. **users** - User accounts & authentication
2. **products** - Product catalog
3. **processes** - Manufacturing processes
4. **process_templates** - Reusable process definitions
5. **process_categories** - Process classification
6. **work_orders** - Production orders
7. **work_order_items** - Work order line items
8. **process_batches** - Production batches ⭐ (Central to system)
9. **process_batch_logs** - Batch activity history
10. **vendors** - Supplier information
11. **employees** - Employee directory
12. **components** - Inventory items
13. **qc_records** - Quality control data
14. **gate_passes** - Material gate passes
15. **product_processes** - Product-process relationships

### Key Database Scripts
- **simple_schema.sql** - Basic table structure
- **create_tables.php** - Table creation script
- **insert_sample_data.php** - Sample data population
- **database/production_management_system_complete.sql** - Full schema

---

## 🔧 KEY SYSTEM PATTERNS

### Authentication & Authorization
- Session-based authentication via `Auth` controller
- Role-based permissions: admin, planner, production, qc, stores, accounts, viewer
- Permission checks: `$this->checkPermission('module.action')`

### AJAX API Pattern
- Controllers validate: `if (!$this->request->isAJAX())`
- Standard JSON response: `{ success: boolean, message: string, data: mixed }`
- CSRF protection: `X-CSRF-TOKEN` header with `window.csrfToken`

### Data Access Pattern
- Models with helper methods (e.g., `getBatchesWithDetails()`)
- Fallback raw queries via `Config\Database::connect()`
- Exception handling with `log_message('error', ...)`

### Frontend Architecture
- Bootstrap 5.3 responsive components
- Custom ES6 JavaScript modules
- Chart.js for data visualization
- Modal-based interactions

---

## 🚀 RECENT CHANGES & STATUS

### Recently Fixed (Current Session)
- ✅ **Production batch deletion** - Fixed missing route & button behavior
- ✅ **AJAX delete endpoint** - Added `POST production/ajax-delete-batch/(:num)`
- ✅ **Button type conflicts** - Added `type="button"` to prevent form submission
- ✅ **Confirmation dialogs** - Fixed browser confirm() blocking

### Major New Features (Current Session)
- 🆕 **Hierarchical Production View** - Enterprise SAP-style tree interface
- 🆕 **Dual View System** - Toggle between table and hierarchy views
- 🆕 **5-Level Data Structure** - Work Orders → Products → Processes → Batches → Logs
- 🆕 **AJAX Lazy Loading** - Efficient expand/collapse with progressive data loading
- 🆕 **Enterprise Styling** - Professional color scheme and compact design

### Current Working State
- Production logs system fully functional with dual view modes
- Hierarchical view with lazy loading and enterprise styling
- Batch creation, editing, and deletion working in both views
- AJAX endpoints properly routed (including new hierarchy endpoints)
- Authentication & permissions active
- Database schema stable with optimized hierarchical queries

---

## 📝 DEVELOPMENT COMMUNICATION PROTOCOL

### When Referencing Components:
- **Controllers**: Use class name (e.g., "Production controller", "WorkOrders controller")
- **Models**: Use class name (e.g., "ProcessBatchModel", "WorkOrderModel")
- **Views**: Use path (e.g., "production/logs.php", "work_orders/show.php")
- **Database**: Use table name (e.g., "process_batches table", "work_orders table")

### For Major Changes:
1. Identify affected components using this reference
2. Consider impact on related models/controllers
3. Check for AJAX endpoints that need updating
4. Verify permission requirements
5. Test with sample data

### Database Modifications:
- Use migration scripts in root directory
- Update corresponding model classes
- Check for foreign key relationships
- Test with existing data

### File Backup Protocol:
- Critical changes: Create timestamped backup zip
- Use format: `pro_sys_backup_YYYYMMDD_HHMM.zip`
- Store in parent directory (`c:\xampp\htdocs\`)

---

**This reference document serves as our shared vocabulary for efficient communication about system modifications. Reference specific components by their names listed above for clarity.**

*Last Updated: October 13, 2025*