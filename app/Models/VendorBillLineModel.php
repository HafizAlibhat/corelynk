<?php

namespace App\Models;

use CodeIgniter\Model;

class VendorBillLineModel extends Model
{
    protected $table = 'vendor_bill_lines';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'vendor_bill_id',
        'po_line_id',
        'processing_record_id',
        'product_id',
        'variant_id',
        'qty',
        'unit_price',
        'line_total',
        'created_at',
    ];
    protected $useTimestamps = false;

    /**
     * Relationship to VendorBillModel
     */
    public function bill()
    {
        return $this->belongsTo('App\Models\VendorBillModel', 'vendor_bill_id', 'id');
    }
}
