# Coding Agent Reference Guide

## System Overview
This document provides a reference for the core modules, models, and database tables in the `corelynk` CodeIgniter 4 production management system. It is intended for use by coding agents and developers to ensure safe, consistent, and non-breaking updates to the codebase.

---

## Safe Update Principles
- **Never break production code:** All changes must be backward compatible and tested with sample data.
- **Always check permissions:** Use the standard permission check pattern before sensitive operations.
- **AJAX Response Format:** Always return `{ success, message, data }` for AJAX endpoints.
- **Error Handling:** Use try/catch and `log_message('error', ...)` for all DB operations.
- **Schema Flexibility:** Detect and adapt to schema variations (column presence, types) as in `ProcessBatchModel`.
- **Return empty arrays on DB errors** to avoid UI crashes.
- **Test after schema changes** using provided scripts.

---

## Module and Model to Table Mapping

| Model/Class                        | Database Table(s)                  |
|------------------------------------|------------------------------------|
| CompanySettingsModel               | company_settings                   |
| EmployeeModel                      | employees                          |
| ExchangeRateModel                  | exchange_rate                      |
| FiscalYearModel                    | fiscal_year                        |
| GatePassModel                      | gate_passes                        |
| ProcessBatchLogModel               | process_batch_logs                 |
| ProcessBatchModel                  | process_batches                    |
| ProcessCategoryModel               | process_categories                 |
| ProcessBatchReleaseModel           | process_batch_releases             |
| PaymentMethodModel                 | payment_methods                    |
| EmployeeSkillModel                 | employee_skills                    |
| ComponentUsageModel                | component_usage, component_stock_transactions, rework_records, scrap_records |
| ComponentModel                     | components                         |
| BatchModel                         | process_batches                    |
| BatchLogModel                      | process_batch_logs                 |
| ProductModel                       | products                           |
| ProductCategoryModel               | product_categories                 |
| ProcessWorkflowTemplateModel       | process_workflow_templates         |
| WorkOrderProcessRunModel           | work_order_process_runs            |
| WorkOrderModel                     | work_orders                        |
| WorkOrderItemModel                 | work_order_items                   |
| VendorModel                        | vendors                            |
| VendorGatepassModel                | vendor_gatepasses                  |
| VendorContactModel                 | vendor_contacts                    |
| UserModel                          | users                              |
| SecuritySettingsModel              | security_settings                  |
| QcRecordModel                      | qc_records                         |
| ProductWorkflowAssignmentModel     | product_workflow_assignments       |
| ProductProcessModel                | product_processes                  |
| ProcessTemplateModel               | process_templates                  |
| ProcessModel                       | processes                          |
| ProcessWorkflowStepModel           | process_workflow_steps             |

---

## Database Table Reference
Refer to `.github/copilot-instructions.md` for the authoritative schema, including all columns, keys, and relationships for each table.

---

## Controller and Model Structure
- All controllers are in `app/Controllers/`
- All models are in `app/Models/`
- Each model's `$table` property defines its main DB table

---

## Key Project Patterns
- **Permission Checks:**
  ```php
  if (!$this->checkPermission('module.action')) {
      return $this->response->setStatusCode(403)->setJSON(['error' => 'Insufficient permissions']);
  }
  ```
- **AJAX Response:**
  ```php
  return $this->response->setJSON([
      'success' => true,
      'message' => 'Operation completed',
      'data' => $results
  ]);
  ```
- **Error Handling:**
  ```php
  try {
      // DB operation
  } catch (\Throwable $e) {
      log_message('error', $e->getMessage());
      return [];
  }
  ```
- **Schema Detection:**
  Use `SHOW COLUMNS` or similar to adapt to schema changes.

---

## Usage
- Always consult this guide before making code changes.
- Use the model-to-table mapping to understand data flow.
- Follow safe update principles to avoid breaking production.
- For full schema, see `.github/copilot-instructions.md` and `database/corelynk_db.sql`.

---

_Last updated: November 26, 2025_