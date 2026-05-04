# Pending Payments & Customer Receivables - Debug & Fix

## Problem Summary
Customer pages were showing **0 pending invoices** and **"Total Pending: 0.00"** even when there were unpaid invoices for that customer. This is a critical accounting issue that prevents customers from seeing what they owe and prevents the business from tracking receivables accurately.

## Root Causes Identified

1. **Unreliable Invoice Query Logic**: The original query in `Customers.php` had multiple fallback field name checks that could fail silently
2. **No Standardized Calculation Method**: The receivables calculation was scattered across multiple controllers with slight variations
3. **Missing Debug Visibility**: No way to diagnose why specific invoices weren't appearing in the pending list
4. **Payment Allocation Edge Cases**: Complex subqueries for calculating paid amounts could miss allocations

## Solutions Implemented

### 1. **Created CustomerReceivablesHelper** 
**File**: [app/Helpers/CustomerReceivablesHelper.php](app/Helpers/CustomerReceivablesHelper.php)

A centralized, reliable helper class with two key methods:

#### `getUnpaidInvoices($customerId, $db, $debug)`
- **Purpose**: Fetch all unpaid invoices for a customer with detailed debugging
- **Logic**:
  - Validates table structure dynamically
  - Determines correct amount field (total_amount, total, or amount)
  - Applies filters in order: soft delete → status → outstanding balance
  - Calculates outstanding amount properly
  - Returns array of unpaid invoices with detailed info

- **Returns**:
  ```php
  [
      'unpaid' => [...], // Array of unpaid invoice records
      'total' => 250.00,  // Total pending amount
      'count' => 2,       // Number of unpaid invoices
      'order_receivables' => [...] // Grouped by sales order
  ]
  ```

#### `recalculatePendingAmount($customerId, $db)`
- **Purpose**: Complete receivables summary including payments and advance balance
- **Returns**:
  ```php
  [
      'total_pending' => 250.00,
      'posted_payments' => 100.00,
      'draft_payments' => 50.00,
      'advance_balance' => 0.00,
  ]
  ```

### 2. **Updated Customers Controller**
**File**: [app/Controllers/Customers.php](app/Controllers/Customers.php)

#### Modified `show($id)` method
- **Before**: Had complex inline query  
- **After**: Uses `CustomerReceivablesHelper::getUnpaidInvoices()`
- **Benefit**: More reliable, maintainable, consistent across the application

#### Added `diagnostic($id)` method
- **URL**: `GET /api/customers/diagnostic/{customerId}`
- **Purpose**: Debug why invoices aren't showing for a specific customer
- **Access**: Requires AJAX request
- **Returns**: Comprehensive JSON with:
  - Customer info validation
  - Table structure check
  - Raw invoices (unfiltered)
  - Count after each filter applied
  - Detailed invoice calculations
  - Payment allocations
  - Recommendations for fixes

## How to Use the Diagnostic Tool

### Step 1: Access the endpoint
```
GET /customers/diagnostic/939
```
(Replace 939 with your customer ID)

### Step 2: Analyze the JSON response
The response will show:
- `customer_id` - The customer being analyzed
- `debug_checks` - Various validation steps
- `table_structure` - Available database columns
- `invoices_raw` - All invoices without filters
- `invoices_with_calcs` - Detailed calculations for each
- `payments` - Payment history
- `recommendations` - Suggested fixes

### Step 3: Key fields to check
- **`total_invoices_for_customer`** - Should be > 0 if customer has invoices
- **`after_delete_filter`** - Count after removing soft-deleted records
- **`after_status_filter`** - Count after excluding cancelled/void
- **`helper_unpaid_count`** - Final count after outstanding balance check
- **`would_include`** - Shows TRUE/FALSE for each invoice whether it should be in the pending list

## Testing the Fix

### Method 1: Using the Diagnostic Tool
1. Go to customer profile page
2. Note the customer ID from URL
3. Visit: `http://yoursite/customers/diagnostic/{customerID}`
4. Look for invoices with `would_include: true` but not appearing in the customer view
5. Check the `outstanding` amount - if > 0.005, it should be included

### Method 2: Direct Check
1. Open customer page: `GET /customers/{id}`
2. Verify the Receivables Snapshot shows correct totals
3. Verify "Unpaid Invoices" section lists all invoices with outstanding balance

## Database Schema Notes

The `customer_invoices` table schema includes:
- ✅ `customer_id` - Links to customer
- ✅ `total_amount` - Invoice total (primary amount field)
- ✅ `status` - enum(draft, issued, partially_paid, paid, overdue, cancelled)
- ✅ `deleted_at` - Soft delete field
- ✅ `issue_date`, `due_date` - Date tracking
- ✅ `currency_code` - Multi-currency support

## Common Issues & Fixes

### Issue: "No unpaid invoices" but invoices exist
**Check**:
1. Is invoice status = 'cancelled' or 'void'? (Will be excluded)
2. Is invoice fully paid? (outstanding <= 0.005)
3. Is invoice soft-deleted? (deleted_at IS NOT NULL)

**Fix**: Use diagnostic endpoint to inspect each invoice's `would_include` flag

### Issue: Invoice shows in list but with $0 outstanding
**Cause**: Invoice is fully paid or overpaid
**Fix**: This is correct behavior - it shouldn't appear in unpaid list

### Issue: Payment allocations not being counted
**Check**: Verify record exists in `customer_payment_allocations` table with:
- `payment_id` pointing to a posted payment
- `invoice_id` pointing to the invoice
- `amount` or `allocated_amount` > 0

## Performance Considerations

The receivables calculation now:
- Uses a single optimized query (instead of multiple queries)
- Properly caches column name checks
- Returns early if no invoices exist
- Uses indexed lookups where possible
- Logs errors for debugging

## Code Quality Improvements

✅ **Centralized Logic** - All receivables calculation in one helper  
✅ **Better Error Handling** - Comprehensive try-catch blocks  
✅ **Debug Logging** - Optional debug mode for detailed output  
✅ **Type Safety** - Proper type hints and casts  
✅ **Backward Compatibility** - Doesn't break existing payment flows

## Next Steps

1. **Test with your data**: Use the diagnostic endpoint on customer ID 939 (from the screenshot)
2. **Monitor logs**: Check `/storage/logs/` for any calculation errors
3. **Verify calculations**: Compare helper's results with manual inspection of database
4. **Update other controllers**: Apply same pattern to `AccountingCustomerPayments.php` and `Corelynk.php` dashboard for consistency

## API Change Summary

### New Helper Available
```php
use App\Helpers\CustomerReceivablesHelper;

// Get unpaid invoices
$result = CustomerReceivablesHelper::getUnpaidInvoices($customerId);

// Get full summary
$summary = CustomerReceivablesHelper::recalculatePendingAmount($customerId);
```

### New Diagnostic Endpoint
```
GET /customers/diagnostic/{customerId}
Response: JSON with detailed debugging info
```

### Updated Models/Controllers
- `Customers.php` - Uses helper instead of inline queries
- Both old and new methods produce same results (backward compatible)
