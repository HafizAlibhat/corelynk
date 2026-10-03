<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The application writes status values that the ENUM columns never accepted:
 *
 *   SalesOrders::createFromQuotation -> quotations.status   = 'converted' (was 'confirmed')
 *   SalesOrders::cancel              -> sales_orders.status = 'cancelled'
 *
 * Because Config\Database sets strictOn = false, CI4 strips STRICT_TRANS_TABLES
 * from the session, so MySQL silently stored '' instead of raising an error.
 * This widens the ENUMs so those specific writes are legal.
 *
 * CORRECTION (2026-08-28, Phase 2): an earlier version of this docblock claimed
 * widening also means "future invalid writes can fail loudly". That is FALSE.
 * While Config\Database::$strictOn = false, any value outside the ENUM is still
 * stored silently as ''. Widening only legalises the values known at the time --
 * it provides no general protection. Proof: DeliveryOrders.php:1155 writes
 * 'delivered' to this very column and was still silently blanking rows after this
 * migration ran (Phase 2 finding P0-1, fixed in 2026-08-28-000003).
 * Only strict mode is a general guard; see CORELYNK_FORENSIC_PHASE2_GATE.md section M.
 *
 * Schema-only and additive: existing rows (including the corrupted '' ones) are
 * preserved untouched. Backfilling '' is deliberately NOT done here — see
 * CORELYNK_DATA_INTEGRITY.md.
 */
class FixDocumentStatusEnums extends Migration
{
    public function up()
    {
        $this->db->query(
            "ALTER TABLE `quotations` MODIFY `status`
             ENUM('draft','sent','accepted','rejected','converted') NOT NULL DEFAULT 'draft'"
        );

        $this->db->query(
            "ALTER TABLE `sales_orders` MODIFY `status`
             ENUM('draft','confirmed','shipped','closed','cancelled') NOT NULL DEFAULT 'draft'"
        );
    }

    public function down()
    {
        // Rows holding the newly-added values would be truncated to '' on a
        // narrowing ALTER, so park them on the nearest legal value first.
        $this->db->query("UPDATE `quotations`    SET `status` = 'accepted' WHERE `status` = 'converted'");
        $this->db->query("UPDATE `sales_orders`  SET `status` = 'closed'   WHERE `status` = 'cancelled'");

        $this->db->query(
            "ALTER TABLE `quotations` MODIFY `status`
             ENUM('draft','sent','accepted','rejected') NOT NULL DEFAULT 'draft'"
        );
        $this->db->query(
            "ALTER TABLE `sales_orders` MODIFY `status`
             ENUM('draft','confirmed','shipped','closed') NOT NULL DEFAULT 'draft'"
        );
    }
}
