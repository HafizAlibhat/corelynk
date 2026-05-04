<?php

namespace App\Models;

use CodeIgniter\Model;

class VendorPaymentAllocationModel extends Model
{
    protected $table = 'vendor_payment_allocations';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'payment_id',
        'vendor_bill_id',
        'purchase_order_id',
        // Legacy column used by cheque module and balance calculations
        'amount',
        'amount_allocated',
        'allocated_at',
        'created_by',
        'created_at',
    ];
    protected $useTimestamps = false;
}
