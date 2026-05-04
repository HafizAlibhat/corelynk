@echo off
REM Add vendor_id column to product_variants table
C:\xampp\mysql\bin\mysql -u root corelynk_db -e "ALTER TABLE product_variants ADD COLUMN vendor_id INT UNSIGNED NULL DEFAULT NULL AFTER cost;"
C:\xampp\mysql\bin\mysql -u root corelynk_db -e "ALTER TABLE product_variants ADD INDEX idx_variant_vendor (vendor_id);"
C:\xampp\mysql\bin\mysql -u root corelynk_db -e "SELECT 'Column vendor_id added successfully to product_variants' as status;"
echo.
echo ✓ Migration completed successfully!
pause
