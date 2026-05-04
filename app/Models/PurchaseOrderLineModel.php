<?php

namespace App\Models;

use CodeIgniter\Model;

class PurchaseOrderLineModel extends Model
{
    protected $table = 'purchase_order_lines';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['po_id','product_id','variant_id','description','qty','unit_price','qty_received','created_at'];
}
