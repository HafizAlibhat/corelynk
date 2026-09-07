<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddExcludedCombosToProducts extends Migration
{
    public function up()
    {
        try {
            $this->db->query("ALTER TABLE products ADD COLUMN excluded_combos TEXT NULL DEFAULT NULL AFTER attributes_definitions");
        } catch (\Throwable $e) {
            // Column may already exist; ignore
        }
    }

    public function down()
    {
        try {
            $this->db->query("ALTER TABLE products DROP COLUMN excluded_combos");
        } catch (\Throwable $e) {}
    }
}
