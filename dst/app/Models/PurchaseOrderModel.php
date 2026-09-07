<?php

namespace App\Models;

use CodeIgniter\Model;
use App\Traits\PublicIdTrait;

class PurchaseOrderModel extends Model
{
    use PublicIdTrait;

    protected $table = 'purchase_orders';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['public_id','po_number','rfq_id','processing_record_id','vendor_id','status','order_date','delivery_date','subtotal','discount_total','document_discount_type','document_discount_value','discount_exclude_shipping','shipping_amount','tax_total','total','currency','currency_code','created_by','created_at','updated_at','sales_order_id'];

    public function __construct(?\CodeIgniter\Database\ConnectionInterface $db = null, ?\CodeIgniter\Validation\ValidationInterface $validation = null)
    {
        parent::__construct($db, $validation);
        $this->bootPublicId();
    }
}
