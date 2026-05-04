<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: Universal Art Number System
 *
 * Switches from per-category number ranges to a single global counter with
 * category suffix.  Format: RI-<SUFFIX>-<PADDED_GLOBAL_NUMBER>
 *
 * Changes:
 *  1. Adds `suffix` column (VARCHAR 4, UNIQUE) to product_categories.
 *  2. Creates `art_number_counter` table with a single row holding the global
 *     sequential counter.
 *  3. Keeps legacy columns (prefix, start_range, end_range, next_number) intact
 *     so existing data/code that reads them won't break.
 */
class AddUniversalArtNumberSystem extends Migration
{
    public function up()
    {
        // 1. Add suffix column to product_categories
        if (! $this->db->fieldExists('suffix', 'product_categories')) {
            $this->forge->addColumn('product_categories', [
                'suffix' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 4,
                    'null'       => true,
                    'default'    => null,
                    'after'      => 'prefix',
                ],
            ]);

            // Add unique index on suffix (ignoring NULLs by default in MySQL)
            $this->db->query('CREATE UNIQUE INDEX idx_categories_suffix ON product_categories(suffix)');
        }

        // 2. Create global art number counter table
        if (! $this->db->tableExists('art_number_counter')) {
            $this->forge->addField([
                'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'next_number'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => false, 'default' => 1],
                'updated_at'   => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->createTable('art_number_counter', true);

            // Seed with initial row – next_number = 1 (or higher if you want to start elsewhere)
            $this->db->table('art_number_counter')->insert([
                'id'          => 1,
                'next_number' => 1,
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down()
    {
        // Drop art_number_counter table
        if ($this->db->tableExists('art_number_counter')) {
            $this->forge->dropTable('art_number_counter', true);
        }

        // Drop suffix column
        if ($this->db->fieldExists('suffix', 'product_categories')) {
            try {
                $this->db->query('DROP INDEX idx_categories_suffix ON product_categories');
            } catch (\Throwable $_) {}
            $this->forge->dropColumn('product_categories', 'suffix');
        }
    }
}
