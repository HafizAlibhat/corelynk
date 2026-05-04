-- Vendor Bill and Bill Lines Schema Migration
-- Run this if automatic migration doesn't work

-- Add po_id and based_on columns to vendor_bills (if they don't exist)
ALTER TABLE vendor_bills ADD COLUMN po_id INT NULL AFTER vendor_id;
ALTER TABLE vendor_bills ADD COLUMN based_on ENUM('po_qty', 'grn_qty', 'manual') DEFAULT 'manual' AFTER po_id;

-- Add foreign key for po_id (if it doesn't exist)
ALTER TABLE vendor_bills 
ADD CONSTRAINT fk_vendor_bills_po_id 
FOREIGN KEY (po_id) REFERENCES purchase_orders(id) 
ON DELETE SET NULL;

-- Create vendor_bill_lines table (if it doesn't exist)
CREATE TABLE IF NOT EXISTS vendor_bill_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vendor_bill_id INT UNSIGNED NOT NULL,
    po_line_id INT UNSIGNED NULL,
    product_id INT UNSIGNED NULL,
    variant_id INT UNSIGNED NULL,
    qty DECIMAL(18,4) NOT NULL DEFAULT 0,
    unit_price DECIMAL(18,4) NOT NULL DEFAULT 0,
    line_total DECIMAL(18,4) NOT NULL DEFAULT 0,
    created_at DATETIME NULL,
    
    CONSTRAINT fk_vbl_bill_id FOREIGN KEY (vendor_bill_id) REFERENCES vendor_bills(id) ON DELETE CASCADE,
    CONSTRAINT fk_vbl_po_line_id FOREIGN KEY (po_line_id) REFERENCES purchase_order_lines(id) ON DELETE SET NULL,
    
    KEY idx_vbl_vendor_bill_id (vendor_bill_id),
    KEY idx_vbl_po_line_id (po_line_id),
    KEY idx_vbl_product_id (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
