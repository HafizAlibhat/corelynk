# Discount Display Alignment - Before & After

## BEFORE FIX ❌

### Invoice View Page (Correct)
```
Discount Source: Document only
Document Discount: $50.00
Doc Disc Type: Percent 50% (Excl Shipping)
```

### PDF (Incorrect) ❌
```
Product Line:
  Code  | Product         | Qty | Unit Price | DISC   | Line Total
  5     | Damascus Steel  | 1   | $100.00    | $50.00 | $50.00  ← WRONG!

Totals:
  Subtotal:               $100.00
  Line Discounts:         $50.00    ← WRONG! (should be hidden)
  Document Discount 50%:  $25.00    ← WRONG! (should be $50)
  Total Discount:         -$75.00   ← WRONG! (should be $50)
  
MISMATCH: View says "document only" but PDF shows both line + doc discount!
```

---

## AFTER FIX ✅

### Invoice View Page (Still Correct)
```
Discount Source: Document only
Document Discount: $50.00
Doc Disc Type: Percent 50% (Excl Shipping)
```

### PDF (Now Correct) ✅
```
Product Line:
  Code  | Product         | Qty | Unit Price | DISC | Line Total
  5     | Damascus Steel  | 1   | $100.00    | —    | $100.00  ← CORRECT!

Totals:
  Subtotal:                  $100.00
  Line Discounts:            (hidden - 0)
  Document Discount (50%):   -$50.00   ← CORRECT!
  Total Discount:            -$50.00   ← CORRECT!
  Shipping:                  $11.00
  Total (After Discounts):   $61.00

ALIGNED: Both view page and PDF correctly show "document only" discount!
```

---

## What Changed in the Code

### Change 1: PDF Controller (CustomerInvoices.php)
**Before**: Just passed lines as-is to PDF template

**After**: 
```php
// Detect if this is a document-only discount scenario
$totalLineDiscountAmount = 0.0;
// ... calculate total ...

$isDocumentLevelOnly = ($docDiscountValue > 0 && abs($totalLineDiscountAmount) < 0.005);

// If document-only, clear line discounts to prevent confusion
if ($isDocumentLevelOnly) {
    $ln['discount_amount'] = 0.0;
    $ln['discount_value'] = 0.0;
}
```

### Change 2: PDF Template (invoice_system.php)  
**Before**: Used `invoice['discount_total']` as line discount (incorrect separator)

**After**:
```php
// Recalculate line discounts from actual line data
$computedLineDiscount = 0.0;
foreach ($lines as $ln) {
    // Sum actual line discount amounts
    $computedLineDiscount += $discAmount;
}

// Then calculate document discount from header
if ($documentDiscountValue > 0) {
    $documentDiscountAmount = $subtotalAfterLineDiscount * ($documentDiscountValue / 100.0);
}

// Result: Clear separation
$discountTotal = $lineDiscountTotal + $documentDiscountAmount;
```

---

## Result: 100% Alignment

| Aspect | Before | After |
|--------|--------|-------|
| **View Page Discount Source** | ✓ Correct | ✓ Correct |
| **PDF Line-Level Discounts** | ❌ Shows $50 (wrong) | ✓ Empty (correct) |
| **PDF Document Discount** | ❌ Shows $25 (wrong) | ✓ Shows $50 (correct) |
| **PDF Total Discount** | ❌ Shows -$75 (wrong) | ✓ Shows -$50 (correct) |
| **PDF Final Total** | ❌ Confusing | ✓ Clear $61.00 |

---

## Database Level: 100% Aligned

The database already had all the necessary fields:

```sql
-- Invoice Header stores document-level discount:
INSERT INTO customer_invoices (
  id, subtotal, 
  discount_total,           -- ✓ Total of all discounts
  document_discount_type,   -- ✓ 'percent' or 'fixed'
  document_discount_value,  -- ✓ 50 (for 50%)
  tax_total, shipping_amount, total_amount
) VALUES (...)

-- Invoice Lines have optional line-level discounts:
INSERT INTO customer_invoice_lines (
  id, invoice_id, product_id, quantity, unit_price,
  discount_type,   -- ✓ 'percent' or 'fixed'
  discount_value,  -- ✓ Discount % or amount
  discount_amount, -- ✓ Calculated discount in currency
  tax_rate, tax_amount, line_total
) VALUES (...)
```

Now both frontend AND database are perfectly aligned! ✅
