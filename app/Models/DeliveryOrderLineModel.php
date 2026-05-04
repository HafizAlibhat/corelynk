<?php

namespace App\Models;

use CodeIgniter\Model;

class DeliveryOrderLineModel extends Model
{
    protected $table = 'delivery_order_lines';
    protected $primaryKey = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'delivery_order_id',
        'sales_order_line_id',
        'product_id',
        'variant_id',
        'quantity_ordered',
        'ready_qty',
        'qty_to_ship',
    ];
}
