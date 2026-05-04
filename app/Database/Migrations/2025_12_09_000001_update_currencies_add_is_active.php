<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdateCurrenciesAddIsActive extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
    $forge = \Config\Database::forge();

        if (! $db->tableExists('currencies')) {
            return;
        }

        // Add is_active column if missing
        if (! $db->fieldExists('is_active', 'currencies')) {
            $fields = [
                'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1]
            ];
            $forge->addColumn('currencies', $fields);
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
    $forge = \Config\Database::forge();
        if (! $db->tableExists('currencies')) return;
        if ($db->fieldExists('is_active', 'currencies')) {
            $forge->dropColumn('currencies', 'is_active');
        }
    }
}
