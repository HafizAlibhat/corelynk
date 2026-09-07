<?php
namespace App\Models;

use CodeIgniter\Model;

class SalesOrderLineModel extends Model
{
    protected $table = 'sales_order_lines';
    protected $primaryKey = 'id';
    protected $useTimestamps = false;
    protected $allowedFields = ['sales_order_id','product_id','product_variant_id','description','quantity','unit_price','discount_type','discount_value','discount_amount','tax_type','tax_value','tax_rate','tax_amount','line_total','display_type','section_title','sort_order','updated_at'];

    public function product()
    {
        return $this->belongsTo('App\Models\ProductModel', 'product_id', 'id');
    }
}
