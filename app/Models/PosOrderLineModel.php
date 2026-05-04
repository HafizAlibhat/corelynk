<?php

namespace App\Models;

use CodeIgniter\Model;

class PosOrderLineModel extends Model
{
    protected $table            = 'pos_order_lines';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'pos_order_id',
        'product_id',
        'variant_id',
        'product_name',
        'variant_name',
        'quantity',
        'unit_price',
        'discount',
        'line_total',
        'notes',
    ];

    /**
     * Get all lines for an order
     */
    public function getByOrder(int $orderId): array
    {
        return $this->where('pos_order_id', $orderId)->findAll();
    }
}
