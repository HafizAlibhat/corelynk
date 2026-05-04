<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddParentIdToProductCategories extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        $forge = \Config\Database::forge();

        if (! $db->tableExists('product_categories')) {
            return;
        }

        if (! $db->fieldExists('parent_id', 'product_categories')) {
            $fields = [
                'parent_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            ];
            $forge->addColumn('product_categories', $fields);

            // Add index on parent_id for faster hierarchy queries
            try {
                $forge->addKey('parent_id');
            } catch (\Throwable $e) {
                // ignore if key exists
            }
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        $forge = \Config\Database::forge(true);

        if (! $db->tableExists('product_categories')) {
            return;
        }

        if ($db->fieldExists('parent_id', 'product_categories')) {
            $forge->dropColumn('product_categories', 'parent_id');
        }
    }
}
