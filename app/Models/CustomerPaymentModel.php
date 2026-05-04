<?php

namespace App\Models;

use CodeIgniter\Model;

class CustomerPaymentModel extends Model
{
    protected $table = 'customer_payments';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'customer_id',
        'payment_date',
        'payment_method',
        'payment_method_id',
        'payment_type',
        'currency_code',
        'amount',
        'advance_amount',
        'source_account_id',
        'posted_entry_id',
        'reference_no',
        'reference_number',
        'memo',
        'notes',
        'status',
        'created_by',
        'created_at',
        'updated_at',
    ];
    protected $useTimestamps = false;

    public function getCustomerAdvanceBalance(int $customerId): float
    {
        $db = \Config\Database::connect();
        $result = $db->query(
            "SELECT COALESCE(SUM(cp.amount),0) as total_advances FROM customer_payments cp WHERE cp.customer_id = ? AND cp.payment_type = 'advance' AND cp.status = 'posted'",
            [$customerId]
        )->getRowArray();

        $totalAdvances = (float) ($result['total_advances'] ?? 0);

        $applied = $db->query(
            "SELECT COALESCE(SUM(cp.advance_amount),0) as total_applied FROM customer_payments cp WHERE cp.customer_id = ? AND cp.status = 'posted' AND cp.payment_type <> 'advance'",
            [$customerId]
        )->getRowArray();

        $totalApplied = (float) ($applied['total_applied'] ?? 0);

        return max(0.0, $totalAdvances - $totalApplied);
    }
}
