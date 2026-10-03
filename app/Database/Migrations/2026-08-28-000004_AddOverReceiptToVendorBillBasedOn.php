<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * P0-3 (Phase 2) -- vendor_bills.based_on was silently truncated to ''.
 *
 * The column was ENUM('po_qty','grn_qty','manual') while the application writes
 * two further values:
 *
 *   'po_over_receipt'    NewPurchaseGrns.php:1710, NewPurchaseOrders.php:340
 *   'po_qty_adjustment'  NewPurchaseOrders.php:2462
 *
 * With strictOn = false these were stored as ''. Unlike the other members of this
 * defect family, this one corrupts arithmetic rather than display:
 *
 *   1. NewPurchaseOrders.php:177 sums base billed qty with
 *      LOWER(COALESCE(vb.based_on,'')) <> 'po_over_receipt'.
 *      '' <> 'po_over_receipt' is TRUE, so over-receipt billing is counted as
 *      base PO billing, inflating billed-to-date.
 *   2. NewPurchaseOrders.php:235 sums extra billed qty with = 'po_over_receipt',
 *      which matches nothing -- the system believes no over-receipt was ever billed.
 *   3. NewPurchaseOrders.php:1227 and :2529 guard against re-billing an
 *      over-receipt with where('based_on','po_over_receipt'). That guard cannot
 *      see the affected bills, so duplicate over-receipt billing is not prevented.
 *
 * Schema-only and additive. Bills 13, 14 and 29 are deliberately NOT backfilled
 * here -- scheduled for Batch 2B.
 */
class AddOverReceiptToVendorBillBasedOn extends Migration
{
    private const WIDENED  = "'po_qty','grn_qty','manual','po_over_receipt','po_qty_adjustment'";
    private const PREVIOUS = "'po_qty','grn_qty','manual'";

    public function up()
    {
        $this->db->query(
            'ALTER TABLE `vendor_bills` MODIFY `based_on` ENUM(' . self::WIDENED . ") NOT NULL DEFAULT 'manual'"
        );
    }

    public function down()
    {
        // Park rows on a legal value before narrowing, otherwise they truncate to ''.
        // Lossy by design: after rollback an over-receipt bill is indistinguishable
        // from a manual one.
        $this->db->query(
            "UPDATE `vendor_bills` SET `based_on` = 'manual'
             WHERE `based_on` IN ('po_over_receipt','po_qty_adjustment')"
        );

        $this->db->query(
            'ALTER TABLE `vendor_bills` MODIFY `based_on` ENUM(' . self::PREVIOUS . ") NOT NULL DEFAULT 'manual'"
        );
    }
}
