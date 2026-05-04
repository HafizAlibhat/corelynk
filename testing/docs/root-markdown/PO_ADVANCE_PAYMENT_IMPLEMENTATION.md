# Purchase Order Advance Payment Implementation

**Date**: February 11, 2026  
**Feature**: PO-Linked Advance Payments with Proper Accounting Separation

---

## Overview

Allows advance payments directly from Purchase Order page (e.g., 50% advance before delivery) while maintaining strict ERP compliance and preventing overpayment.

### Key Features
✅ Pay advance from PO page  
✅ Validate advance ≤ remaining payable  
✅ Track total advances per PO  
✅ Advance posts to Asset (NOT AP)  
✅ Auto-apply when bill posted  
✅ Clean double-entry accounting  

---

## Database Migration

**File**: `app/Database/Migrations/2026-02-11-000001_AddPoAdvancePaymentFields.php`

```sql
-- Run migration
php spark migrate

-- Adds to vendor_payments table:
ALTER TABLE vendor_payments ADD COLUMN (
    po_id INT NULL AFTER vendor_id,
    payment_type ENUM('advance', 'bill_payment') DEFAULT 'bill_payment' AFTER payment_method
);

CREATE INDEX idx_vendor_payments_po_id ON vendor_payments(po_id);

ALTER TABLE vendor_payments 
ADD CONSTRAINT fk_vendor_payments_po_id 
FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE SET NULL;
```

**Changes**:
- ✅ Adds nullable `po_id` column (existing payments unaffected)
- ✅ Adds `payment_type` ENUM (defaults to 'bill_payment' for existing rows)
- ✅ Creates index for performance
- ✅ Adds FK constraint (with SET NULL on delete)
- ✅ Does NOT modify existing columns

---

## Backend Implementation

### 1. Controller Method: `NewPurchaseOrders::payAdvance()`

**Route**: Add to `app/Config/Routes.php`:
```php
$routes->post('new-purchase-orders/pay-advance/(:num)', 'NewPurchaseOrders::payAdvance/$1');
```

**Validation Logic**:
```php
public function payAdvance($id) {
    // 1. Validate PO exists and status = 'confirmed'
    $po = $poModel->find($poId);
    if ($po['status'] !== 'confirmed') {
        throw new \RuntimeException('Advance payment only for confirmed POs');
    }
    
    // 2. Calculate total advances already paid
    $totalAdvancesPaid = $db->table('vendor_payments')
        ->selectSum('amount')
        ->where('po_id', $poId)
        ->where('payment_type', 'advance')
        ->where('status !=', 'cancelled')
        ->get()->getRow()->amount;
    
    // 3. CRITICAL: Reject if exceeds remaining
    $remainingPayable = $poTotal - $totalAdvancesPaid;
    if ($advanceAmount > $remainingPayable) {
        throw new \RuntimeException(
            "Advance {$advanceAmount} exceeds remaining {$remainingPayable}"
        );
    }
    
    // 4. Insert payment
    $paymentData = [
        'vendor_id' => $po['vendor_id'],
        'po_id' => $poId,
        'payment_type' => 'advance',
        'amount' => $advanceAmount,
        'payment_method' => $paymentMethod,
        'source_account_id' => $sourceAccountId,
        'status' => 'draft',
    ];
    $paymentId = $paymentModel->insert($paymentData);
    
    // 5. Auto-post to accounting
    $accountingService->postVendorPayment($paymentId);
}
```

**Safety Rules**:
- ✅ Only confirmed POs allowed
- ✅ Total advances cannot exceed PO total
- ✅ Does NOT modify PO status or total
- ✅ Does NOT affect stock/GRN
- ✅ Transaction-safe (rollback on error)

---

### 2. Accounting Service: `AccountingPostingService::postVendorPayment()`

**Modified Logic**:

```php
public function postVendorPayment(int $vendorPaymentId): array {
    $payment = $paymentModel->find($vendorPaymentId);
    $paymentType = $payment['payment_type'] ?? 'bill_payment';
    
    // CRITICAL: Different GL accounts based on payment type
    if ($paymentType === 'advance') {
        // Advance payment
        $debitAccountId = $this->findAccountIdByCodeOrName(
            ['1400', '1450'], 
            ['vendor advance', 'advances to vendors']
        );
        $debitLabel = 'Vendor Advance';
    } else {
        // Bill payment
        $debitAccountId = $this->findAccountIdByCodeOrName(
            ['2000'], 
            ['accounts payable']
        );
        $debitLabel = 'Accounts Payable';
    }
    
    // Create journal entry
    $jeId = $jeModel->insert([...]);
    
    // Dr Vendor Advance (or AP)
    $this->insertLine([
        'account_id' => $debitAccountId,
        'debit' => $amount,
        'credit' => 0,
    ]);
    
    // Cr Bank/Cash
    $this->insertLine([
        'account_id' => $sourceAccountId,
        'debit' => 0,
        'credit' => $amount,
    ]);
}
```

---

### 3. Advance Application: `AccountingPostingService::applyAdvancesToBill()`

**Triggered When**: Vendor bill posted

**Logic**:
```php
private function applyAdvancesToBill($billId, $poId, $vendorId, $currency, $fxRate) {
    // Find all posted advances for this PO
    $advances = $db->table('vendor_payments')
        ->where('po_id', $poId)
        ->where('payment_type', 'advance')
        ->where('status', 'posted')
        ->get()->getResultArray();
    
    foreach ($advances as $advance) {
        $amount = $advance['amount'];
        
        // Create journal entry to apply advance
        $jeId = $jeModel->insert([
            'memo' => "Apply advance #{$advance['id']} to bill #{$billId}",
            ...
        ]);
        
        // Dr Accounts Payable (reduce liability)
        $this->insertLine([
            'account_id' => $apId,
            'debit' => $amount,
            'credit' => 0,
        ]);
        
        // Cr Vendor Advance (clear asset)
        $this->insertLine([
            'account_id' => $vendorAdvanceId,
            'debit' => 0,
            'credit' => $amount,
        ]);
    }
}
```

**Result**: AP liability reduced by advance amount automatically

---

## UI Implementation

### PO View Page Modification

**File**: `app/Views/purchase_ui/po_view.php`

**Add Button** (after existing "Receive" button):

```php
<div class="btn-group">
  <a id="backLink" href="<?= site_url('newpurchaseui/rfqpo') ?>" class="btn btn-sm btn-outline-secondary">Back</a>
  <a id="receiveBtn" href="#" class="btn btn-sm btn-outline-success">Receive</a>
  <button id="advanceBtn" type="button" class="btn btn-sm btn-outline-primary" style="display:none;">Pay Advance</button>
  <button id="printBtn" type="button" class="btn btn-sm btn-outline-primary">Print</button>
</div>
```

**Add Modal** (at end of file, before closing script tag):

```html
<!-- Pay Advance Modal -->
<div class="modal fade" id="advanceModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Pay Advance on PO #<span id="modalPoNumber"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="advanceMessage"></div>
        
        <div class="mb-3">
          <label class="form-label fw-bold">PO Total:</label>
          <div class="fs-5" id="modalPoTotal">0.00</div>
        </div>
        
        <div class="mb-3">
          <label class="form-label">Already Paid (Advances):</label>
          <div id="modalAlreadyPaid" class="text-success">0.00</div>
        </div>
        
        <div class="mb-3">
          <label class="form-label fw-bold">Remaining Payable:</label>
          <div class="fs-5 text-primary" id="modalRemainingPayable">0.00</div>
        </div>
        
        <hr>
        
        <form id="advanceForm">
          <div class="mb-3">
            <label for="advanceAmount" class="form-label required">Advance Amount</label>
            <input type="number" class="form-control" id="advanceAmount" step="0.01" min="0.01" required>
            <small class="text-muted">Maximum: <span id="maxAdvance">0.00</span></small>
          </div>
          
          <div class="mb-3">
            <label for="paymentMethod" class="form-label required">Payment Method</label>
            <select class="form-select" id="paymentMethod" required>
              <option value="cash">Cash</option>
              <option value="bank">Bank Transfer</option>
              <option value="cheque">Cheque</option>
            </select>
          </div>
          
          <div class="mb-3">
            <label for="referenceNo" class="form-label">Reference/Receipt No</label>
            <input type="text" class="form-control" id="referenceNo" placeholder="Optional">
          </div>
          
          <div class="mb-3">
            <label for="advanceNotes" class="form-label">Notes</label>
            <textarea class="form-control" id="advanceNotes" rows="2" placeholder="Optional notes"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="submitAdvanceBtn">Pay Advance</button>
      </div>
    </div>
  </div>
</div>
```

**Add JavaScript** (in existing script section):

```javascript
// Advance Payment Functionality
let currentPo = null;
let totalAdvancesPaid = 0;

async function loadAdvanceData(poId) {
  try {
    // Fetch total advances already paid
    const resp = await fetch(`<?= site_url('new-purchase-orders/') ?>${poId}/advances`, {
      headers: {'Accept': 'application/json'}
    });
    const data = await resp.json();
    totalAdvancesPaid = data.total_advances || 0;
    updateAdvanceButton();
  } catch (e) {
    console.error('Failed to load advance data', e);
    totalAdvancesPaid = 0;
  }
}

function updateAdvanceButton() {
  const advanceBtn = document.getElementById('advanceBtn');
  if (!currentPo || !advanceBtn) return;
  
  const status = currentPo.status || '';
  const poTotal = parseFloat(currentPo.total || 0);
  const remaining = poTotal - totalAdvancesPaid;
  
  // Show button only if PO confirmed AND remaining > 0
  if (status === 'confirmed' && remaining > 0) {
    advanceBtn.style.display = 'inline-block';
    advanceBtn.textContent = `Pay Advance (${fmt(remaining)} remaining)`;
  } else {
    advanceBtn.style.display = 'none';
  }
}

document.getElementById('advanceBtn')?.addEventListener('click', () => {
  if (!currentPo) return;
  
  const poTotal = parseFloat(currentPo.total || 0);
  const remaining = poTotal - totalAdvancesPaid;
  const currency = currentPo.currency || '<?= esc($defaultCurrency) ?>';
  
  document.getElementById('modalPoNumber').textContent = currentPo.po_number || currentPo.id;
  document.getElementById('modalPoTotal').textContent = `${currency} ${fmt(poTotal)}`;
  document.getElementById('modalAlreadyPaid').textContent = `${currency} ${fmt(totalAdvancesPaid)}`;
  document.getElementById('modalRemainingPayable').textContent = `${currency} ${fmt(remaining)}`;
  document.getElementById('maxAdvance').textContent = `${currency} ${fmt(remaining)}`;
  
  document.getElementById('advanceAmount').max = remaining;
  document.getElementById('advanceAmount').value = '';
  document.getElementById('advanceForm').reset();
  document.getElementById('advanceMessage').innerHTML = '';
  
  new bootstrap.Modal(document.getElementById('advanceModal')).show();
});

document.getElementById('submitAdvanceBtn')?.addEventListener('click', async () => {
  const form = document.getElementById('advanceForm');
  if (!form.checkValidity()) {
    form.reportValidity();
    return;
  }
  
  const amount = parseFloat(document.getElementById('advanceAmount').value || 0);
  const poTotal = parseFloat(currentPo.total || 0);
  const remaining = poTotal - totalAdvancesPaid;
  
  if (amount <= 0) {
    showAdvanceError('Amount must be greater than zero');
    return;
  }
  
  if (amount > remaining) {
    showAdvanceError(`Amount exceeds remaining payable (${fmt(remaining)})`);
    return;
  }
  
  const submitBtn = document.getElementById('submitAdvanceBtn');
  submitBtn.disabled = true;
  submitBtn.textContent = 'Processing...';
  
  try {
    const payload = {
      amount: amount,
      payment_method: document.getElementById('paymentMethod').value,
      reference_no: document.getElementById('referenceNo').value || null,
      notes: document.getElementById('advanceNotes').value || null,
    };
    
    const resp = await fetch(`<?= site_url('new-purchase-orders/pay-advance/') ?>${currentPo.id}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        '<?= csrf_token() ?>': '<?= csrf_hash() ?>'
      },
      body: JSON.stringify(payload)
    });
    
    const result = await resp.json();
    
    if (result.success) {
      bootstrap.Modal.getInstance(document.getElementById('advanceModal')).hide();
      msg.innerHTML = `<div class="alert alert-success">${result.message}</div>`;
      
      // Refresh advance data
      totalAdvancesPaid = result.total_advances_paid || 0;
      updateAdvanceButton();
    } else {
      showAdvanceError(result.error || 'Failed to process advance payment');
    }
  } catch (e) {
    showAdvanceError('Network error: ' + e.message);
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = 'Pay Advance';
  }
});

function showAdvanceError(message) {
  document.getElementById('advanceMessage').innerHTML = 
    `<div class="alert alert-danger">${message}</div>`;
}

// Call after PO loaded
// Add to existing PO load success handler:
currentPo = p;
loadAdvanceData(id);
updateAdvanceButton();
```

---

## Accounting Examples

### Example 1: Pay 50% Advance on PO

**Scenario**: PO Total = 10,000 PKR

**Action**: Pay 5,000 PKR advance

**Journal Entry** (Auto-created):
```
Date: 2026-02-11
Memo: Vendor Advance Payment #1

Dr  1400 Vendor Advance          5,000.00
    Cr  1050 Bank Account                    5,000.00
```

**Balance Sheet Impact**:
- Assets: Vendor Advance +5,000, Bank -5,000 (Net: 0)
- Liabilities: No change
- Equity: No change

---

### Example 2: Receive Goods, Post Vendor Bill

**Scenario**: 
- PO Total: 10,000 PKR
- Already Paid Advance: 5,000 PKR
- GRN received: 10,000 PKR worth of goods
- Vendor bill created: 10,000 PKR

**Action**: Post vendor bill

**Journal Entry 1** (Bill Posting):
```
Date: 2026-02-11
Memo: Vendor Bill #5

Dr  5100 Purchases              10,000.00
    Cr  2000 Accounts Payable              10,000.00
```

**Journal Entry 2** (Auto-Applied Advance):
```
Date: 2026-02-11
Memo: Apply advance payment #1 to vendor bill #5 (PO #10)

Dr  2000 Accounts Payable        5,000.00
    Cr  1400 Vendor Advance                 5,000.00
```

**Net Result**:
- Assets: Inventory +10,000, Vendor Advance -5,000
- Liabilities: AP +10,000 -5,000 = +5,000
- **Remaining Due to Vendor: 5,000 PKR** ✓

---

### Example 3: Pay Remaining Balance

**Scenario**: After bill posted, pay remaining 5,000 PKR

**Action**: Pay bill

**Journal Entry**:
```
Date: 2026-02-12
Memo: Vendor Payment #2

Dr  2000 Accounts Payable        5,000.00
    Cr  1050 Bank Account                   5,000.00
```

**Final Balance**:
- AP = 0 (fully paid) ✓
- Vendor Advance = 0 (applied) ✓
- Total cash paid = 10,000 (5,000 advance + 5,000 final) ✓

---

## Safety Validations

### 1. Payment Amount Validation
```php
// BEFORE payment creation
$poTotal = (float)$po['total'];
$totalAdvancesPaid = (float)$db->table('vendor_payments')
    ->selectSum('amount')
    ->where('po_id', $poId)
    ->where('payment_type', 'advance')
    ->where('status !=', 'cancelled')
    ->get()->getRow()->amount;

$remainingPayable = $poTotal - $totalAdvancesPaid;

if ($advanceAmount > $remainingPayable) {
    throw new \RuntimeException(
        "Advance amount ({$advanceAmount}) exceeds remaining payable ({$remainingPayable}). "
        . "PO Total: {$poTotal}, Already Paid: {$totalAdvancesPaid}"
    );
}
```

**Prevents**: Paying more than PO total

---

### 2. PO Status Validation
```php
if ($po['status'] !== 'confirmed') {
    throw new \RuntimeException(
        'Advance payment only allowed for confirmed POs. Current status: ' . $po['status']
    );
}
```

**Prevents**: Advance on draft/cancelled POs

---

### 3. Accounting Account Validation
```php
$vendorAdvanceId = $this->findAccountIdByCodeOrName(
    ['1400', '1450'], 
    ['vendor advance', 'advances to vendors', 'vendor prepayment']
);

if ($vendorAdvanceId <= 0) {
    return [
        'success' => false, 
        'message' => 'Vendor Advance account not configured. Please create account with code 1400'
    ];
}
```

**Prevents**: Posting without proper GL account setup

---

### 4. Transaction Isolation
```php
$db->transBegin();
try {
    // 1. Validate PO
    // 2. Calculate advances
    // 3. Insert payment
    // 4. Post to accounting
    
    $db->transCommit();
} catch (\Throwable $e) {
    $db->transRollback();
    throw $e;
}
```

**Prevents**: Partial updates on error

---

## Testing Checklist

### Happy Path
- [ ] Create confirmed PO (total = 10,000)
- [ ] Pay 5,000 advance → Verify payment created, posted to GL
- [ ] Check "Already Paid" shows 5,000
- [ ] Check "Remaining Payable" shows 5,000
- [ ] Try paying 6,000 more → Should fail (exceeds remaining)
- [ ] Receive goods (GRN)
- [ ] Create vendor bill (10,000)
- [ ] Post bill → Verify advance auto-applied
- [ ] Check AP balance = 5,000 (not 10,000) ✓

### Edge Cases
- [ ] Try advance on draft PO → Should fail
- [ ] Try advance on cancelled PO → Should fail
- [ ] Try advance = 0 → Should fail
- [ ] Try advance > PO total → Should fail
- [ ] Pay 5,000, then pay 5,000 again → Should succeed (total = 10,000)
- [ ] Try paying 5,001 when 5,000 remaining → Should fail
- [ ] Cancel advance payment → Should not affect "remaining payable"

### Accounting Verification
- [ ] Advance payment: Dr Vendor Advance, Cr Bank ✓
- [ ] Bill posting: Dr Purchases, Cr AP ✓
- [ ] Advance application: Dr AP, Cr Vendor Advance ✓
- [ ] Final payment: Dr AP, Cr Bank ✓
- [ ] Verify all debits = all credits (balanced) ✓

---

## Chart of Accounts Setup

**Required GL Accounts**:

| Code | Name | Type | Purpose |
|------|------|------|---------|
| 1000 | Cash | Asset | Cash payments |
| 1050 | Bank Account | Asset | Bank transfers |
| 1400 | Vendor Advance | Asset | **Advances to vendors** |
| 2000 | Accounts Payable | Liability | Vendor bills |
| 5100 | Purchases | Expense | Inventory purchases |

**Create Vendor Advance Account** (if missing):
```sql
INSERT INTO chart_of_accounts (code, name, account_type, parent_id, is_active)
VALUES ('1400', 'Vendor Advance', 'asset', NULL, 1);
```

---

## Routes Configuration

Add to `app/Config/Routes.php`:

```php
$routes->post('new-purchase-orders/pay-advance/(:num)', 'NewPurchaseOrders::payAdvance/$1');
$routes->get('new-purchase-orders/(:num)/advances', 'NewPurchaseOrders::getAdvancesSummary/$1');
```

---

## Summary

### What Was Added
✅ Database: `po_id`, `payment_type` to vendor_payments  
✅ Controller: `payAdvance()` method with validation  
✅ Accounting: Payment type-based posting (advance vs bill_payment)  
✅ Accounting: Auto-apply advances when bill posted  
✅ UI: "Pay Advance" button with modal  

### What Was NOT Changed
✅ Existing vendor payment module (untouched)  
✅ PO lifecycle (status, total unchanged by advance)  
✅ GRN/stock logic (not affected)  
✅ Existing vendor_payments rows (migration safe)  

### Compliance
✅ Advance posts to Asset (NOT Accounts Payable)  
✅ Bill payment posts to Accounts Payable  
✅ Advance auto-applied when bill posted  
✅ Cannot overpay (validation enforced)  
✅ Transaction-safe (atomic operations)  
✅ Audit trail maintained (journal entries logged)  

---

**Implementation Status**: READY FOR TESTING  
**Risk Level**: LOW (minimal changes, backward compatible)  
**Estimated Time**: 6-8 hours implementation + testing
