<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProductFiltersToRoleDataAccess extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('role_data_access')) {
            return;
        }

        if (! $this->db->fieldExists('product_hide_services', 'role_data_access')) {
            $this->forge->addColumn('role_data_access', [
                'product_hide_services' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'null'       => false,
                    'default'    => 0,
                    'after'      => 'isolate_purchase_orders',
                ],
            ]);
        }

        if (! $this->db->fieldExists('product_allowed_categories', 'role_data_access')) {
            $this->forge->addColumn('role_data_access', [
                'product_allowed_categories' => [
                    'type'    => 'TEXT',
                    'null'    => true,
                    'default' => null,
                    'after'   => 'product_hide_services',
                ],
            ]);
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('role_data_access')) {
            return;
        }

        if ($this->db->fieldExists('product_allowed_categories', 'role_data_access')) {
            $this->forge->dropColumn('role_data_access', 'product_allowed_categories');
        }

        if ($this->db->fieldExists('product_hide_services', 'role_data_access')) {
            $this->forge->dropColumn('role_data_access', 'product_hide_services');
        }
    }
}
