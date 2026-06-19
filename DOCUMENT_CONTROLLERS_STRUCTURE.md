# Document Controllers Structure Reference

## 1. **CustomerInvoices** Controller
**File**: [app/Controllers/CustomerInvoices.php](app/Controllers/CustomerInvoices.php)

### Main Models
- **Document Model**: `CustomerInvoiceModel`
  - Table: `customer_invoices`
  - Primary Key: `id`
  - Document Identifier: `invoice_number`
  - Public ID: `public_id`

- **Line Model**: `CustomerInvoiceLineModel`
  - Table: `customer_invoice_lines`
  - Primary Key: `id`
  - Foreign Key: `invoice_id`

### Key Fields

**Document Level** (`customer_invoices`):
- `id`, `public_id`, `invoice_number` (identifier)
- `customer_id` (links to customers table)
- `sales_order_id` (links to sales_orders)
- `parent_invoice_id` (for custom invoice types)
- `invoice_type` ('system' or 'custom')
- `issue_date`, `due_date`
- `payment_term_id`
- `currency_code`
- `subtotal`, `discount_total`, `tax_total`, `total_amount`, `shipping_cost`, `customs_value`
- `document_discount_type`, `document_discount_value`, `discount_exclude_shipping`
- `status`, `is_custom_adjusted`, `custom_notes`, `export_reference`
- `posted_entry_id`, `created_by`
- Timestamps: `created_at`, `updated_at`, `deleted_at`

**Line Level** (`customer_invoice_lines`):
- `id`, `invoice_id`, `sort_order`
- `product_id`, `product_variant_id`
- `product_code`, `product_name`, `product_image_url`
- `description`
- `unit` (measurement unit)
- `quantity`, `unit_price`
- `discount_type` ('percent' or 'fixed'), `discount_value`, `discount_amount`
- `tax_type` ('percent' or 'fixed'), `tax_value`, `tax_rate`, `tax_amount`, `tax_code_id`
- `line_total`
- `display_type` ('line' or 'section')
- `section_title` (for grouped sections)
- Timestamps: `created_at`, `updated_at`

### show() Method

**Signature**: `view($invoiceId)`

**Location**: Line 557

**Logic**:
1. Look up invoice by `public_id` or `id` using `findByPublicIdOrId()`
2. Extract invoice `id`
3. Fetch all lines: `$invoiceLineModel->where('invoice_id', $invoiceId)->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->findAll()`
4. Normalize lines using `normalizeInvoiceLinesForDisplay()`
5. Enrich lines with product data:
   - Load all products and build product map
   - Load all product variants
   - Match variants by explicit `product_variant_id` or by description
   - Populate product code, unit, image URL
6. Return view with invoice + enriched lines

**Customer/Vendor Info**:
- Customer info loaded separately from `CustomerModel` (if available)
- Customer address fetched via `CustomerAddressModel`

---

## 2. **Quotations** Controller
**File**: [app/Controllers/Quotations.php](app/Controllers/Quotations.php)

### Main Models
- **Document Model**: `QuotationModel`
  - Table: `quotations`
  - Primary Key: `id`
  - Document Identifier: `quote_number`
  - Public ID: `public_id`

- **Line Model**: `QuotationLineModel`
  - Table: `quotation_lines`
  - Primary Key: `id`
  - Foreign Key: `quotation_id`

### Key Fields

**Document Level** (`quotations`):
- `id`, `public_id`, `quote_number` (identifier)
- `customer_id` (links to customers)
- `company_id`, `price_list_id`
- `issue_date`, `expires_at`
- `status` ('draft', 'quoted', 'accepted', 'cancelled')
- `currency`, `quote_currency`, `base_currency`
- `subtotal`, `discount`, `document_discount_type`, `document_discount_value`, `discount_exclude_shipping`
- `tax`, `tax_total`, `shipping_amount`
- `total_weight`, `total` (grand total)
- `notes`, `created_by`
- Timestamps: `created_at`, `updated_at`, `deleted_at`

**Line Level** (`quotation_lines`):
- `id`, `quotation_id`, `sort_order`
- `product_id`, `product_variant_id`, `product_code`, `product_name`
- `description`
- `quantity`, `unit` (measurement unit)
- `unit_price`, `line_total`
- `discount_type` ('percent' or 'fixed'), `discount_value`, `discount_amount`
- `tax_type` ('percent' or 'fixed'), `tax_value`, `tax_rate`, `tax_amount`
- `weight`, `unit_weight`, `weight_unit` (kg, g, lb, oz, mg)
- `vendor_id`, `cost_price`, `sale_price_currency`
- `base_amount`, `net_amount`
- `line_number`
- `product_image_url`
- `display_type` ('line' or 'section'), `section_title`
- `updated_at`

### view() Method

**Signature**: `view($identifier = null)`

**Location**: ~Line 330

**Logic**:
1. Support both numeric `id` and `public_id` identifiers
2. Try numeric ID first: `$quote = $this->quotationModel->getWithLines((int)$identifier)`
3. If not found and identifier is string, lookup by `public_id` using query builder
4. If public_id enabled and numeric ID was used, redirect to public_id URL
5. Fetch quotation with lines via `getWithLines()` method which:
   - Retrieves quotation record
   - Fetches all lines ordered by `sort_order` or `id`
   - Preloads products and variants to avoid N+1 queries
   - Recalculates per-line amounts for display (base, discount, net, tax, total)
   - Recalculates document totals
6. Enriches with customer data and address info
7. Applies role-based data access controls

**Customer Info**:
- Customer data loaded via `CustomerModel->find($customer_id)`
- Customer address loaded via `CustomerAddressModel` with country name lookup

---

## 3. **SalesOrders** Controller
**File**: [app/Controllers/SalesOrders.php](app/Controllers/SalesOrders.php)

### Main Models
- **Document Model**: `SalesOrderModel`
  - Table: `sales_orders`
  - Primary Key: `id`
  - Document Identifier: `order_number`
  - Public ID: `public_id`

- **Line Model**: `SalesOrderLineModel`
  - Table: `sales_order_lines`
  - Primary Key: `id`
  - Foreign Key: `sales_order_id`

### Key Fields

**Document Level** (`sales_orders`):
- `id`, `public_id`, `order_number` (identifier)
- `customer_id` (links to customers)
- `quotation_id` (link to source quotation)
- `order_date`
- `subtotal`, `discount`, `document_discount_type`, `document_discount_value`, `discount_exclude_shipping`
- `tax_total`, `total` (grand total)
- `shipping_amount` (optional field, may not exist on all installations)
- `shipping_cost` (alternative field name)
- `currency`, `currency_code` (PKR, USD, etc.)
- `status`
- `created_by`
- Timestamps: `created_at`, `updated_at`

**Line Level** (`sales_order_lines`):
- `id`, `sales_order_id`, `sort_order`
- `product_id`, `product_variant_id`
- `description`
- `quantity`, `unit_price`
- `discount_type` ('percent' or 'fixed'), `discount_value`, `discount_amount`
- `tax_type` ('percent' or 'fixed'), `tax_value`, `tax_rate`, `tax_amount`
- `line_total`
- `display_type` ('line' or 'section'), `section_title`
- `updated_at`

### view() Method

**Signature**: `view($id)`

**Location**: ~Line 66 (labeled as `view()`)

**Logic**:
1. Support both numeric `id` and `public_id` identifiers
2. Try numeric ID: `$order = $this->model->find((int)$identifier)`
3. If not found, try public_id lookup via query builder
4. Redirect to error if order not found
5. Redirect to canonical URL if public_id enabled
6. Fetch lines: `$this->lineModel->where('sales_order_id', $orderId)->findAll()`
7. Prefer quotation lines if available (for metadata): `$quoteWithLines['lines'] ?? $this->lineModel->where('sales_order_id', $orderId)->findAll()`
8. Enrich lines with product metadata:
   - Load product map (code, name, image, unit, weight info)
   - Load variant map (art_number, weight, image)
   - Calculate line weights
   - Resolve product images (prefer variant image if available)

**Customer Info**:
- Customer loaded via `CustomerModel->find($customer_id)`
- Address loaded via `CustomerAddressModel` with country lookup

---

## 4. **DeliveryOrders** Controller
**File**: [app/Controllers/DeliveryOrders.php](app/Controllers/DeliveryOrders.php)

### Main Models
- **Document Model**: `DeliveryOrderModel`
  - Table: `delivery_orders`
  - Primary Key: `id`
  - Document Identifier: `do_number`
  - Public ID: `public_id`
  - Has `getWithLines()` method

- **Line Model**: `DeliveryOrderLineModel`
  - Table: `delivery_order_lines`
  - Primary Key: `id`
  - Foreign Key: `delivery_order_id`

### Key Fields

**Document Level** (`delivery_orders`):
- `id`, `public_id`, `do_number` (identifier)
- `sales_order_id` (links to sales_orders)
- `status` ('draft', 'confirmed', 'shipped', 'delivered')
- `delivery_status` (null, 'in_transit', 'delivered')
- `shipping_vendor_id`, `shipping_service_id`
- `shipping_po_id`, `shipping_bill_id`
- `final_weight_kg`, `shipping_cost_pkr`
- `tracking_number`, `tracking_url`
- `destination_country`
- `shipped_at`, `delivered_at`
- `estimated_delivery_days`
- `delivery_confirmed_at`
- `shipping_notes`, `delivery_notes`
- `parcel_image`, `delivery_screenshot`
- Timestamps: `created_at`, `updated_at`

**Line Level** (`delivery_order_lines`):
- `id`, `delivery_order_id`
- `sales_order_line_id` (link to source sales order line)
- `product_id`, `variant_id`
- `quantity_ordered`, `ready_qty`, `qty_to_ship`
- Timestamps: `created_at`, `updated_at`

### view() Method

**Signature**: `view($doId = null)`

**Location**: Line 357

**Logic**:
1. Require authentication
2. Look up DO by `public_id` or `id` using `findByPublicIdOrId()`
3. Extract DO `id`
4. Fetch with lines: `$do = $doModel->getWithLines($doId)`
5. Load related sales order
6. Load sales order lines and product/variant data:
   - Get product IDs from DO lines
   - Load all products and build map (code, name, image)
   - Get sales order line IDs from DO lines
   - Load sales order lines to get variant info
   - Load product variants map
7. Enrich DO lines with product/variant metadata:
   - Resolve product code (prefer variant code if available)
   - Resolve product name
   - Resolve product image (prefer variant image)
8. Build delivery timeline
9. Load shipping vendors and services
10. Load parcel images and tracking docs
11. Return view with enriched data

**Supplier/Vendor Info**:
- Customer data loaded from related `SalesOrderModel` (which has customer_id)
- Shipping vendor loaded from `vendors` table if `shipping_vendor_id` set
- Shipping service loaded from `ShippingServiceModel`

---

## 5. **NewPurchaseGrns** Controller
**File**: [app/Controllers/NewPurchaseGrns.php](app/Controllers/NewPurchaseGrns.php)

### Main Models
- **Document Model**: `PurchaseGrnModel`
  - Table: `purchase_grns`
  - Primary Key: `id`
  - Document Identifier: `grn_number`
  - Public ID: `public_id` (auto-added if not exists)

- **Line Model**: `PurchaseGrnLineModel`
  - Table: `purchase_grn_lines`
  - Primary Key: `id`
  - Foreign Key: `grn_id`

### Key Fields

**Document Level** (`purchase_grns`):
- `id`, `public_id`, `grn_number` (identifier)
- `po_id` (links to purchase_orders)
- `vendor_id` (links to vendors)
- `received_at`
- `notes`
- `created_by`
- Timestamps: `created_at` (if exists), stored in `created_at`

**Line Level** (`purchase_grn_lines`):
- `id`, `grn_id`
- `po_line_id` (link to purchase_order_lines)
- `product_id`, `variant_id` (optional)
- `quantity received` (stored as `qty_received`)
- `unit_price`, `unit_cost`
- `over_receipt_reason_type`, `over_receipt_reason_details`
- `over_received_qty`
- `description`
- `created_at`

### detail() Method (Read-only view)

**Signature**: `detail($grnId = null)`

**Location**: ~Line 150

**Logic**:
1. Support both string `grnId` and `public_id` lookups
2. Ensure public IDs enabled: `ensureGrnPublicIds()`
3. Build complex query joining:
   - `purchase_orders` (for PO details)
   - `vendors` (for vendor name)
   - Stock movements (for warehouse/location)
   - `warehouses`, `warehouse_locations`
   - `users` (for created_by username)
4. Match by `public_id`, `grn_number`, or numeric `id`
5. Redirect to canonical public_id URL if needed
6. Load warehouse location path if applicable
7. Fetch GRN lines:
   ```sql
   SELECT gl.*, pol.qty as ordered_qty, p.name as product_name, p.sku, p.code, ...
   FROM purchase_grn_lines gl
   LEFT JOIN purchase_order_lines pol ON pol.id = gl.po_line_id
   LEFT JOIN products p ON p.id = gl.product_id
   LEFT JOIN product_variants pv ON pv.id = gl.variant_id (if field exists)
   WHERE gl.grn_id = ?
   ```
8. Load related vendor bills on the same PO (up to 8)
9. Load issue history from `purchase_grn_line_issues` table
10. Backfill over-receipt reasons from business logic if blank
11. Infer variant IDs for PO lines if not already set

**Vendor Info**:
- Vendor data loaded via `vendors` table join
- PO details loaded and enriched
- Related purchase orders available in related_bills context

---

## 6. **VendorBills** Controller
**File**: [app/Controllers/VendorBills.php](app/Controllers/VendorBills.php)

### Main Models
- **Document Model**: `VendorBillModel`
  - Table: `vendor_bills`
  - Primary Key: `id`
  - Document Identifier: `vendor_bill_number`
  - Public ID: `public_id`
  - Has `findByPublicIdOrId()` method

- **Line Model**: `VendorBillLineModel`
  - Table: `vendor_bill_lines`
  - Primary Key: `id`
  - Foreign Key: `vendor_bill_id`

### Key Fields

**Document Level** (`vendor_bills`):
- `id`, `public_id`, `vendor_bill_number` (identifier)
- `vendor_id` (links to vendors)
- `po_id` (links to purchase_orders)
- `memo`, `notes`, `status`
- `bill_date`, `issue_date`
- `total_amount` (sum of line totals)
- `balance` (calculated as total - paid_amount)
- `based_on` (context field: 'po', 'po_over_receipt', etc.)
- `currency_code` (PKR, USD, etc.)
- `account_id`, `expense_account_id`, `purchase_account_id`
- `posted_entry_id` (link to accounting journal)
- `created_by`
- Timestamps: `created_at`, `updated_at` (but `useTimestamps = false`)

**Line Level** (`vendor_bill_lines`):
- `id`, `vendor_bill_id`
- `po_line_id` (link to purchase_order_lines)
- `processing_record_id` (optional, for non-PO billing)
- `product_id`, `variant_id`
- `qty` (billed quantity)
- `unit_price`
- `line_total`
- `created_at`

### show() Method

**Signature**: `show($billId = null)`

**Location**: Line 17

**Logic**:
1. Look up bill by `public_id` or `id` using `findByPublicIdOrId()`
2. Return 404 if not found (JSON if AJAX, redirect if HTML)
3. Extract bill `id`
4. Enrich bill header fields (not stored directly):
   - Load vendor name/code from `vendors` table if missing
   - Load PO number from `purchase_orders` if missing
   - Set vendor_name fallback if vendor lookup failed
5. Calculate payment status:
   ```sql
   SELECT SUM(COALESCE(NULLIF(amount_allocated, 0), amount, 0)) as paid_amount
   FROM vendor_payment_allocations vpa
   JOIN vendor_payments vp ON vp.id = vpa.payment_id
   WHERE vpa.vendor_bill_id = ? AND vp.status = 'posted'
   ```
6. Calculate: `paid`, `balance` (max(0, total - paid)), `is_paid` (balance <= 0.0001)
7. Load bill lines:
   ```
   lines = $lineModel->where('vendor_bill_id', billId)->findAll()
   ```
8. Initialize `image_urls` on all lines (critical step)
9. Load payment history and related GRNs (if exists)
10. Enrich lines with product/GRN/PO metadata as available
11. Handle stored image URLs with fallback to base URL construction

**Vendor Info**:
- Vendor loaded from `vendors` table join
- PO details loaded where available
- Related bills and payments tracked
- Payment status calculated from `vendor_payment_allocations` and `vendor_payments` tables

---

## Summary Table

| Controller | Document Model | Line Model | Identifier Field | Key FK | Relationship | show() method |
|-----------|----------------|----------|------------------|--------|--------------|---------------|
| CustomerInvoices | CustomerInvoiceModel | CustomerInvoiceLineModel | invoice_number | customer_id | Many lines per invoice | view($invoiceId) |
| Quotations | QuotationModel | QuotationLineModel | quote_number | customer_id | Many lines per quote | view($identifier) |
| SalesOrders | SalesOrderModel | SalesOrderLineModel | order_number | customer_id | Many lines per order | view($id) |
| DeliveryOrders | DeliveryOrderModel | DeliveryOrderLineModel | do_number | sales_order_id | Many lines per DO | view($doId) |
| NewPurchaseGrns | PurchaseGrnModel | PurchaseGrnLineModel | grn_number | vendor_id | Many lines per GRN | detail($grnId) |
| VendorBills | VendorBillModel | VendorBillLineModel | vendor_bill_number | vendor_id | Many lines per bill | show($billId) |

---

## Common Patterns

1. **Line Fetching**: All use `->where('document_fk', $id)->findAll()` pattern
2. **Public IDs**: Support both numeric ID and public_id via `findByPublicIdOrId()` method
3. **Product Enrichment**: Load product maps in memory to avoid N+1 queries
4. **Image URLs**: Normalize to base_url() for relative paths, preserve absolute URLs
5. **Currency**: Stored in document headers, defaults to company settings or USD
6. **Timestamps**: Most use CodeIgniter's `useTimestamps = true` with created_at/updated_at
7. **Soft Deletes**: Sales documents use soft deletes (`deleted_at` field)

---

## Line Field Commonalities

### Universal Fields (across most line tables)
- `id`, `{document}_id` (FK), `sort_order`, `product_id`
- `quantity` (qty/qty_received/qty_to_ship), `unit_price`, `line_total`
- Display metadata: `product_code`, `product_name`, `description`, `unit`

### Calculated/Optional Fields 
- `discount_amount`, `tax_amount` (calculated per line)
- `product_variant_id` (optional, for variant-specific lines)
- `product_image_url` (cached for display)
- `display_type`, `section_title` (for section headers vs product lines)

### Metadata Fields
- Tax/Discount breakdown (`discount_type`, `tax_type`, rates vs amounts)
- Weight information (`unit_weight`, `weight_unit` - quotations/sales orders)
- Over-receipt tracking (GRNs: `over_receipt_reason_type`, `over_receipt_reason_details`)
- Processing references (`po_line_id`, `processing_record_id` for VendorBills)
