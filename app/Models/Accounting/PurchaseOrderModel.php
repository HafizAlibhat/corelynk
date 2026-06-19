<?php
namespace App\Models\Accounting;
use CodeIgniter\Model;
class PurchaseOrderModel extends Model
{
    // Unified DB: removed $DBGroup
    protected $table='purchase_orders';
    protected $primaryKey='id';
    protected $allowedFields=['vendor_id','processing_record_id','order_date','status','currency_code','subtotal','discount_total','document_discount_type','document_discount_value','discount_exclude_shipping','shipping_amount','tax_total','total'];
    protected $useTimestamps=true;
}
