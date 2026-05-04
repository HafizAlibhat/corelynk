<?php

namespace App\Models;

use CodeIgniter\Model;

class CustomerPaymentAllocationModel extends Model
{
    protected $table = 'customer_payment_allocations';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'payment_id',
        'invoice_id',
        'allocated_amount',
        'amount',
        'amount_allocated',
        'cash_amount',
        'advance_amount',
        'allocated_at',
        'created_by',
        'created_at',
    ];
    protected $useTimestamps = false;
}
