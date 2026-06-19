# Discount Alignment Fix - Exact Code Changes

## File 1: app/Controllers/CustomerInvoices.php

### Location: `pdf()` method, line ~840-920

### Change Summary:
Added discount-level detection logic to clear line-level discounts when invoice has document-only discount.

### Exact Change:

**ADDED CODE** (in pdf() method, after line 909 where lines are fetched):

```php
// Calculate total line-level discount to determine if discounts are truly line-level or document-level
$totalLineDiscountAmount = 0.0;
foreach ($lines as $ln) {
    $discAmount = isset($ln['discount_amount']) ? (float)$ln['discount_amount'] : 0.0;
    $discValue = isset($ln['discount_value']) ? (float)$ln['discount_value'] : 0.0;
    if ($discAmount > 0) {
        $totalLineDiscountAmount += $discAmount;
    } elseif ($discValue > 0) {
        $qty = (float)($ln['quantity'] ?? 0);
        $price = (float)($ln['unit_price'] ?? 0);
        $discType = strtolower((string)($ln['discount_type'] ?? 'percent'));
        $discAmount = ($discType === 'percent') ? (($qty * $price) * ($discValue / 100.0)) : $discValue;
        $totalLineDiscountAmount += $discAmount;
    }
}

// Determine if discounts are document-level only
$invoiceDiscountTotal = (float)($invoice['discount_total'] ?? ($invoice['discount'] ?? 0));
$docDiscountValue = (float)($invoice['document_discount_value'] ?? 0);
$docDiscountType = strtolower((string)($invoice['document_discount_type'] ?? 'fixed'));
$isDocumentLevelOnly = ($docDiscountValue > 0 && abs($totalLineDiscountAmount) < 0.005);

foreach ($lines as &$ln) {
    // ... existing enrichment code ...
    
    // If discounts are document-level only, clear line-level discount amounts to prevent confusion
    if ($isDocumentLevelOnly) {
        $ln['discount_amount'] = 0.0;
        $ln['discount_value'] = 0.0;
        $ln['discount_type'] = 'percent';
    }
}
unset($ln);
```

### Why This Works:
- Detects scenario: `document_discount_value > 0` AND no significant line-level discounts
- When detected, clears discount amounts from lines before passing to PDF
- PDF template then only sees document-level discount
- View page and PDF now display the same information

---

## File 2: app/Views/pdf/invoice_system.php

### Location: Lines ~154-210 (discount calculation section)

### Change Summary:
Fixed discount calculation to properly separate line-level from document-level discounts by recalculating from actual data instead of using invoice header fields incorrectly.

### Exact Changes:

**BEFORE:**
```php
// Separate line-level and document-level discounts
$lineDiscountTotal = isset($invoice['discount_total']) ? (float)$invoice['discount_total'] : 0.0;
$documentDiscountType = isset($invoice['document_discount_type']) ? (string)$invoice['document_discount_type'] : 'fixed';
$documentDiscountValue = isset($invoice['document_discount_value']) ? (float)$invoice['document_discount_value'] : 0.0;
$documentDiscountAmount = 0.0;

$computedSubtotal = 0.0;
$computedLineDiscount = 0.0;
$computedTax = 0.0;
if (!empty($lines)) {
    foreach ($lines as $ln) {
        $qty = (float)($ln['quantity'] ?? 0);
        $unitPrice = (float)($ln['unit_price'] ?? 0);
        $lineBase = $qty * $unitPrice;
        $discAmt = isset($ln['discount_amount']) ? (float)$ln['discount_amount'] : 0.0;
        if ($discAmt === 0.0 && isset($ln['discount_value'])) {
            $discAmt = $lineBase * ((float)$ln['discount_value'] / 100.0);
        }
        $taxRate = isset($ln['tax_rate']) ? (float)$ln['tax_rate'] : (isset($ln['tax']) ? (float)$ln['tax'] : 0.0);
        $taxAmt = isset($ln['tax_amount']) ? (float)$ln['tax_amount'] : (($lineBase - $discAmt) * ($taxRate / 100.0));
        $computedSubtotal += $lineBase;
        $computedLineDiscount += $discAmt;
        $computedTax += $taxAmt;
    }
}

if ($subtotal == 0.0 && $computedSubtotal > 0) {
    $subtotal = $computedSubtotal;
}
if ($lineDiscountTotal == 0.0 && $computedLineDiscount > 0) {
    $lineDiscountTotal = $computedLineDiscount;
}
if ($tax == 0.0 && $computedTax > 0) {
    $tax = $computedTax;
}

// Calculate document-level discount if present
if ($documentDiscountValue > 0) {
    $subtotalAfterLineDiscount = $subtotal - $lineDiscountTotal;
    if ($documentDiscountType === 'percent') {
        $documentDiscountAmount = $subtotalAfterLineDiscount * ($documentDiscountValue / 100.0);
    } else {
        $documentDiscountAmount = $documentDiscountValue;
    }
} else {
    $documentDiscountAmount = 0.0;
}

// Total discount = line-level + document-level
$discountTotal = $lineDiscountTotal + $documentDiscountAmount;
```

**AFTER:**
```php
// Separate line-level and document-level discounts
$documentDiscountType = isset($invoice['document_discount_type']) ? (string)$invoice['document_discount_type'] : 'fixed';
$documentDiscountValue = isset($invoice['document_discount_value']) ? (float)$invoice['document_discount_value'] : 0.0;
$documentDiscountAmount = 0.0;

$computedSubtotal = 0.0;
$computedLineDiscount = 0.0;
$computedTax = 0.0;
if (!empty($lines)) {
    foreach ($lines as $ln) {
        $qty = (float)($ln['quantity'] ?? 0);
        $unitPrice = (float)($ln['unit_price'] ?? 0);
        $lineBase = $qty * $unitPrice;
        // Only count line discount if the line actually has discount data
        $discAmt = isset($ln['discount_amount']) ? (float)$ln['discount_amount'] : 0.0;
        if ($discAmt === 0.0 && isset($ln['discount_value'])) {
            $discValue = (float)$ln['discount_value'];
            if ($discValue > 0) {
                $discAmt = $lineBase * ($discValue / 100.0);
            }
        }
        $taxRate = isset($ln['tax_rate']) ? (float)$ln['tax_rate'] : (isset($ln['tax']) ? (float)$ln['tax'] : 0.0);
        $taxAmt = isset($ln['tax_amount']) ? (float)$ln['tax_amount'] : (($lineBase - $discAmt) * ($taxRate / 100.0));
        $computedSubtotal += $lineBase;
        $computedLineDiscount += $discAmt;
        $computedTax += $taxAmt;
    }
}

$lineDiscountTotal = $computedLineDiscount;

// Calculate document-level discount if present
if ($documentDiscountValue > 0) {
    $subtotalAfterLineDiscount = $computedSubtotal - $lineDiscountTotal;
    if ($documentDiscountType === 'percent') {
        $documentDiscountAmount = $subtotalAfterLineDiscount * ($documentDiscountValue / 100.0);
    } else {
        $documentDiscountAmount = $documentDiscountValue;
    }
} else {
    $documentDiscountAmount = 0.0;
}

// Ensure subtotal reflects computed value if not set
if ($subtotal == 0.0 && $computedSubtotal > 0) {
    $subtotal = $computedSubtotal;
}
if ($tax == 0.0 && $computedTax > 0) {
    $tax = $computedTax;
}

// Total discount = line-level + document-level
$discountTotal = $lineDiscountTotal + $documentDiscountAmount;
```

### Key Differences:

1. **Line 155**: Removed `$lineDiscountTotal = isset($invoice['discount_total'])...`
   - ❌ Was using invoice['discount_total'] (which is the TOTAL of all discounts)
   - ✅ Now recalculate from actual line data

2. **Lines 160-185**: Computation now uses `$computedLineDiscount` instead of mixing with invoice header field

3. **Line 187**: Direct assignment: `$lineDiscountTotal = $computedLineDiscount;`
   - ✅ Clean separation: line discounts come from lines, not header

4. **Lines 189-197**: Document discount calculation now uses `$computedSubtotal` instead of potentially wrong `$subtotal`

5. **Lines 199-202**: Explicit fallback to computed values only if not already provided

### Why This Works:
- Now ALWAYS recalculates line discounts from actual line data
- Never mixes invoice header fields with line data
- Document discount calculated from header fields (which are correct)
- Clear, explicit separation prevents confusion

---

## File 3: app/Views/invoices/simple_invoice.php

### No Changes Required ✓
The view page template already correctly:
- Reads `document_discount_type` and `document_discount_value` from invoice header
- Displays discount source as "Document only", "Line only", or "Both"
- Shows proper totals

---

## Summary of Changes

| File | Type | Impact | Result |
|------|------|--------|--------|
| CustomerInvoices.php | Logic Fix | Controllers detects & clears doc-only line discounts | PDF doesn't show line discounts when not applicable |
| invoice_system.php | Logic Fix | Template recalculates from actual data | PDF correctly separates line vs document discounts |
| simple_invoice.php | None | View already correct | No changes needed |

---

## Testing the Fix

### Manual Test:
1. Create invoice with document discount only (50%, no line discounts)
2. View page → Shows "Document only"
3. Download PDF → Check:
   - ✓ Product lines have NO discount in DISC column
   - ✓ Line Discounts row is hidden
   - ✓ Document Discount (50%) shows correct amount
   - ✓ Total Discount is correct
   - ✓ Final total matches view page

### Automated Test:
```php
// Verify roundtrip consistency
$viewData = // ... fetch view page data
$pdfData = // ... fetch PDF data

assert($viewData['documentDiscountAmount'] === $pdfData['documentDiscountAmount']);
assert($viewData['lineDiscountTotal'] === $pdfData['lineDiscountTotal']);
assert($viewData['total'] === $pdfData['total']);
```

---

## Deployment Checklist

- [ ] Backup current database (not required, no data changes)
- [ ] Deploy CustomerInvoices.php changes
- [ ] Deploy invoice_system.php changes
- [ ] Clear any PDF caches
- [ ] Test with existing invoice that has document discount
- [ ] Verify view page and PDF show same discount information
- [ ] Verify financial totals match
