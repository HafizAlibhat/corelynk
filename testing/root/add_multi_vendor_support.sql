-- Multi-vendor per process support
-- Run this SQL in your database. Compatible with MySQL 8.0+

-- 1) Pivot table: process_vendors
CREATE TABLE IF NOT EXISTS process_vendors (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  process_id INT UNSIGNED NOT NULL,
  vendor_id INT UNSIGNED NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_process_vendor (process_id, vendor_id),
  KEY idx_pv_process (process_id),
  KEY idx_pv_vendor (vendor_id),
  CONSTRAINT fk_pv_process FOREIGN KEY (process_id) REFERENCES processes(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_pv_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
);

-- 2) Seed pivot from existing processes.vendor_id when present
INSERT INTO process_vendors (process_id, vendor_id, is_active)
SELECT p.id, p.vendor_id, 1
FROM processes p
WHERE p.vendor_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM process_vendors pv
    WHERE pv.process_id = p.id AND pv.vendor_id = p.vendor_id
  );

-- 3) Add vendor_id to process_batches (nullable for back-compat)
ALTER TABLE process_batches
  ADD COLUMN IF NOT EXISTS vendor_id INT UNSIGNED NULL AFTER process_id,
  ADD KEY idx_pb_vendor (vendor_id),
  ADD CONSTRAINT fk_pb_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id)
    ON UPDATE CASCADE ON DELETE RESTRICT;
