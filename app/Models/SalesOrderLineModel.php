<?php
namespace App\Models;

use CodeIgniter\Model;

class SalesOrderLineModel extends Model
{
    protected $table = 'sales_order_lines';
    protected $primaryKey = 'id';
    protected $useTimestamps = false;
    protected $allowedFields = ['sales_order_id','product_id','product_variant_id','description','quantity','unit_price','line_total'];

    public function product()
    {
        return $this->belongsTo('App\Models\ProductModel', 'product_id', 'id');
    }
}
