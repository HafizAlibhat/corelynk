<?php

namespace App\Models;

use CodeIgniter\Model;
use App\Traits\PublicIdTrait;

class PurchaseRfqModel extends Model
{
    use PublicIdTrait;
    protected $table = 'purchase_rfqs';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    // include cancel fields for soft-cancel auditability
    // Note: keep backward-compatible fields (discount, tax_amount) and add total_discount/total_tax
    protected $allowedFields = [
        'public_id','rfq_number','vendor_id','status','notes','created_by','created_at','updated_at',
        'cancel_reason','cancelled_at','cancelled_by','rfq_date','delivery_date',
        'subtotal','discount','tax_amount','grand_total',
        'total_discount','total_tax',
        'currency'
    ];
}

