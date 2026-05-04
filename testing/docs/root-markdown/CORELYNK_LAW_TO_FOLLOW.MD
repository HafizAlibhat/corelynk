CoreLynk ERP Constitution

(Version 1.0 – Pre-Implementation Control Document)

1. Business Reality (Ground Truth)

CoreLynk is a hybrid trading + manufacturing + services ERP.

Business Characteristics

Buys goods locally in PKR

Sells internationally in USD / EUR

Handles physical products and services

Maintains one or more warehouses

Performs:

In-house manufacturing

Multi-stage production

Subcontracting / job work

Tracks:

Raw materials

Work-in-Progress (WIP)

Finished goods

Charges:

Product price

Shipping charges (sometimes separate, sometimes inclusive)

Service charges (e.g., laser marking, branding)

Supports:

Advance payments

Partial payments

Post-shipment payments

📌 ERP must reflect physical and financial reality. No fictional data is allowed.

2. Module Responsibilities & Boundaries

Each module has clear ownership. No overlap is allowed.

Accounting

Owns

Ledger

Debits & credits

Taxes

Currency conversion

Product costing & valuation

Customer & vendor balances

Vendor advances & prepayments

Does NOT

Control stock quantity

Control production steps

Control sales or purchase logic

Inventory

Owns

Stock quantity

Stock location

Stock movements

Raw / WIP / Finished goods separation

Does NOT

Store cost or value

Perform accounting

Decide pricing

Products

Owns

Product & service master data

Units of measure

Product type (physical / service)

Tracking rules (batch, serial, etc.)

Does NOT

Track stock

Track prices

Track costs

Sales

Owns

Quotations

Sales orders

Customer invoices

Shipping charges

Service charges

Does NOT

Reduce stock directly

Post accounting entries directly

Calculate balances

Purchase

Owns

RFQs

Purchase orders

Goods receipt records

Vendor bills (documents only)

Does NOT

Increase stock directly

Handle payments

Post ledger entries

Production

Owns

BOMs

Production stages

In-house operations

Subcontracting workflows

Does NOT

Change stock directly

Calculate costs

Post accounting entries

3. Data Ownership Rules (Non-Negotiable)
Data	Owner
Stock quantity	Inventory
Stock movement history	Inventory
Product cost	Accounting
Customer balance	Accounting
Vendor balance	Accounting
Exchange rates	Accounting
Product definition	Products

📌 Only the owner module may WRITE data. Others may READ only.

4. Inter-Module Interaction Rules

Sales, Purchase, Production REQUEST actions

Inventory and Accounting EXECUTE actions

Examples:

Sales requests stock deduction → Inventory performs it

Purchase requests stock increase → Inventory performs it

Sales/Purchase trigger accounting → Accounting posts entries

5. End-to-End Workflows
5.1 Sales Workflow
Quotation
→ Sales Order (allowed without stock)
→ (Wait for stock via purchase or production)
→ Shipment (partial shipments allowed)
→ Invoice (can be before or after shipment)
→ Accounting Entry


Key Rules

Quotation → no stock impact

Sales Order → no stock impact

Shipment → stock MUST exist

Partial shipments allowed

Remaining quantities can be backordered or cancelled

Services never affect inventory

Payment rules are configurable (advance / partial / postpaid)

5.2 Purchase Workflow
RFQ / Draft PO
→ Confirmed PO
→ Goods Receipt (GRN)
→ Vendor Bill
→ Accounting Entry


Key Rules

PO → no stock impact

GRN → stock increases

Vendor advances & partial payments handled ONLY by Accounting

Payments may exist with or without PO

5.3 Production & Subcontracting Workflow
Raw Material
→ Issue to Production
→ In-house / Subcontract Stage
→ Receive WIP
→ Next Stage
→ Final Stage
→ Finished Goods in Inventory


Key Rules

Planning & BOM → no stock impact

Issue material only when stage starts

Receive goods after each stage

Subcontracting always has OUT and IN movements

Finished goods become sellable stock

All costing handled by Accounting only

6. Audit, Control & Validation Rules
Inventory

Every stock change creates a movement record

Movements are never deleted, only reversed

Negative stock not allowed by default

Accounting

Debit must equal credit

Closed periods cannot be modified

All currency conversion handled centrally

Documents

Every document has a unique, immutable reference number

Documents can be cancelled, not deleted

Cancellation requires reason

Reversals are mandatory

Archived records remain traceable

7. Core Principles (The “Constitution Laws”)

Physical truth before system convenience

One module owns one responsibility

Nothing is deleted, everything is traceable

Stock never lies

Money is handled only by Accounting

AI suggestions must obey this document