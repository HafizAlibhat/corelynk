<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * P0-1 (Phase 2) -- delivery completion was silently blanking sales_orders.status.
 *
 * DeliveryOrders.php:1155 writes 'delivered' into sales_orders.status, which was
 * never a member of that ENUM -- not before migration 2026-08-28-000001 and not
 * after it, because that migration only added 'cancelled'.
 *
 * With Config\Database::$strictOn = false, MySQL stored '' instead of raising an
 * error. Proven by exact correlation across all 24 SO/DO pairs:
 *
 *   delivery_orders.status = 'delivered' -> sales_orders.status = ''         (3/3)
 *   delivery_orders.status = 'shipped'   -> sales_orders.status = 'shipped'  (20/20)
 *   delivery_orders.status = 'draft'     -> sales_orders.status = 'confirmed'(1/1)
 *
 * 'delivered' is a real business lifecycle state distinct from 'closed', so the
 * fix is to widen the domain rather than to make the controller write 'closed'
 * (which would collapse two states and lose information).
 *
 * Schema-only and additive. The 3 existing '' rows are deliberately NOT backfilled
 * here -- they are classified HIGH, not CERTAIN, and are scheduled for Batch 2B.
 */
class AddDeliveredToSalesOrderStatus extends Migration
{
    private const WIDENED = "'draft','confirmed','shipped','delivered','closed','cancelled'";
    private const PREVIOUS = "'draft','confirmed','shipped','closed','cancelled'";

    public function up()
    {
        $this->db->query(
            'ALTER TABLE `sales_orders` MODIFY `status` ENUM(' . self::WIDENED . ") NOT NULL DEFAULT 'draft'"
        );
    }

    public function down()
    {
        // A narrowing ALTER would truncate any 'delivered' row to '' -- the exact
        // corruption this migration exists to stop. Park those rows on the nearest
        // legal value first. This is lossy by design: after rollback, a delivered
        // order is indistinguishable from a closed one.
        $this->db->query("UPDATE `sales_orders` SET `status` = 'closed' WHERE `status` = 'delivered'");

        $this->db->query(
            'ALTER TABLE `sales_orders` MODIFY `status` ENUM(' . self::PREVIOUS . ") NOT NULL DEFAULT 'draft'"
        );
    }
}
