-- Create vendor_bills table first
CREATE TABLE IF NOT EXISTS vendor_bills (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT(10) UNSIGNED NOT NULL,
    bill_number VARCHAR(50) NULL UNIQUE,
    bill_date DATETIME NULL,
    due_date DATETIME NULL,
    total_amount DECIMAL(18,4) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(18,4) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(18,4) NOT NULL DEFAULT 0,
    balance DECIMAL(18,4) NOT NULL DEFAULT 0,
    status ENUM('draft', 'confirmed', 'cancelled', 'paid') NOT NULL DEFAULT 'draft',
    po_id INT(11) UNSIGNED NULL,
    based_on ENUM('po_qty', 'grn_qty', 'manual') NOT NULL DEFAULT 'manual',
    notes TEXT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE SET NULL,
    KEY idx_vendor_id (vendor_id),
    KEY idx_status (status),
    KEY idx_po_id (po_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create vendor_bill_lines table
CREATE TABLE IF NOT EXISTS vendor_bill_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vendor_bill_id INT UNSIGNED NOT NULL,
    po_line_id INT(11) UNSIGNED NULL,
    product_id INT(11) UNSIGNED NULL,
    variant_id INT(11) UNSIGNED NULL,
    qty DECIMAL(18,4) NOT NULL DEFAULT 0,
    unit_price DECIMAL(18,4) NOT NULL DEFAULT 0,
    line_total DECIMAL(18,4) NOT NULL DEFAULT 0,
    created_at DATETIME NULL,
    FOREIGN KEY (vendor_bill_id) REFERENCES vendor_bills(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (po_line_id) REFERENCES purchase_order_lines(id) ON DELETE SET NULL ON UPDATE CASCADE,
    KEY idx_vendor_bill_id (vendor_bill_id),
    KEY idx_po_line_id (po_line_id),
    KEY idx_product_id (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mark migration as run
INSERT INTO migrations (version, class, `group`, `namespace`, time, batch) 
VALUES ('2026-02-16-000001', 'App\\Database\\Migrations\\AddVendorBillPoLinkage', 'default', 'App', UNIX_TIMESTAMP(), 
    (SELECT COALESCE(MAX(batch), 0) + 1 FROM (SELECT batch FROM migrations ORDER BY batch DESC LIMIT 1) AS t))
ON DUPLICATE KEY UPDATE batch = batch;
