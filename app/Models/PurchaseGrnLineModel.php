<?php

namespace App\Models;

use CodeIgniter\Model;

class PurchaseGrnLineModel extends Model
{
    protected $table = 'purchase_grn_lines';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['grn_id','po_line_id','product_id','variant_id','description','qty_received','unit_price','unit_cost','over_received_qty','over_receipt_reason_type','over_receipt_reason_details','created_at'];
}
