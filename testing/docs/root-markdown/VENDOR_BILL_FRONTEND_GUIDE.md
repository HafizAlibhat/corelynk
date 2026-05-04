# Vendor Bill Frontend Integration Guide

## Overview

This guide shows how to integrate the Vendor Bill endpoints into your UI.

---

## 1. Display "Create Bill" Button on PO Page

### API Endpoint
```
POST /new-purchase-orders/create-bill/{po_id}
```

### Button Visibility
Show button only when:
- PO status = 'confirmed' OR
- PO status = 'partial_received' OR  
- PO status = 'open'

```javascript
// In your PO detail view
if (['confirmed', 'partial_received', 'open'].includes(po.status)) {
    // Show "Create Vendor Bill" button
}
```

### Sample Button HTML
```html
<button id="createBillBtn" class="btn btn-primary" data-po-id="1">
    <i class="bi bi-receipt"></i> Create Vendor Bill
</button>
```

### Click Handler
```javascript
document.getElementById('createBillBtn').addEventListener('click', async () => {
    const poId = this.dataset.poId;
    
    if (!confirm('Create vendor bill for this PO?')) {
        return;
    }
    
    try {
        const response = await fetch(`/new-purchase-orders/create-bill/${poId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✓ Bill created! ID: ' + data.bill_id);
            // Redirect to bill detail page
            window.location.href = `/vendor-bills/${data.bill_id}`;
        } else {
            alert('✗ Error: ' + data.error);
        }
    } catch (error) {
        alert('✗ Request failed: ' + error.message);
    }
});
```

---

## 2. Display Vendor Bill Detail Page

### API Endpoint
```
GET /vendor-bills/{bill_id}
```

### Response Structure
```json
{
  "success": true,
  "data": {
    "bill": {
      "id": 1,
      "vendor_id": 5,
      "po_id": 1,
      "vendor_bill_number": null,
      "bill_date": "2026-02-16",
      "total_amount": 20000.00,
      "balance": 20000.00,
      "status": "draft",
      "based_on": "po_qty",
      "currency_code": "PKR",
      "created_at": "2026-02-16 14:30:00"
    },
    "lines": [
      {
        "id": 1,
        "vendor_bill_id": 1,
        "po_line_id": 1,
        "product_id": 10,
        "variant_id": null,
        "qty": 10.0000,
        "unit_price": 1000.0000,
        "line_total": 10000.0000,
        "product_name": "13\" Tactical Knife",
        "product_code": "KS-80034"
      },
      {
        "id": 2,
        "vendor_bill_id": 1,
        "po_line_id": 2,
        "product_id": 20,
        "variant_id": null,
        "qty": 5.0000,
        "unit_price": 2000.0000,
        "line_total": 10000.0000,
        "product_name": "Combat Gloves",
        "product_code": "GL-50001"
      }
    ]
  }
}
```

### Sample Table Display
```html
<div id="billDetail">
    <h3>Vendor Bill #<span id="billId"></span></h3>
    
    <div class="row">
        <div class="col-md-6">
            <p><strong>Status:</strong> <span id="billStatus" class="badge"></span></p>
            <p><strong>Bill Date:</strong> <span id="billDate"></span></p>
            <p><strong>Total Amount:</strong> <span id="billTotal"></span></p>
        </div>
        <div class="col-md-6">
            <p><strong>Balance:</strong> <span id="billBalance"></span></p>
            <p><strong>Vendor:</strong> <span id="vendorName"></span></p>
            <p><strong>Based On:</strong> <span id="basedOn"></span></p>
        </div>
    </div>
    
    <table class="table table-sm">
        <thead>
            <tr>
                <th>Product</th>
                <th>Code</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Line Total</th>
            </tr>
        </thead>
        <tbody id="billLines">
        </tbody>
    </table>
    
    <div id="actions">
        <!-- Confirm button appears if status = draft -->
        <button id="confirmBillBtn" class="btn btn-success">
            <i class="bi bi-check-circle"></i> Confirm & Post
        </button>
        
        <!-- Cancel button appears if status = draft -->
        <button id="cancelBillBtn" class="btn btn-danger">
            <i class="bi bi-x-circle"></i> Cancel
        </button>
    </div>
</div>
```

### JavaScript to Load & Display
```javascript
async function loadBill(billId) {
    const response = await fetch(`/vendor-bills/${billId}`);
    const data = await response.json();
    
    if (!data.success) {
        alert('Error: ' + data.error);
        return;
    }
    
    const bill = data.data.bill;
    const lines = data.data.lines;
    
    // Fill header
    document.getElementById('billId').textContent = bill.id;
    document.getElementById('billStatus').textContent = bill.status.toUpperCase();
    document.getElementById('billDate').textContent = formatDate(bill.bill_date);
    document.getElementById('billTotal').textContent = formatCurrency(bill.total_amount, bill.currency_code);
    document.getElementById('billBalance').textContent = formatCurrency(bill.balance, bill.currency_code);
    document.getElementById('basedOn').textContent = bill.based_on;
    
    // Status badge color
    const badge = document.getElementById('billStatus');
    badge.className = 'badge bg-' + (bill.status === 'draft' ? 'warning' : 'success');
    
    // Fill lines table
    const tbody = document.getElementById('billLines');
    tbody.innerHTML = '';
    
    lines.forEach(line => {
        const row = tbody.insertRow();
        row.innerHTML = `
            <td>${line.product_name}</td>
            <td>${line.product_code || '-'}</td>
            <td>${line.qty}</td>
            <td>${formatCurrency(line.unit_price, bill.currency_code)}</td>
            <td>${formatCurrency(line.line_total, bill.currency_code)}</td>
        `;
    });
    
    // Show/hide actions based on status
    if (bill.status === 'draft') {
        document.getElementById('confirmBillBtn').style.display = 'inline-block';
        document.getElementById('cancelBillBtn').style.display = 'inline-block';
    } else {
        document.getElementById('confirmBillBtn').style.display = 'none';
        document.getElementById('cancelBillBtn').style.display = 'none';
    }
}
```

---

## 3. Confirm Bill (Post to Accounting)

### API Endpoint
```
POST /vendor-bills/{bill_id}/confirm
```

### Request
```javascript
const response = await fetch(`/vendor-bills/${billId}/confirm`, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});
```

### Response
```json
{
  "success": true,
  "bill_id": 1,
  "posted_entry_id": 42,
  "message": "Vendor bill confirmed and posted to accounting"
}
```

### Success Message
```javascript
if (data.success) {
    alert('✓ Bill confirmed and posted to accounting!\n' + 
          'Journal Entry: #' + data.posted_entry_id);
    // Reload bill to show new status
    loadBill(billId);
}
```

---

## 4. Display Vendor Ledger

### API Endpoint
```
GET /vendors/ledger/{vendor_id}
```

### Response Structure
```json
{
  "success": true,
  "data": {
    "vendor": {
      "id": 5,
      "name": "Zargham - WB",
      "email": "zargham@example.com",
      "phone": "03001234567",
      "address": "Karachi, Pakistan"
    },
    "transactions": [
      {
        "date": "2026-02-10",
        "doc_type": "Bill",
        "doc_id": "VB1",
        "doc_number": "VB-0001",
        "po_id": 1,
        "debit": 20000.00,
        "credit": 0.00,
        "running_balance": 20000.00,
        "status": "confirmed",
        "currency": "PKR"
      },
      {
        "date": "2026-02-12",
        "doc_type": "Payment",
        "doc_id": "VP5",
        "doc_number": "CHQ-123",
        "po_id": 1,
        "debit": 0.00,
        "credit": 10000.00,
        "running_balance": 10000.00,
        "status": "posted",
        "currency": "PKR"
      }
    ],
    "final_balance": 10000.00,
    "last_updated": "2026-02-16 14:45:00"
  }
}
```

### Sample Ledger Table
```html
<div id="vendorLedger">
    <h3>Vendor Ledger: <span id="vendorName"></span></h3>
    
    <div class="alert alert-info">
        <strong>Current Balance:</strong> 
        <span id="finalBalance"></span>
    </div>
    
    <table class="table table-sm table-striped">
        <thead class="table-light">
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Document</th>
                <th>Debit</th>
                <th>Credit</th>
                <th>Running Balance</th>
            </tr>
        </thead>
        <tbody id="ledgerLines">
        </tbody>
    </table>
</div>
```

### JavaScript to Load & Display
```javascript
async function loadVendorLedger(vendorId) {
    const response = await fetch(`/vendors/ledger/${vendorId}`);
    const data = await response.json();
    
    if (!data.success) {
        alert('Error: ' + data.error);
        return;
    }
    
    const vendor = data.data.vendor;
    const transactions = data.data.transactions;
    const finalBalance = data.data.final_balance;
    const currency = transactions[0]?.currency || 'PKR';
    
    // Fill header
    document.getElementById('vendorName').textContent = vendor.name;
    document.getElementById('finalBalance').textContent = 
        `${currency} ${formatNumber(finalBalance)}`;
    
    // Fill table
    const tbody = document.getElementById('ledgerLines');
    tbody.innerHTML = '';
    
    transactions.forEach(txn => {
        const row = tbody.insertRow();
        const debitClass = txn.debit > 0 ? 'text-danger' : '';
        const creditClass = txn.credit > 0 ? 'text-success' : '';
        const balanceClass = txn.running_balance > 0 ? 'text-danger' : 'text-success';
        
        row.innerHTML = `
            <td>${formatDate(txn.date)}</td>
            <td>
                <span class="badge bg-${txn.doc_type === 'Bill' ? 'warning' : 'info'}">
                    ${txn.doc_type}
                </span>
            </td>
            <td>
                <a href="/${txn.doc_type.toLowerCase()}-${txn.doc_id}">
                    ${txn.doc_number}
                </a>
            </td>
            <td class="${debitClass}">
                ${txn.debit > 0 ? formatNumber(txn.debit) : '-'}
            </td>
            <td class="${creditClass}">
                ${txn.credit > 0 ? formatNumber(txn.credit) : '-'}
            </td>
            <td class="${balanceClass}">
                ${formatNumber(txn.running_balance)}
            </td>
        `;
    });
}

function formatDate(dateStr) {
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', {year: 'numeric', month: '2-digit', day: '2-digit'});
}

function formatNumber(num) {
    return parseFloat(num).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function formatCurrency(num, currency = 'PKR') {
    return currency + ' ' + formatNumber(num);
}
```

---

## 5. Display Vendor Balance on PO Page

The PO detail response already includes vendor balance:

```javascript
// From GET /new-purchase-orders/{po_id}
const response = await fetch(`/new-purchase-orders/${poId}`);
const data = await response.json();
const po = data.data.po;

// Vendor balance included:
console.log(po.vendor_balance);
// {
//   "total_payable": 35000.00,
//   "unpaid_bills": 25000.00,
//   "last_updated": "2026-02-16 14:45:00"
// }
```

### Sample Display
```html
<div id="vendorBalanceCard" class="card">
    <div class="card-header bg-light">
        <strong>Vendor Balance</strong>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p>
                    <strong>Total Payable:</strong><br>
                    <span id="totalPayable" class="text-danger" style="font-size:1.2em;"></span>
                </p>
            </div>
            <div class="col-md-6">
                <p>
                    <strong>Unpaid Bills:</strong><br>
                    <span id="unpaidBills" class="text-warning" style="font-size:1.2em;"></span>
                </p>
            </div>
        </div>
        <small class="text-muted">Last updated: <span id="balanceTime"></span></small>
    </div>
</div>
```

### JavaScript
```javascript
// After loading PO
const balance = po.vendor_balance;
document.getElementById('totalPayable').textContent = 
    `${po.currency} ${formatNumber(balance.total_payable)}`;
document.getElementById('unpaidBills').textContent = 
    `${po.currency} ${formatNumber(balance.unpaid_bills)}`;
document.getElementById('balanceTime').textContent = 
    formatDate(balance.last_updated);
```

---

## Error Handling Examples

### Bill Creation Error
```javascript
const response = await fetch(`/new-purchase-orders/create-bill/${poId}`, {
    method: 'POST'
});

const data = await response.json();

if (!data.success) {
    switch(response.status) {
        case 400:
            alert('Bad Request: ' + data.error);
            // "Bill can only be created from PO with status: confirmed..."
            // "A bill already exists for this PO"
            break;
        case 404:
            alert('PO not found');
            break;
        case 500:
            alert('Server error: ' + data.error);
            break;
    }
}
```

### Confirm Bill Error
```javascript
const response = await fetch(`/vendor-bills/${billId}/confirm`, {
    method: 'POST'
});

const data = await response.json();

if (!data.success) {
    if (response.status === 400) {
        alert('Cannot confirm: ' + data.error);
        // "Only draft bills can be confirmed"
    } else {
        alert('Posting failed: ' + data.error);
        // "Accounts Payable account not configured"
    }
}
```

---

## Full Integration Example

```javascript
class VendorBillManager {
    constructor(poId) {
        this.poId = poId;
        this.billId = null;
    }
    
    async createBill() {
        if (!confirm('Create vendor bill for this PO?')) return;
        
        try {
            const response = await fetch(
                `/new-purchase-orders/create-bill/${this.poId}`,
                { method: 'POST' }
            );
            const data = await response.json();
            
            if (data.success) {
                this.billId = data.bill_id;
                alert('✓ Bill created: #' + this.billId);
                window.location.href = `/vendor-bills/${this.billId}`;
            } else {
                alert('✗ ' + data.error);
            }
        } catch (e) {
            alert('✗ Request failed: ' + e.message);
        }
    }
    
    async confirmBill() {
        if (!confirm('Confirm bill and post to accounting?')) return;
        
        try {
            const response = await fetch(
                `/vendor-bills/${this.billId}/confirm`,
                { method: 'POST' }
            );
            const data = await response.json();
            
            if (data.success) {
                alert('✓ Bill confirmed!\nJournal Entry: #' + data.posted_entry_id);
                location.reload();
            } else {
                alert('✗ ' + data.error);
            }
        } catch (e) {
            alert('✗ Request failed: ' + e.message);
        }
    }
    
    async cancelBill() {
        if (!confirm('Cancel this bill?')) return;
        
        try {
            const response = await fetch(
                `/vendor-bills/${this.billId}/cancel`,
                { method: 'POST' }
            );
            const data = await response.json();
            
            if (data.success) {
                alert('✓ Bill cancelled');
                history.back();
            } else {
                alert('✗ ' + data.error);
            }
        } catch (e) {
            alert('✗ Request failed: ' + e.message);
        }
    }
}

// Usage in HTML
const manager = new VendorBillManager(1);

document.getElementById('createBillBtn').onclick = () => manager.createBill();
document.getElementById('confirmBillBtn').onclick = () => manager.confirmBill();
document.getElementById('cancelBillBtn').onclick = () => manager.cancelBill();
```

---

## Helper Functions

```javascript
// Format date to DD-MM-YYYY
function formatDate(dateStr) {
    const d = new Date(dateStr);
    const pad = (n) => String(n).padStart(2, '0');
    return `${pad(d.getDate())}-${pad(d.getMonth() + 1)}-${d.getFullYear()}`;
}

// Format number with comma separators
function formatNumber(num) {
    return parseFloat(num).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

// Format currency
function formatCurrency(num, currency = 'PKR') {
    return `${currency} ${formatNumber(num)}`;
}

// Show loading indicator
function showLoading(element) {
    element.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
}

// Show error message
function showError(message) {
    const alert = document.createElement('div');
    alert.className = 'alert alert-danger alert-dismissible fade show';
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.insertBefore(alert, document.body.firstChild);
}

// Show success message
function showSuccess(message) {
    const alert = document.createElement('div');
    alert.className = 'alert alert-success alert-dismissible fade show';
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.insertBefore(alert, document.body.firstChild);
}
```

---

**End of Frontend Integration Guide**
