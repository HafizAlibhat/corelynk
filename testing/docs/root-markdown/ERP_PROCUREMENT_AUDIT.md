# ERP Procurement-to-Delivery Audit Report
**Generated:** $(date)**  
**System:** Corelynk ERP (CodeIgniter 4)  
**Database:** MySQL corelynk_db  
**Scope:** Complete procurement flow from Quotation → Sales Order → RFQ → PO → GRN → Stock → DO → Vendor Bill

---

## Executive Summary

This audit examined the end-to-end procurement lifecycle across **10 stages** to identify data integrity, aggregation, and control gaps. The system has **strong architectural foundations** with proper separation of concerns, but several **critical gaps** exist:

### ✅ Strengths
1. **GRN Aggregation Infrastructure**: PO lines track `qty_received` and GRN lines link back via `po_line_id`
2. **Transaction Safety**: All major operations wrapped in database transactions
3. **Stock Movement Tracking**: Every inventory change creates `stock_movements` records
4. **Multi-Vendor Support**: Flexible vendor management with variant-level pricing
5. **Over-Receipt Detection**: System calculates and stores `over_received_qty` to flag excess receipts

### ⚠️ Critical Gaps
1. **Vendor Bill Not Linked to GRN**: VendorBillModel has no reference to GRN or PO; payment basis unclear
2. **No Over-Receipt Prevention**: System allows receiving more than ordered with `over_received_qty` field but no enforcement
3. **No Over-Shipping Prevention**: DO confirmation deducts stock with **no validation** that DO qty ≤ available stock
4. **SO Status Not Gated on Stock**: Sales Order can be confirmed even if stock insufficient (Phase-1 is READ-ONLY)
5. **Partial Received PO Behavior Undefined**: No clear logic for PO qty_received updates or remaining qty display
6. **Missing PO Line Constraints**: No database constraints preventing qty_received > qty

---

## Stage-by-Stage Analysis

### Stage 1: Quotation → Sales Order
**Model**: `Quotation` → `SalesOrderModel`  
**Controller**: `Quotations`, `SalesOrders`

**Data Flow**:
```
Quotation (quotations table)
  ↓ [user selects "Convert to Sales Order"]
Sales Order (sales_orders table)
  ├─ order_number (auto)
  ├─ customer_id (required) ✅
  ├─ subtotal, tax_total, total
  └─ status = 'draft'
     ↓
Sales Order Lines (sales_order_lines table)
  ├─ product_id, variant_id ✅
  ├─ quantity (ordered qty)
  ├─ unit_price, tax_percent
  └─ No stock reservation happens
```

**Findings**:
- ✅ **Customer validation added** (Session 1): Prevents null customer_id
- ✅ **Variant tracking enabled**: Product variants properly linked
- ⚠️ **No stock check**: SO created regardless of inventory availability (by design - Phase 1 is READ-ONLY)
- ⚠️ **Reserved qty never set**: No `reserved` flag created in stock_balances when SO confirmed
- ⚠️ **Inventory shortage display only**: Warning banner shown but order can proceed

**Status**: PARTIAL RISK  
**Recommendation**: Phase-2 should add SO confirmation gate: prevent confirmation if stock < ordered qty

---

### Stage 2: Sales Order → RFQ Creation
**Model**: `PurchaseRfqModel`, `PurchaseRfqLineModel`  
**Controller**: `NewPurchaseRfqs`

**Data Flow**:
```
Sales Order (confirmed)
  ├─ [User creates auto-RFQ]
       ↓
Purchase RFQ (purchase_rfqs table)
  ├─ rfq_number (auto)
  ├─ vendor_id (selected) ✅ FIXED
  ├─ subtotal, total
  └─ status = 'draft'
     ↓
Purchase RFQ Lines (purchase_rfq_lines table)
  ├─ product_id, variant_id ✅
  ├─ qty (linked to SO line qty)
  ├─ unit_price (from vendor_prices or manual) ✅ FIXED
  └─ No link to purchase_order yet
```

**Findings**:
- ✅ **Vendor selection fixed** (Session 2): vendor_id=0 now properly validated
- ✅ **Vendor price auto-load** (Session 3): Loads after lines populated
- ✅ **Variant-aware**: RFQ lines carry variant_id
- ✅ **User can edit qty**: Can change 1→100 before confirmation
- ⚠️ **No quantity limit check**: RFQ qty not gated to SO qty
- ⚠️ **No supplier capacity validation**: No min/max order qty check

**Status**: HEALTHY (with recent fixes)  
**Recommendation**: Add optional quantity lock to prevent accidental over-ordering

---

### Stage 3: RFQ → Purchase Order
**Model**: `PurchaseOrderModel`, `PurchaseOrderLineModel`  
**Controller**: `NewPurchaseRfqs::accept()`

**Data Flow**:
```
Purchase RFQ (status='draft')
  ├─ [User clicks "Confirm RFQ"] ✅ FIXED
       ↓
  ├─ Modal prompts for delivery_date ✅ FIXED
       ↓
Purchase Order (purchase_orders table)
  ├─ po_number (auto)
  ├─ rfq_id (foreign key)
  ├─ vendor_id, sales_order_id (optional)
  ├─ subtotal, tax_total, total
  ├─ status = 'confirmed' (if delivery_date provided) ✅ FIXED
  ├─ delivery_date (now stored) ✅ FIXED
  └─ currency, currency_code
     ↓
Purchase Order Lines (purchase_order_lines table)
  ├─ po_id (foreign key)
  ├─ product_id, variant_id ✅
  ├─ qty (from RFQ)
  ├─ qty_received = 0 (initial)
  ├─ unit_price, unit_cost
  └─ No constraints on qty_received
```

**Findings**:
- ✅ **Single-step RFQ→PO** (Session 4): Eliminated multi-click confirmation
- ✅ **Delivery date captured**: Now stored on PO creation
- ✅ **Status auto-confirmed**: PO marked confirmed if delivery_date provided
- ✅ **Transaction-safe**: Wrapped in db->transBegin/transCommit
- ⚠️ **No constraint**: Database allows qty_received > qty
- ⚠️ **No GRN link at PO creation**: Link only happens when GRN created
- ⚠️ **sales_order_id optional**: PO can exist without SO linkage

**Status**: HEALTHY  
**Recommendation**: Add database CONSTRAINT: `qty_received <= qty` on purchase_order_lines

---

### Stage 4: Purchase Order → Goods Receipt (GRN)
**Model**: `PurchaseGrnModel`, `PurchaseGrnLineModel`  
**Controller**: `NewPurchaseGrns::create()`

**Data Flow**:
```
Purchase Order (status='confirmed')
  ├─ [Warehouse receives goods]
       ↓
Purchase GRN (purchase_grns table)
  ├─ grn_number (auto)
  ├─ po_id (foreign key) ✅
  ├─ vendor_id
  ├─ received_at = NOW()
  └─ status = (implicit; no explicit status field)
     ↓
Purchase GRN Lines (purchase_grn_lines table)
  ├─ grn_id (foreign key)
  ├─ po_line_id (foreign key) ✅ AGGREGATION LINK
  ├─ product_id, variant_id ✅
  ├─ qty_received (actual qty in goods)
  ├─ over_received_qty (qty > ordered) ✅ FLAGGED
  ├─ unit_price, unit_cost
  └─ created_at = NOW()
```

**Critical Logic** (NewPurchaseGrns::create() lines 139-191):
```php
// AGGREGATION HAPPENS HERE ✅
if ($poLineId && $poLine) {
    $newReceived = (float)$poLine['qty_received'] + $qtyReceived;
    $poLineModel->update($poLineId, ['qty_received' => $newReceived]);
}

// STOCK INCREASE (via InventoryService) ✅
if ($productId) {
    $inventory->receiveFromGrn($productId, $warehouseId, $locationId, $qtyReceived, $grnId);
}
```

**Findings**:
- ✅ **Aggregation infrastructure working**: qty_received on PO line UPDATED when GRN created
- ✅ **Multiple GRNs supported**: Can create partial receipts; cumulative qty_received = sum of all GRNs
- ✅ **Over-receipt flagged**: Calculates `over_received_qty = max(0, qtyReceived - pendingQty)`
- ✅ **Stock increase transactional**: Wrapped in transaction; rolled back on error
- ✅ **Variant-aware**: GRN lines inherit variant_id from PO lines or accept variant_id in request
- ✅ **Optional PO closure**: Can mark PO as 'closed' if close_po flag set
- ⚠️ **Over-receipt allowed**: No validation preventing qty_received > qty on PO line
- ⚠️ **No GRN status**: GRN model has no explicit status field (implicit 'received')
- ⚠️ **No over-receipt approval workflow**: over_received_qty flagged but not reviewed/approved

**Status**: HEALTHY (Infrastructure Strong)  
**Critical Gaps**: Over-receipt prevention not enforced

**Recommendation**: 
1. Add CHECK constraint: `qty_received <= qty` on purchase_order_lines
2. Add `over_receipt_approved` field to purchase_grn_lines (boolean, default 0)
3. Add approval workflow before GRN confirmation if over_received_qty > 0

---

### Stage 5: GRN → Stock Balance
**Model**: Stock handled via `InventoryService`, `stock_balances`, `stock_movements`

**Data Flow**:
```
Purchase GRN Line (qty_received = 90)
  ├─ [InventoryService::receiveFromGrn()] ✅
       ↓
stock_balances (warehouse/location-specific)
  ├─ product_id, variant_id, warehouse_id, location_id
  ├─ quantity += qty_received
  └─ updated_at = NOW()
     ↓
stock_movements (audit trail)
  ├─ product_id, variant_id
  ├─ warehouse_id, location_id
  ├─ qty_change = +90
  ├─ movement_type = 'grn_receipt'
  ├─ reference_type = 'grn'
  ├─ reference_id = grn_id
  └─ created_by, created_at
```

**Findings**:
- ✅ **Multi-warehouse support**: stock_balances by warehouse_id & location_id
- ✅ **Variant tracking**: Variants stored in stock_balances
- ✅ **Audit trail maintained**: Every movement logged in stock_movements
- ✅ **Transactional**: All updates wrapped in transaction
- ⚠️ **No reservation layer**: stock_balances doesn't distinguish "reserved for SO" vs "available"
- ⚠️ **No negative balance prevention**: Stock can go negative (as seen in DO confirm code)

**Status**: HEALTHY (Basic implementation sound)

**Recommendation**: Add `reserved_qty` column to stock_balances when implementing Phase-2 SO stock reservation

---

### Stage 6: Sales Order Fulfillment Status
**Model**: `FulfillmentStatusService`  
**Controller**: `SalesOrders::view()`

**Data Flow**:
```
Sales Order (with lines)
  ├─ [FulfillmentStatusService::getFulfillmentStatus()]
       ├─ Gets ordered qty from sales_order_lines
       ├─ Maps to purchase_order_lines via sales_order_line_po_map
       ├─ Sums GRN receipts via purchase_grn_lines
       └─ Computes:
             - received_qty = SUM(purchase_grn_lines.qty_received) WHERE po_line_id IN (...)
             - orderStatus = [NOT_READY | PARTIAL_READY | READY]
                             
Sales Order View (enriched with:)
  ├─ Fulfillment status badge (read-only)
  ├─ Stock shortage warning (if hasShortage)
  ├─ Line-level: available_qty, shortage_qty (Phase-1)
  └─ No database writes (Phase-1 READ-ONLY)
```

**Findings**:
- ✅ **Fulfillment status computed**: Correctly aggregates received from GRN
- ✅ **PO-to-SO mapping used**: Respects sales_order_line_po_map
- ✅ **Read-only**: Phase-1 implementation is informational only
- ✅ **Partial receipt support**: Shows PARTIAL_READY if some stock received
- ⚠️ **Phase-1 limitation**: No enforcement - SO can ship despite NOT_READY status
- ⚠️ **No delivery_date field**: FulfillmentStatusService doesn't check PO delivery_date vs today

**Status**: HEALTHY (Phase-1 Implementation)

**Recommendation**: Phase-2 should prevent DO creation if fulfillment status = NOT_READY

---

### Stage 7: Sales Order → Delivery Order
**Model**: `DeliveryOrderModel`, `DeliveryOrderLineModel`  
**Service**: `DeliveryOrderService`  
**Controller**: `DeliveryOrders`

**Data Flow**:
```
Sales Order (status='draft' or confirmed')
  ├─ [User clicks "Create Delivery Order"]
  ├─ [DeliveryOrderService::createDraftFromSalesOrder()]
       ├─ Gets fulfillment status via FulfillmentStatusService
       ├─ For each SO line with ready_qty > 0:
       │   └─ Creates DO line with qty_to_ship = ready_qty
       └─ Creates DO with status='draft'
            ↓
Delivery Order (delivery_orders table)
  ├─ do_number (auto)
  ├─ sales_order_id (foreign key)
  ├─ status = 'draft'
  ├─ created_at = NOW()
  └─ No warehouse/location fields
     ↓
Delivery Order Lines (delivery_order_lines table)
  ├─ delivery_order_id (foreign key)
  ├─ sales_order_line_id (foreign key)
  ├─ product_id, variant_id ✅
  ├─ quantity_ordered (from SO line)
  ├─ ready_qty (from fulfillment calculation)
  ├─ qty_to_ship (default = ready_qty, user-editable)
  └─ created_at = NOW()
```

**Findings**:
- ✅ **Ready qty calculation**: Uses FulfillmentStatusService to determine available qty
- ✅ **Draft status**: Only includes lines that have stock (ready_qty > 0)
- ✅ **User can adjust**: qty_to_ship can be reduced before confirmation
- ✅ **Validation**: validateQtyToShip() checks qty_to_ship ≤ ready_qty
- ⚠️ **No stock check at creation**: ready_qty based on FulfillmentStatusService (not live stock)
- ⚠️ **No warehouse assignment**: DO doesn't track which warehouse items coming from
- ⚠️ **No backorder tracking**: Lines with ready_qty=0 silently excluded

**Status**: HEALTHY (Draft-only phase)

**Recommendation**: Add warehouse_id & location_id to deliver_order_lines for audit trail

---

### Stage 8: Delivery Order Confirmation → Stock Deduction
**Service**: `DeliveryOrderService::confirm()`  
**Controller**: `DeliveryOrders::confirm()`

**Critical Code** (DeliveryOrderService lines 266-330):
```php
// For each DO line:
foreach ($do['lines'] as $line) {
    $qtyToShip = (float)($line['qty_to_ship'] ?? 0);
    
    // Decrement stock_balances across available rows
    $remainingToShip = $qtyToShip;
    $balanceRows = $this->db->table('stock_balances')
        ->where('product_id', $productId)
        ->orderBy('quantity', 'DESC')  // ← Deplete high-qty rows first
        ->get()
        ->getResultArray();
    
    foreach ($balanceRows as $balance) {
        if ($remainingToShip <= 0) break;
        
        $availableQty = (float)($balance['quantity'] ?? 0);
        if ($availableQty <= 0) continue;
        
        $deductQty = min($availableQty, $remainingToShip);
        $newQty = $availableQty - $deductQty;
        
        // UPDATE stock_balances
        $this->db->table('stock_balances')
            ->where('id', (int)$balance['id'])
            ->update(['quantity' => $newQty]);
        
        // CREATE stock_movements (negative entry)
        $this->db->table('stock_movements')->insert([
            'qty_change' => -$deductQty,
            'movement_type' => 'shipment',
            'reference_type' => 'delivery_order',
            'reference_id' => $doId,
        ]);
        
        $remainingToShip -= $deductQty;
    }
    
    // If still remaining: CREATE negative balance ← ALLOWS OVER-SHIPPING
    if ($remainingToShip > 0) {
        $this->db->table('stock_balances')->insert([
            'quantity' => -$remainingToShip,  // ← NEGATIVE STOCK
            ...
        ]);
    }
}

// After all lines: Update sales_orders.status = 'shipped'
$soModel->update($do['sales_order_id'], ['status' => 'shipped']);
```

**Findings**:
- ✅ **Multi-warehouse depletion**: Correctly handles stock spread across warehouses
- ✅ **Audit trail created**: Every movement recorded in stock_movements
- ✅ **SO status updated**: Links SO status to DO confirmation
- ⚠️ **NO VALIDATION**: Does NOT check if qty_to_ship ≤ available_qty before deduction
- ⚠️ **ALLOWS NEGATIVE STOCK**: If qty_to_ship > available_qty, creates negative balance
- ⚠️ **NO over-shipping alert**: User gets no warning before allowing negative stock
- ⚠️ **Fallback behavior hidden**: Creates negative balance silently

**Status**: CRITICAL GAP  
**Risk Level**: HIGH

**Example Failure Scenario**:
```
Scenario: User ships more than available
  Stock: Product A = 50 units
  DO qty_to_ship: 100 units
  
Current behavior:
  1. Deducts 50 from stock_balances (quantity becomes 0)
  2. Creates negative balance: quantity = -50
  3. Creates stock_movements with qty_change = -100
  4. Updates sales_order.status = 'shipped'
  5. System now shows: -50 units in stock
  6. Next GRN of 100 units appears as +100, final = +50 ✓
  
But if no GRN comes: Stock permanently negative ✗
```

**Recommendation** (CRITICAL):
1. Add validation before DO confirmation:
```php
if ($qtyToShip > $availableQty) {
    throw new RuntimeException("Cannot ship $qtyToShip units. Only $availableQty available.");
}
```
2. Add `over_shipped_qty` field to track discrepancies
3. Add approval workflow for over-shipments (flag for manager review)

---

### Stage 9: Vendor Bill Creation & Status
**Model**: `VendorBillModel`  
**Controller**: None found (creation method unclear)

**Data Flow**:
```
Purchase GRN (received)
  ├─ [Vendor sends bill]
  ├─ [User creates Vendor Bill (manually?)]
       ↓
Vendor Bills (vendor_bills table)
  ├─ vendor_id (required)
  ├─ vendor_bill_number
  ├─ bill_date, issue_date
  ├─ total_amount (???)  ← SOURCE UNCLEAR
  ├─ balance
  ├─ status = 'draft'
  └─ NO REFERENCE TO PO or GRN ✗
```

**Findings**:
- ⚠️ **NO PO/GRN reference**: VendorBillModel has NO foreign keys to PO or GRN
- ⚠️ **Bill amount SOURCE UNCLEAR**: No indication whether based on:
  - Ordered qty (PO lines)?
  - Received qty (GRN lines)?
  - Manual entry?
- ⚠️ **No variant tracking**: VendorBillModel has no product/variant detail
- ⚠️ **Payment allocation orphaned**: vendor_payment_allocations links to purchase_order_id but VendorBillModel doesn't
- ⚠️ **Accounting impact**: AccountingPostingService posts bill amounts but doesn't validate against PO/GRN

**Status**: CRITICAL GAP  
**Risk Level**: CRITICAL

**Recommendation** (CRITICAL):
1. Add `po_id` and `grn_id` fields to vendor_bills
2. Add `based_on` field: 'po_qty' | 'grn_qty' | 'manual'
3. Add `is_partial_receipt_bill` (boolean) for partial payment scenarios
4. Create vendor_bill_lines table (line-item detail linked to GRN lines)
5. Add validation:
   - If based_on='po_qty': bill_amount must = SUM(purchase_order_lines.qty * unit_price)
   - If based_on='grn_qty': bill_amount must = SUM(purchase_grn_lines.qty_received * unit_price)
6. Prevent bill posting if amount doesn't match reference

---

### Stage 10: Vendor Bill → Accounting Post
**Service**: `AccountingPostingService::postVendorBill()`

**Code** (AccountingPostingService lines 372-465):
```php
public function postVendorBill(int $vendorBillId): array {
    $bill = $billModel->find($vendorBillId);
    
    // Get bill amount (priority order):
    $amount = $this->resolveFirstPositiveAmount($bill, 
        ['balance', 'amount', 'total_amount', 'grand_total']);
    
    // Find GL accounts
    $debitAccountId = $this->findBillDebitAccount($bill);
    $apId = $this->findAccountIdByCodeOrName(['2000'], ['accounts payable']);
    
    // Create journal entry
    $jeId = $jeModel->insert([
        'entry_date' => $bill['bill_date'],
        'memo' => 'Vendor Bill #' . $vendorBillId,
        'total_debits' => $amount,
        'total_credits' => $amount,
        'source_type' => 'vendor_bill',
        'source_id' => $vendorBillId,
    ]);
    
    // Debit: Expense/Purchase account | Credit: Accounts Payable
    // (Posts $amount to both sides)
}
```

**Findings**:
- ✅ **Transaction control**: Wrapped in db->transBegin/transCommit
- ✅ **Account mapping**: Uses GL account codes/names for robustness
- ⚠️ **Amount source vague**: Uses "first positive" from multiple fields
- ⚠️ **No PO/GRN validation**: Doesn't verify bill amount against PO qty or GRN qty
- ⚠️ **No line-item detail**: Posts header amount only; no breakdown by product/location
- ⚠️ **No multi-currency support yet**: Hardcodes currency_code resolution

**Status**: WORKS but RISKY (no validation against PO/GRN)

**Recommendation**: Enhance to validate bill_amount = expected_amount before posting

---

## Summary Table: All 10 Stages

| Stage | From → To | Status | Critical Gaps | Risk |
|-------|-----------|--------|---------------|------|
| 1 | Quotation → SO | ✅ HEALTHY | SO can be created without stock check | MED |
| 2 | SO → RFQ | ✅ HEALTHY | RFQ qty not limited to SO qty | LOW |
| 3 | RFQ → PO | ✅ HEALTHY | No qty_received ≤ qty constraint | MED |
| 4 | PO → GRN | ✅ HEALTHY | Over-receipt allowed (flagged but not enforced) | MED |
| 5 | GRN → Stock | ✅ HEALTHY | No reserved qty tracking | MED |
| 6 | SO Fulfillment | ✅ HEALTHY | Status is READ-ONLY (Phase-1) | MED |
| 7 | SO → DO | ✅ HEALTHY | DO loses warehouse/location info | LOW |
| 8 | DO Confirmation | 🔴 CRITICAL | ALLOWS NEGATIVE STOCK (no qty validation) | CRITICAL |
| 9 | Vendor Bill Creation | 🔴 CRITICAL | NO PO/GRN reference; bill source unclear | CRITICAL |
| 10 | Bill → Accounting | ⚠️ RISKY | No validation against PO/GRN | HIGH |

---

## Root Cause Analysis

### Why Over-Shipping is Possible?
1. **DO creation uses stale data**: `ready_qty` calculated once, not checked at confirmation
2. **DO confirmation has no validation**: Code allows `qty_to_ship > available_qty`
3. **Fallback behavior creates negative stock**: System silently allows negative balances
4. **No UI warning**: User has no indication they're about to over-ship

### Why Vendor Bill is Disconnected?
1. **Missing schema**: VendorBillModel designed without PO/GRN references
2. **No bill creation UI found**: Unclear how bills are created (may be manual outside the code)
3. **Accounting integration weak**: Posting service doesn't validate against PO/GRN

### Why Over-Receipt is Allowed?
1. **Partial receipt support**: System correctly allows GRN < PO qty
2. **No enforcement**: `over_received_qty` field flags it but doesn't prevent it
3. **No approval workflow**: Exceeding PO qty doesn't trigger review

---

## Recommended Implementation Plan

### Phase-2A: Stock Validation (Critical - Do First)
**Priority**: CRITICAL  
**Effort**: 2-3 hours  
**Risk**: Low (adds validation, doesn't change existing logic)

**Changes**:
1. **Add constraint to purchase_order_lines**:
```sql
ALTER TABLE purchase_order_lines 
ADD CONSTRAINT chk_qty_received_valid 
CHECK (qty_received >= 0 AND qty_received <= qty);
```

2. **Add validation to DeliveryOrderService::confirm()**:
```php
// Before processing lines:
foreach ($do['lines'] as $line) {
    $qtyToShip = (float)($line['qty_to_ship'] ?? 0);
    $availableQty = $this->calculateAvailableQty($productId, $variantId);
    
    if ($qtyToShip > $availableQty) {
        throw new RuntimeException(
            "Cannot ship {$qtyToShip} units of product {$productId}. "
            . "Only {$availableQty} available in stock."
        );
    }
}
```

3. **Add validation to DO user warnings**:
```javascript
// In DO view, before confirmation:
if (qtyToShip > availableQty) {
    showModal('Over-Ship Warning', 
        `Qty ${qtyToShip} exceeds available ${availableQty}. Manager approval required.`);
}
```

---

### Phase-2B: Vendor Bill Linkage (Critical - Do Second)
**Priority**: CRITICAL  
**Effort**: 3-4 hours  
**Risk**: Medium (adds fields, may affect existing bill posting logic)

**Changes**:
1. **Migration: Add PO/GRN references to vendor_bills**:
```sql
ALTER TABLE vendor_bills ADD COLUMN (
    po_id INT NULL,
    grn_id INT NULL,
    based_on ENUM('po_qty', 'grn_qty', 'manual') DEFAULT 'manual',
    is_partial_receipt_bill TINYINT(1) DEFAULT 0
);
ALTER TABLE vendor_bills ADD FOREIGN KEY (po_id) REFERENCES purchase_orders(id);
ALTER TABLE vendor_bills ADD FOREIGN KEY (grn_id) REFERENCES purchase_grns(id);
```

2. **Create vendor_bill_lines table**:
```sql
CREATE TABLE vendor_bill_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_bill_id INT NOT NULL,
    po_line_id INT NULL,
    grn_line_id INT NULL,
    product_id INT,
    variant_id INT NULL,
    qty INT,
    unit_price DECIMAL(18,4),
    line_total DECIMAL(18,4),
    FOREIGN KEY (vendor_bill_id) REFERENCES vendor_bills(id),
    FOREIGN KEY (po_line_id) REFERENCES purchase_order_lines(id),
    FOREIGN KEY (grn_line_id) REFERENCES purchase_grn_lines(id)
);
```

3. **Update VendorBillModel**:
```php
public function lines() {
    return $this->hasMany('App\Models\VendorBillLineModel', 'vendor_bill_id', 'id');
}
```

4. **Create vendor bill from GRN**:
```php
// NewPurchaseGrns::confirmAndCreateBill() after GRN creation
$billData = [
    'vendor_id' => $po['vendor_id'],
    'po_id' => $poId,
    'grn_id' => $grnId,
    'based_on' => 'grn_qty',
    'bill_date' => date('Y-m-d'),
    'total_amount' => 0, // Will sum line items
];
$billId = $billModel->insert($billData);

// For each GRN line: create bill line
foreach ($grnLines as $grnLine) {
    $lineTotal = $grnLine['qty_received'] * $grnLine['unit_cost'];
    $billModel->lines()->insert([
        'vendor_bill_id' => $billId,
        'grn_line_id' => $grnLine['id'],
        'po_line_id' => $grnLine['po_line_id'],
        'qty' => $grnLine['qty_received'],
        'unit_price' => $grnLine['unit_cost'],
        'line_total' => $lineTotal,
    ]);
    $totalAmount += $lineTotal;
}

$billModel->update($billId, ['total_amount' => $totalAmount]);
```

---

### Phase-2C: SO Stock Reservation (Important - Do Third)
**Priority**: IMPORTANT  
**Effort**: 4-5 hours  
**Risk**: Medium (adds reservation layer)

**Changes**:
1. **Add reserved_qty to stock_balances**:
```sql
ALTER TABLE stock_balances ADD COLUMN (
    reserved_qty DECIMAL(18,4) DEFAULT 0,
    available_qty_computed DECIMAL(18,4) AS (quantity - reserved_qty) STORED
);
```

2. **Update SO confirmation to reserve stock**:
```php
// SalesOrders::confirm()
foreach ($soLines as $line) {
    $reserved = $this->reserveStock($line['product_id'], $line['product_variant_id'], $line['quantity']);
    if (!$reserved) {
        throw new RuntimeException("Cannot reserve stock for line {$line['id']}");
    }
}
$soModel->update($soId, ['status' => 'confirmed', 'reserved_at' => now()]);
```

3. **DO confirmation unreserves and deducts**:
```php
// DeliveryOrderService::confirm()
// First, unreserve
$this->unreserveStock($productId, $variantId, $qtyToShip);
// Then, deduct (same as now)
$this->deductStock($productId, $variantId, $qtyToShip);
```

---

### Phase-3: Over-Receipt Approval (Important - Do Later)
**Priority**: IMPORTANT  
**Effort**: 2-3 hours  
**Risk**: Low (adds approval flag, doesn't change receipt logic)

**Changes**:
1. **Add approval to purchase_grn_lines**:
```sql
ALTER TABLE purchase_grn_lines ADD COLUMN (
    over_receipt_approved TINYINT(1) DEFAULT 0,
    approved_by INT NULL,
    approved_at DATETIME NULL
);
```

2. **If over_received_qty > 0, require approval**:
```php
// NewPurchaseGrns::create()
if ($overReceived > 0) {
    // Mark as pending approval
    $grnLineModel->update($grnLineId, [
        'over_receipt_approved' => 0,
        'approved_by' => null,
    ]);
    
    // Notify manager
    $this->sendApprovalNotification($grnId, $overReceived);
    
    // Don't update PO qty_received until approved
    $canUpdateQtyReceived = false;
}

if ($canUpdateQtyReceived) {
    $poLineModel->update($poLineId, ['qty_received' => $newReceived]);
}
```

3. **Add approval endpoint**:
```php
// NewPurchaseGrns::approveOverReceipt($grnLineId)
public function approveOverReceipt($grnLineId) {
    $grnLine = $grnLineModel->find($grnLineId);
    if ($grnLine['over_receipt_approved'] == 1) {
        return response('Already approved');
    }
    
    $grnLineModel->update($grnLineId, [
        'over_receipt_approved' => 1,
        'approved_by' => session('user_id'),
        'approved_at' => now(),
    ]);
    
    // NOW update PO qty_received
    $poLineModel->update($grnLine['po_line_id'], [
        'qty_received' => ... // sum all GRN lines (including this one)
    ]);
}
```

---

## Data Integrity Checklist

Use this to verify system health:

```sql
-- Check 1: No PO lines with qty_received > qty (if constraint added)
SELECT id, qty, qty_received, (qty_received - qty) as overage
FROM purchase_order_lines
WHERE qty_received > qty;
-- Expected: 0 rows (or flag with warning if > 0)

-- Check 2: Verify GRN aggregation matches PO qty_received
SELECT pl.id as po_line_id, pl.qty, pl.qty_received,
       SUM(gl.qty_received) as grn_sum
FROM purchase_order_lines pl
LEFT JOIN purchase_grn_lines gl ON gl.po_line_id = pl.id
GROUP BY pl.id
HAVING pl.qty_received != COALESCE(SUM(gl.qty_received), 0);
-- Expected: 0 rows (check if any mismatch)

-- Check 3: Check for negative stock_balances
SELECT id, product_id, warehouse_id, location_id, quantity
FROM stock_balances
WHERE quantity < 0;
-- Expected: 0 rows (or limited to fallback rows from over-shipment)

-- Check 4: Verify delivery orders haven't shipped more than SO ordered
SELECT do.id as do_id, sl.quantity as so_qty, SUM(dl.qty_to_ship) as do_qty
FROM delivery_orders do
JOIN delivery_order_lines dl ON dl.delivery_order_id = do.id
JOIN sales_order_lines sl ON sl.id = dl.sales_order_line_id
GROUP BY do.id, sl.id
HAVING do_qty > so_qty;
-- Expected: 0 rows (DO qty should never exceed SO qty per line)

-- Check 5: Verify vendor bills have PO/GRN reference
SELECT id, po_id, grn_id
FROM vendor_bills
WHERE po_id IS NULL AND grn_id IS NULL;
-- Expected: 0 rows (all bills should reference source)
```

---

## Testing Scenarios

### Scenario 1: Partial Receipt (Expected to Work ✅)
```
1. Create PO: Product A = 100 units
2. Create GRN #1: 50 units
   → PO line qty_received = 50 ✅
   → stock_balances quantity = 50 ✅
3. Create GRN #2: 30 units
   → PO line qty_received = 80 ✅
   → stock_balances quantity = 80 ✅
4. PO shows: Ordered=100, Received=80, Pending=20 ✅
```

### Scenario 2: Over-Receipt (Should Fail After Fix 🔴)
```
1. Create PO: Product B = 50 units
2. Create GRN: 75 units
   BEFORE FIX: qty_received = 75, over_received_qty = 25 (allowed)
   AFTER FIX: Error "Cannot receive 75 units (max 50)" ✓
```

### Scenario 3: Over-Shipping (Should Fail After Fix 🔴)
```
1. Stock: Product C = 60 units
2. DO qty_to_ship = 100 units
3. Confirm DO
   BEFORE FIX: Deducts 60, creates negative balance = -40 (WRONG)
   AFTER FIX: Error "Cannot ship 100 units (only 60 available)" ✓
```

### Scenario 4: Vendor Bill Accuracy (Should Fail After Fix 🔴)
```
1. PO: 100 units @ $10 = $1000
2. GRN: 90 units received
3. Create bill
   BEFORE FIX: Bill amount = ??? (source unclear)
   AFTER FIX: Bill auto-created from GRN: 90 × $10 = $900 ✓
             (or manager can override if partial payment)
```

---

## Architecture Improvements (Optional - Phase 3+)

### 1. Event-Driven Aggregation
Instead of updating PO qty_received on GRN creation, use domain events:
```php
// When GRN line created
$eventBus->dispatch(new GrnLineCreatedEvent($grnLineId, $qtyReceived, $poLineId));

// Listen and aggregate
$eventListener->handle(GrnLineCreatedEvent $event) {
    $this->aggregateQtyReceived($event->poLineId);
}
```

**Benefits**:
- Loose coupling between GRN and PO
- Easy to add new aggregations (e.g., over-receipt workflow)
- Audit trail of what triggered what

### 2. Materialized Views for PO Status
Create a summary table:
```sql
CREATE TABLE purchase_order_summary (
    po_id INT PRIMARY KEY,
    total_ordered DECIMAL(18,4),
    total_received DECIMAL(18,4),
    total_over_received DECIMAL(18,4),
    pending_qty DECIMAL(18,4) AS (total_ordered - total_received),
    status ENUM('open', 'partial', 'complete', 'over_received'),
    last_updated DATETIME,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id)
);
```

**Benefits**:
- O(1) lookup vs O(n) sum every time
- Single source of truth for PO status
- Triggers can keep it in sync

### 3. Stock Reservation State Machine
Formalize stock lifecycle:
```
AVAILABLE → RESERVED → SHIPPED → DELIVERED
           ↓
         CANCELLED (unreserve)
```

With state-specific operations:
- `reserve()`: Move qty from available to reserved
- `unreserve()`: Move qty back to available (if DO cancelled)
- `ship()`: Move qty from available to shipped
- `deliver()`: Final deduction from available

---

## Compliance with Corelynk Law

**Reference**: [CORELYNK_LAW_TO_FOLLOW.MD](CORELYNK_LAW_TO_FOLLOW.MD)

| Module | Owns | Audit Finding |
|--------|------|--------------|
| **Inventory** | Stock qty, movements, locations | ✅ Correctly maintains stock_balances & stock_movements |
| **Products** | Master data, units, type | ✅ Product and variant metadata properly tracked |
| **Purchase** | RFQs, POs, GRNs, (bill docs) | ⚠️ GRN aggregation OK, but VendorBillModel disconnected |
| **Sales** | Quotations, SOs, invoices | ⚠️ SO created without stock check (Phase-1 limitation) |
| **Accounting** | Ledger posting | ⚠️ VendorBill posting not validated against PO/GRN |

**Recommendation**: Vendor billing logic should remain in Purchase module, not Accounting. Current design violates separation by having VendorBillModel isolated.

---

## Conclusion

**System Health**: **MODERATE** 🟡
- Strong in data structure and transaction safety
- Weak in validation and constraint enforcement
- Critical gaps in vendor billing and over-shipment prevention

**Immediate Actions** (This Week):
1. Add qty_received ≤ qty constraint to purchase_order_lines
2. Add validation to DeliveryOrderService to prevent over-shipments
3. Add PO/GRN references to vendor_bills table

**Short-term** (Next 2 Weeks):
4. Implement vendor_bill_lines table for line-item detail
5. Add stock reservation layer to SO confirmation
6. Create approval workflow for over-receipts

**Long-term** (Phase-3):
7. Implement event-driven aggregation
8. Create materialized views for PO status
9. Build stock reservation state machine

---

**Report Generated**: $(date)  
**Audit Scope**: Quotation → SO → RFQ → PO → GRN → Stock → DO → VendorBill  
**Database**: corelynk_db  
**Framework**: CodeIgniter 4  
**Recommendation**: Schedule 6-8 hour implementation sprint for Phase-2A & 2B fixes
