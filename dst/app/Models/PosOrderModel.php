<?php

namespace App\Models;

use CodeIgniter\Model;

class PosOrderModel extends Model
{
    protected $table            = 'pos_orders';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    protected $allowedFields = [
        'order_number',
        'order_type',       // dine_in, takeout, delivery
        'customer_name',
        'table_number',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'discount_amount',
        'discount_type',    // percent, fixed
        'total',
        'amount_paid',
        'change_due',
        'payment_method',   // cash, card, custom
        'status',           // open, paid, voided, refunded
        'notes',
        'cashier_id',
        'created_at',
        'updated_at',
    ];

    /**
     * Generate next order number like POS-000001
     */
    public function nextOrderNumber(): string
    {
        $last = $this->selectMax('id')->first();
        $next = ($last['id'] ?? 0) + 1;
        return 'POS-' . str_pad($next, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get today's orders
     */
    public function todaysOrders()
    {
        return $this->where('DATE(created_at)', date('Y-m-d'))
                     ->orderBy('created_at', 'DESC')
                     ->findAll();
    }

    /**
     * Get today's sales total
     */
    public function todaysSalesTotal(): float
    {
        $result = $this->selectSum('total')
                       ->where('DATE(created_at)', date('Y-m-d'))
                       ->where('status', 'paid')
                       ->first();
        return (float)($result['total'] ?? 0);
    }
}
