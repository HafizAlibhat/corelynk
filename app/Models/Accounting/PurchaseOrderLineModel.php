<?php
namespace App\Models\Accounting;
use CodeIgniter\Model;
class PurchaseOrderLineModel extends Model
{
    // Unified DB: removed $DBGroup
    protected $table='purchase_order_lines';
    protected $primaryKey='id';
    protected $allowedFields=['po_id','product_id','description','qty','unit_price','tax_code_id','line_total','qty_received'];
    public $timestamps=false;
}
