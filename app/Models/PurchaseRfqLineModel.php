<?php

namespace App\Models;

use CodeIgniter\Model;

class PurchaseRfqLineModel extends Model
{
    protected $table = 'purchase_rfq_lines';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    // support both legacy and current column names; DB uses quantity/unit_cost
    // Add per-line discount/tax fields and line_total
    protected $allowedFields = [
        'rfq_id','product_id','product_variant_id','description','qty','quantity','unit_price','unit_cost',
        'discount','discount_percent','tax_percent','tax_amount','line_total','created_at'
    ];
}
