# GRN Partial Receiving System - Architecture & Implementation

## Overview
Build a professional GRN (Goods Receipt Note) system supporting:
- **Partial receipts** - Receive subset of items from a PO
- **Per-product location selection** - Each product can go to different warehouse location
- **Pending tracking** - Track items still pending from a PO
- **Easy re-receipt** - Users can come back and receive remaining items

## Database Schema Changes

### 1. purchase_order_lines - Add Status Tracking
```sql
ALTER TABLE purchase_order_lines ADD COLUMN IF NOT EXISTS
  `receive_status` VARCHAR(20) DEFAULT 'pending' COMMENT 'pending, partially_received, fully_received';
ALTER TABLE purchase_order_lines ADD COLUMN IF NOT EXISTS  
  `fully_received_date` DATETIME NULL COMMENT 'When fully received';
```

**Status Values:**
- `pending` - No items received yet
- `partially_received` - Some items received, more pending
- `fully_received` - All items received

### 2. purchase_grn_lines - Track Location per Product
```sql
ALTER TABLE purchase_grn_lines ADD COLUMN IF NOT EXISTS
  `warehouse_id` INT NULL COMMENT 'Warehouse location for this line';
ALTER TABLE purchase_grn_lines ADD COLUMN IF NOT EXISTS
  `location_id` INT NULL COMMENT 'Warehouse location detail for this line';
```

### 3. Create grn_receipt_history Table
```sql
CREATE TABLE IF NOT EXISTS grn_receipt_history (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  po_id INT NOT NULL,
  po_line_id INT NOT NULL,
  grn_id INT NOT NULL,
  product_id INT NOT NULL,
  variant_id INT NULL,
  qty_pending DECIMAL(15,4),
  qty_received DECIMAL(15,4),
  warehouse_id INT NULL,
  location_id INT NULL,
  received_date DATETIME,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY (po_id, po_line_id),
  KEY (grn_id),
  UNIQUE KEY uniq_grn_line (grn_id, po_line_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Purpose:** Track each receipt event for audit trail

## UI/UX Flow

### Step 1: GRN Form - Show Pending Items
```
PO Search Widget
    ↓
Shows: PO-0006 | Zarghem WB | Pending: 7 items with quantities
    ↓
User selects which products to receive TODAY
    ↓
Each product row shows:
  - Product name / code
  - Ordered qty | Previously Received | Still Pending
  - "Receive Now" field (default = pending qty, user can override)
  - Location dropdown (optional - use default from top if not selected)
    ↓
User can choose:
  a) Receive ALL pending items → GRN marks line as "fully_received"
  b) Receive SOME items → GRN marks line as "partially_received"  
  c) Skip products not arriving → Don't add to GRN
    ↓
Submit → Creates GRN + inventory movements ONLY for selected items
    ↓
PO lines updated:
  - Fully received items → status = "fully_received"
  - Partially received items → status = "partially_received"
  - Skipped items → status remains "pending"
```

### Step 2: Return to GRN for Remaining Items
```
User comes back later when more items arrive
    ↓
PO still shows pending items (e.g., "2 items pending out of 7")
    ↓
User can click "Continue Receiving" on existing PO
    ↓
Form shows ONLY pending items (filters out already fully received)
    ↓
User selects which of remaining items to receive TODAY
    ↓
Submit → Creates new GRN for remaining items
    ↓
grn_receipt_history captured for audit trail
```

## Database-Level Safety (Isolation)

### Constraint: Can't Receive More Than Ordered
```php
// On each GRN line, validate:
qty_received <= (ordered_qty - sum_of_previous_grn_quantities)
```

### Constraint: Status Never Downgrades
```sql
-- pending → partially_received ✓
-- pending → fully_received ✓
-- partially_received → fully_received ✓
-- Never backward
```

### Transaction Safety
All GRN operations wrapped in DB transaction:
1. Insert GRN header
2. Insert GRN lines (with location_id per item)
3. Insert grn_receipt_history records
4. Update purchase_order_lines status
5. Create stock_movements (inventory)
6. **All succeed or all rollback**

## Implementation Files

### 1. Database Migration
`app/Database/Migrations/2025-04-15-000001_AddGrnPartialReceivingSupport.php`

### 2. Updated Controllers
`app/Controllers/NewPurchaseGrns.php` - Enhanced with partial receipt logic

### 3. Updated Views  
`app/Views/purchase_ui/grn.php` - Enhanced form with location selection per product

### 4. New Models
`app/Models/GrnReceiptHistoryModel.php`

### 5. New Service
`app/Services/GrnService.php` - Business logic for partial receipts

### 6. Helper Updates
`app/Helpers/PurchaseOrderHelper.php` - Status calculation functions

## Key Business Logic

### Function: Can We Receive Line?
```php
function canReceivePoLine($poLineId, $qtyAttempt) {
  $orderedQty = $poLine->ordered_qty;
  $previousReceivedQty = sumOfAllGrnQtiesForLine($poLineId);
  $stillPendingQty = $orderedQty - $previousReceivedQty;
  
  return $qtyAttempt <= $stillPendingQty;
}
```

### Function: Update Line Status
```php
function updatePoLineStatus($poLineId) {
  $orderedQty = $poLine->ordered_qty;
  $receivedQty = sumOfAllGrnQtyiesForLine($poLineId);
  
  if ($receivedQty == 0) {
    return 'pending';
  } elseif ($receivedQty < $orderedQty) {
    return 'partially_received';
  } else {
    return 'fully_received'; // Note: allows over-receipt if enabled
  }
}
```

### Example: 2-Part Receipt

**Initial PO:**
```
Product A: Ordered 100 units
  Status: pending
  Received: 0
  Pending: 100
```

**First GRN Creation (Product A arrives first):**
```
User receives 50 units of Product A
GRN created with:
  - qty_received: 50
  - location_id: 5 (e.g., Shelf A1)
  
PO Line Updated:
  Status: partially_received
  Received: 50
  Pending: 50
  
Stock Movement Created:
  +50 units to Shelf A1
```

**Second GRN Creation (Remaining arrives):**
```
User receives 50 more units of Product A
New GRN created with:
  - qty_received: 50
  - location_id: 6 (e.g., Shelf A2 if user chose different location)
  - referenced previous GRN in notes/history
  
PO Line Updated:
  Status: fully_received
  Received: 100
  Pending: 0
  fully_received_date: NOW()
  
Stock Movement Created:
  +50 units to Shelf A2
  
grn_receipt_history logged:
  - Event 1: GRN-001 received 50 units
  - Event 2: GRN-002 received 50 units (pending from GRN-001)
```

## Testing Checklist

### Database Level
- [ ] Transaction isolation: Failed GRN doesn't create half-inventory
- [ ] Status validation: Never downgrades status
- [ ] Qty validation: Can't receive more than ordered
- [ ] Location tracking: Each item knows its warehouse/location
- [ ] Audit trail: All receipts logged in receipt_history

### UI Level
- [ ] Form shows only pending items when editing PO
- [ ] Location dropdown appears for each product
- [ ] "Use default" option works correctly  
- [ ] Can override individual product locations
- [ ] Over-receipt warning shows correctly
- [ ] Pending count updates after each receipt

### Business Logic
- [ ] First receipt receives some items ✓
- [ ] Remaining items show as pending ✓  
- [ ] User can come back and receive rest ✓
- [ ] Status transitions correctly (pending → partially → fully)
- [ ] Qty calculations accurate after partial receipt
- [ ] Inventory movements only for received items
- [ ] No duplicate inventory entries

### Error Handling
- [ ] Can't receive more than available
- [ ] Can't submit empty GRN
- [ ] Missing location validation (if required)
- [ ] Warehouse mismatch prevention
- [ ] Duplicate location assignment prevented

## End-to-End Test Scenario

**Setup:** PO-003 with 3 products (Knife 7, Bone Scales 60, Horn Scales 20)

**Day 1 - Partial Receipt:**
1. User opens GRN form
2. Searches "PO-0006" 
3. Form shows 3 items all pending
4. User receives only:
   - Falcon Knife: 7 units → Location: Shelf A1
   - Bone Scales: 60 units → Location: Shelf A2
5. Skips Horn Scales (not arrived yet)
6. Submits → Creates GRN-001
7. Database state:
   - GRN-001 has 2 lines (Knife 7, Scales 60)
   - Stock increased by 67 units total
   - PO Status:
     - Knife: fully_received ✓
     - Bone Scales: fully_received ✓
     - Horn Scales: pending ✓

**Day 3 - Receive Remaining:**
1. User searches PO-0006 again
2. Form shows ONLY Horn Scales (20 units, pending)
3. User receives all 20 units → Location: Shelf A3
4. Submits → Creates GRN-002
5. Database state:
   - GRN-002 has 1 line (Horn Scales 20)
   - Stock increased by 20 units
   - PO Status:
     - All items marked fully_received
     - fully_received_date set
   - grn_receipt_history shows both events

**Audit Trail:**
```
GRN-001: 2025-04-15 | 67 units | Knife (Shelf A1), Scales (Shelf A2)
GRN-002: 2025-04-18 | 20 units | Horn Scales (Shelf A3) | Pending from GRN-001
```

## Code Quality Standards

✓ No code breaks - Backward compatible
✓ No buggy functions - Each tested independently
✓ Database integrity - Transactions ensure atomicity
✓ Error handling - Try-catch on all DB operations
✓ Audit logging - Every receipt tracked
✓ User-friendly - Clear pending/received status
✓ Professional - Enterprise-grade error messages
