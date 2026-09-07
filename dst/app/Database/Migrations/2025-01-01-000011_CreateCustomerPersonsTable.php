<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCustomerPersonsTable extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        
        // Create customer_persons table if it doesn't exist
        if (!$db->tableExists('customer_persons')) {
            $sql = "CREATE TABLE customer_persons (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                customer_id BIGINT UNSIGNED NOT NULL COMMENT 'Reference to customers table',
                name VARCHAR(255) NOT NULL COMMENT 'Person name or contact name',
                title VARCHAR(100) NULL COMMENT 'Title/Position (e.g., Manager, Director)',
                email VARCHAR(255) NULL COMMENT 'Email address',
                phone VARCHAR(20) NULL COMMENT 'Phone number',
                mobile VARCHAR(20) NULL COMMENT 'Mobile number',
                is_primary_contact TINYINT DEFAULT 0 COMMENT 'Mark as primary contact (1) or secondary (0)',
                is_active TINYINT DEFAULT 1 COMMENT 'Active (1) or inactive (0)',
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_customer_id (customer_id),
                KEY idx_is_primary (is_primary_contact)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $db->query($sql);
        }
        
        // Update customer_addresses if it exists to add shipping-related fields
        if ($db->tableExists('customer_addresses')) {
            if (!$db->fieldExists('address_type', 'customer_addresses')) {
                $db->query("ALTER TABLE customer_addresses ADD COLUMN address_type VARCHAR(50) NULL COMMENT 'Type: shipping, billing, both'");
            }
            
            if (!$db->fieldExists('label', 'customer_addresses')) {
                $db->query("ALTER TABLE customer_addresses ADD COLUMN label VARCHAR(100) NULL COMMENT 'Address label (e.g., Main Office, Warehouse, Home)'");
            }
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        
        if ($db->tableExists('customer_persons')) {
            $db->query("DROP TABLE customer_persons");
        }
        
        if ($db->tableExists('customer_addresses')) {
            if ($db->fieldExists('address_type', 'customer_addresses')) {
                $db->query("ALTER TABLE customer_addresses DROP COLUMN address_type");
            }
            if ($db->fieldExists('label', 'customer_addresses')) {
                $db->query("ALTER TABLE customer_addresses DROP COLUMN label");
            }
        }
    }
}
