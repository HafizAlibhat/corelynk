<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Same defect class as 2026-08-28-000001, but in accounting.
 *
 * journal_entries.source_type was ENUM('invoice','payment','cheque','credit_note','manual'),
 * yet the application posts these values:
 *
 *   'vendor_bill'                     DeliveryOrders.php:1038, VendorBill posting
 *   'vendor_payment'                  vendor payment posting
 *   'vendor_advance_application'       advance application posting
 *   'vendor_bill_advance_application'  advance application posting
 *   'mobile_expense'                   Api/ExpenseApi.php:140
 *
 * With strictOn = false these were silently stored as '', which broke two things:
 *
 *   1. VendorLedger.php:108-111 filters on source_type = 'vendor_bill'/'vendor_payment'
 *      and therefore returns ZERO journal rows -- the vendor ledger is silently blank.
 *   2. AccountingJournals.php:382 locks an entry with
 *      !empty($je['source_type']) && $je['source_type'] !== 'manual'.
 *      An empty string is empty(), so 24 system-generated journals are treated as
 *      freely-editable manual entries.
 *
 * Schema-only. Existing '' rows are preserved; see CORELYNK_DATA_INTEGRITY.md for the
 * backfill proposal, which needs human review because '' is ambiguous.
 */
class FixJournalSourceTypeEnum extends Migration
{
    private const VALUES = "'invoice','payment','cheque','credit_note','manual',"
        . "'vendor_bill','vendor_payment','vendor_advance_application',"
        . "'vendor_bill_advance_application','mobile_expense'";

    public function up()
    {
        $this->db->query(
            'ALTER TABLE `journal_entries` MODIFY `source_type` ENUM(' . self::VALUES . ') NULL DEFAULT NULL'
        );
    }

    public function down()
    {
        // Narrowing would truncate the newly-legal values to ''; null them instead so
        // no row silently changes meaning.
        $this->db->query(
            "UPDATE `journal_entries` SET `source_type` = NULL
              WHERE `source_type` IN ('vendor_bill','vendor_payment','vendor_advance_application',
                                      'vendor_bill_advance_application','mobile_expense')"
        );
        $this->db->query(
            "ALTER TABLE `journal_entries` MODIFY `source_type`
             ENUM('invoice','payment','cheque','credit_note','manual') NULL DEFAULT NULL"
        );
    }
}
