# Phase-3: Sales Order Fulfillment Tracking

## Overview
Phase-3 implements **READ-ONLY** sales order fulfillment tracking that derives fulfillment status from existing data without any database writes.

The system tracks the journey of ordered items through the procurement process:
```
Sales Order → Purchase Orders (via SO-PO mapping) → GRN Receipts → Fulfillment Status
```

## Architecture

### 1. FulfillmentStatusService (`app/Services/FulfillmentStatusService.php`)

**Purpose**: Derive fulfillment status from existing PO mappings and GRN receipts

**Key Method**:
```php
public function getSalesOrderFulfillment($salesOrderId)
```

Returns:
```php
{
  orderStatus: 'NOT_READY' | 'PARTIAL_READY' | 'READY',
  lines: [
    {
      sales_order_line_id,
      product_id,
      product_name,
      is_stockable,
      ordered_qty,
      incoming_qty,      // Sum of PO quantities
      received_qty,      // Sum of GRN quantities
      pending_qty,       // ordered_qty - received_qty
      ready_to_ship_qty, // min(received_qty, ordered_qty)
      po_details: [      // PO reference information
        {
          po_id,
          po_number,
          po_line_id,
          po_qty
        }
      ]
    }
  ]
}
```

### 2. Quantity Derivation Logic

For each sales order line:

| Quantity | Formula | Source |
|----------|---------|--------|
| **ordered_qty** | SO line quantity | `sales_order_lines.quantity` |
| **incoming_qty** | Sum of mapped PO quantities | `sales_order_line_po_map` → `purchase_order_lines.quantity` |
| **received_qty** | Sum of GRN quantities for mapped POs | `purchase_grn_lines.qty_received` |
| **pending_qty** | ordered_qty - received_qty | Derived |
| **ready_to_ship_qty** | min(received_qty, ordered_qty) | Derived |

### 3. Order Status Derivation

Order status is calculated based on **stockable items only** (non-stockable: services, virtual products):

| Status | Rule |
|--------|------|
| **READY** | All stockable lines have `received_qty >= ordered_qty` |
| **PARTIAL_READY** | Some stockable lines have `received_qty > 0` but not all complete |
| **NOT_READY** | All stockable lines have `received_qty = 0` |

## Data Flow

### Creating PO Mappings
When "Auto-Create PO Drafts" is used, the system creates records in `sales_order_line_po_map`:

```sql
INSERT INTO sales_order_line_po_map
(sales_order_id, sales_order_line_id, purchase_order_id, purchase_order_line_id)
VALUES (2, 5, 10, 42)
```

This links:
- Sales Order Line 5 (ordered 100 units)
- ↓
- Purchase Order Line 42 (ordered 100 units from vendor)

### Recording Receipts
When goods are received, GRN records are created:

```sql
INSERT INTO purchase_grn_lines
(grn_id, po_line_id, qty_received)
VALUES (1, 42, 50)  -- Partial receipt of 50 units
```

### Deriving Fulfillment Status
The service queries in sequence:

1. **Get SO lines** (with product type/stockable status)
2. **Find mapped POs** via `sales_order_line_po_map`
3. **Sum incoming quantities** from mapped PO lines
4. **Sum received quantities** from GRN records
5. **Derive all other quantities** (pending, ready-to-ship)
6. **Calculate order status** based on fulfillment completeness

## Controller Integration

File: `app/Controllers/SalesOrders.php` → `view($id)` method

The controller:
1. Loads inventory availability (Phase-1)
2. **Calls FulfillmentStatusService** to get fulfillment data
3. Merges fulfillment data into line records
4. Passes fulfillment status to view

```php
$fulfillmentService = new \App\Services\FulfillmentStatusService();
$fulfillmentData = $fulfillmentService->getSalesOrderFulfillment($id);

// Merge into lines
foreach ($data['lines'] as &$ln) {
    $soLineId = $ln['id'] ?? null;
    if ($soLineId && isset($fulfillmentMap[$soLineId])) {
        $fLine = $fulfillmentMap[$soLineId];
        $ln['incoming_qty'] = $fLine['incoming_qty'];
        $ln['received_qty'] = $fLine['received_qty'];
        $ln['pending_qty'] = $fLine['pending_qty'];
        $ln['ready_to_ship_qty'] = $fLine['ready_to_ship_qty'];
        $ln['po_details'] = $fLine['po_details'];
    }
}

$data['fulfillmentStatus'] = $fulfillmentData['orderStatus'];
```

## View Enhancements

File: `app/Views/sales_orders/view.php`

### Order-Level Badge
Shows overall fulfillment readiness:
- **Ready to Ship** (green): All items received
- **Partially Ready** (yellow): Some items received
- **Not Ready** (red): No items received yet

### Line-Level Columns
Added three new columns to the lines table:

| Column | Shows | Color Coding |
|--------|-------|--------------|
| **Incoming** | Sum of all mapped PO quantities | Gray (informational) |
| **Received** | Sum of GRN receipts | **Green** when > 0 |
| **Pending** | Remaining quantity needed | **Yellow** when > 0 |

### Tooltips
- **Incoming column**: Shows linked PO numbers and quantities on hover
  - Example: `POs: PO-RFQ-000010 (100.00), PO-RFQ-000011 (50.00)`
- **Order badge**: Shows fulfillment readiness reason

## STRICT CONSTRAINTS (ENFORCED)

✅ **READ-ONLY**: No database writes except reading existing data
- No updates to SO status
- No inventory modifications
- No stock reservations
- No PO confirmations

✅ **DERIVED ONLY**: All quantities calculated from existing data
- No stored fulfillment status in DB
- No cache/persistence layer
- Status recalculated on every view

✅ **NO AUTOMATION**: Manual user actions required
- User must click "Auto-Create PO Drafts"
- User must receive goods via GRN
- No auto-shipping or auto-invoicing

✅ **GRACEFUL DEGRADATION**: Handles missing data
- SO lines with no mapped POs: incoming=0, received=0
- Non-stockable products: excluded from status calculation
- Service products: always considered ready

## Database Schema

### sales_order_line_po_map
```sql
CREATE TABLE sales_order_line_po_map (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sales_order_id INT NOT NULL,
  sales_order_line_id INT NOT NULL,
  purchase_order_id INT NOT NULL,
  purchase_order_line_id INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_so_id (sales_order_id),
  KEY idx_so_line_id (sales_order_line_id),
  KEY idx_po_id (purchase_order_id),
  KEY idx_po_line_id (purchase_order_line_id),
  UNIQUE KEY unique_mapping (sales_order_line_id, purchase_order_line_id)
);
```

### Related Tables (existing)
- **sales_orders**: SO header data
- **sales_order_lines**: Individual SO line items
- **purchase_orders**: PO header data
- **purchase_order_lines**: Individual PO line items
- **purchase_grns**: Goods Received Note header
- **purchase_grn_lines**: GRN line items with `qty_received`

## Example Scenario

### Step 1: Create Sales Order
```
Sales Order #2: Damascus Steel Billets
  Line 1: 100 units ordered
  Line 2: 50 units ordered
```

### Step 2: User Creates PO Drafts
Button: "Auto-Create PO Drafts" → Creates:
```
PO RFQ-000010: 100 units to Vendor A
PO RFQ-000011: 50 units to Vendor B

mapping table records:
  SO Line 1 → PO Line 42 (100 units)
  SO Line 2 → PO Line 43 (50 units)
```

### Step 3: View Fulfillment Status
At this point:
- **Order Status**: NOT_READY (nothing received yet)
- Line 1: incoming=100, received=0, pending=100
- Line 2: incoming=50, received=0, pending=50

### Step 4: Receive Partial Goods
GRN created for PO RFQ-000010:
```
GRN-001: 50 units received (partial)
```

### Step 5: View Updated Status
- **Order Status**: PARTIAL_READY (Line 1 partially filled)
- Line 1: incoming=100, received=50, pending=50
- Line 2: incoming=50, received=0, pending=50

### Step 6: Receive Remaining Goods
GRNs created for remaining:
```
GRN-002: 50 units received (completes Line 1)
GRN-003: 50 units received (completes Line 2)
```

### Step 7: Final Status
- **Order Status**: READY ✅
- Line 1: incoming=100, received=100, pending=0
- Line 2: incoming=50, received=50, pending=0

Now the SO is ready to ship!

## Error Handling

Service catches exceptions gracefully:
- Missing mapping table: Returns empty fulfillment data
- GRN table errors: Returns 0 received quantities
- DB connection issues: Returns NOT_READY status
- Invalid SO ID: Returns empty fulfillment structure

All errors logged via CodeIgniter logger for debugging.

## Performance Considerations

### Query Efficiency
- Single query per SO line for PO mappings
- Single query to sum GRN quantities per set of PO lines
- Proper indexing on mapping table

### Caching Strategy
- NO caching (status recalculated on every view)
- Ensures fulfillment data always reflects current state
- Real-time accuracy over performance

### Scalability
Service designed for typical SO sizes (10-100 lines per order).
For extremely large SOs (1000+ lines), consider:
- Implementing optional result caching with TTL
- Batching GRN queries
- Adding database view for common queries

## Future Enhancements (NOT Implemented)

🚫 NOT IN SCOPE for Phase-3:
- Auto-complete SO when fulfilled
- Payment tracking based on fulfillment %
- Partial shipping/invoicing
- Fulfillment exceptions/alerts
- Historical fulfillment tracking
- Forecasting/ETAs based on PO status

These should be Phase-4+ features if needed.

## Testing the System

### Test Case 1: Complete Fulfillment
1. Create SO with items
2. Create POs via auto-create button
3. Receive all goods via GRN
4. Verify status shows "Ready to Ship"

### Test Case 2: Partial Fulfillment
1. Create SO with 2 lines (100 + 50 units)
2. Create POs
3. Receive only first 50 units
4. Verify status shows "Partially Ready"
5. Receive remaining units
6. Verify status shows "Ready to Ship"

### Test Case 3: No Fulfillment
1. Create SO
2. Create POs (but don't receive)
3. Verify status shows "Not Ready"
4. No GRN records → 0 received qty

### Test Case 4: Non-Stockable Items
1. Create SO with service items only
2. Verify status shows "Ready" (services excluded from status calc)
3. Add stockable item, create PO but no GRN
4. Verify status shows "Not Ready" (now has pending stock item)
