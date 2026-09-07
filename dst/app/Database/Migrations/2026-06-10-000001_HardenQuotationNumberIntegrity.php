<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class HardenQuotationNumberIntegrity extends Migration
{
    public function up()
    {
        // Ensure no duplicate quote numbers exist before adding unique index.
        $dups = $this->db->query(
            "SELECT quote_number, COUNT(*) AS cnt FROM quotations GROUP BY quote_number HAVING COUNT(*) > 1 LIMIT 1"
        )->getRowArray();

        if (!empty($dups)) {
            throw new \RuntimeException(
                'Cannot add unique index to quotations.quote_number; duplicate value found: ' . (string) ($dups['quote_number'] ?? '')
            );
        }

        $idx = $this->db->query("SHOW INDEX FROM quotations WHERE Key_name = 'uq_quotations_quote_number'")->getResultArray();
        if (empty($idx)) {
            $this->db->query('ALTER TABLE quotations ADD UNIQUE KEY uq_quotations_quote_number (quote_number)');
        }
    }

    public function down()
    {
        $idx = $this->db->query("SHOW INDEX FROM quotations WHERE Key_name = 'uq_quotations_quote_number'")->getResultArray();
        if (!empty($idx)) {
            $this->db->query('ALTER TABLE quotations DROP INDEX uq_quotations_quote_number');
        }
    }
}
