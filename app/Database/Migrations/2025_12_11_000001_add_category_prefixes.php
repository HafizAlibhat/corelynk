<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCategoryPrefixes extends Migration
{
    public function up()
    {
    $db = \Config\Database::connect();
    // Use forge() without boolean to avoid assertion on group type
    $forge = \Config\Database::forge();

        if (! $db->tableExists('product_categories')) {
            return;
        }

        // Add columns if they don't exist
        $fields = [];
        if (! $db->fieldExists('prefix', 'product_categories')) {
            $fields['prefix'] = ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true];
        }
        if (! $db->fieldExists('start_range', 'product_categories')) {
            $fields['start_range'] = ['type' => 'INT', 'unsigned' => true, 'default' => 1];
        }
        if (! $db->fieldExists('end_range', 'product_categories')) {
            $fields['end_range'] = ['type' => 'INT', 'unsigned' => true, 'default' => 999999];
        }
        if (! $db->fieldExists('next_number', 'product_categories')) {
            $fields['next_number'] = ['type' => 'INT', 'unsigned' => true, 'default' => 1];
        }

        if (! empty($fields)) {
            $forge->addColumn('product_categories', $fields);
        }

        // Add unique index on prefix (ignore errors)
        try {
            $forge->addKey('prefix', true);
        } catch (\Throwable $e) {
            // ignore if key exists
        }

        // Initialize next_number to start_range where null or zero
        $db->query("UPDATE `product_categories` SET `next_number` = `start_range` WHERE `next_number` IS NULL OR `next_number` = 0");
    }

    public function down()
    {
        $db = \Config\Database::connect();
        $forge = \Config\Database::forge(true);

        if (! $db->tableExists('product_categories')) {
            return;
        }

        // Drop columns if exist
        $cols = ['prefix', 'start_range', 'end_range', 'next_number'];
        foreach ($cols as $c) {
            if ($db->fieldExists($c, 'product_categories')) {
                $forge->dropColumn('product_categories', $c);
            }
        }
    }
}
