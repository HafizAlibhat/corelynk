<?php

namespace App\Models;

use CodeIgniter\Model;

class VendorPaymentModel extends Model
{
    protected $table = 'vendor_payments';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'vendor_id',
        'po_id',
        'payment_date',
        'payment_method',
        'payment_type',
        'currency_code',
        'amount',
        'advance_amount',
        'source_account_id',
        'posted_entry_id',
        'cheque_id',
        'reference_no',
        'memo',
        'notes',
        'cheque_payee_name',
        'cheque_notes',
        'cheque_number',
        'cheque_delivery_type',
        'status',
        'created_by',
        'created_at',
        'updated_at',
    ];
    protected $useTimestamps = false;

    /**
     * Get total unallocated advance balance for a specific vendor.
     *
     * Formula:
     * - total_advances = SUM(posted payments where payment_type='advance')
     * - total_applied  = SUM(posted non-advance payments' advance_amount)
     */
    public function getVendorAdvanceBalance(int $vendorId): float
    {
        $db = \Config\Database::connect();
        
        // Total posted advances for this vendor
        $result = $db->query(
            "SELECT COALESCE(SUM(vp.amount), 0) as total_advances
             FROM vendor_payments vp
             WHERE vp.vendor_id = ? AND vp.payment_type = 'advance' AND vp.status = 'posted'",
            [$vendorId]
        )->getRowArray();
        
        $totalAdvances = (float)($result['total_advances'] ?? 0);
        
        // Total advance consumed in posted settlement/bill payments
        $applied = $db->query(
            "SELECT COALESCE(SUM(vp.advance_amount), 0) as total_applied
             FROM vendor_payments vp
             WHERE vp.vendor_id = ?
               AND vp.status = 'posted'
               AND vp.payment_type <> 'advance'",
            [$vendorId]
        )->getRowArray();
        
        $totalApplied = (float)($applied['total_applied'] ?? 0);
        
        // Return remaining balance (ensure non-negative)
        return max(0.0, $totalAdvances - $totalApplied);
    }

    /**
     * Get list of all posted advances for a vendor
     * @param int $vendorId
     * @return array
     */
    public function getVendorAdvances(int $vendorId): array
    {
        return $this->where('vendor_id', $vendorId)
            ->where('payment_type', 'advance')
            ->where('status', 'posted')
            ->findAll();
    }
}
