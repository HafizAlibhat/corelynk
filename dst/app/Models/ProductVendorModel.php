<?php
namespace App\Models;

use CodeIgniter\Model;

class ProductVendorModel extends Model
{
    protected $table = 'product_vendors';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['product_id','vendor_id','vendor_product_code','lead_time_days','last_cost','is_active','created_at','updated_at'];

    public function getVendorCost(int $productId, int $vendorId)
    {
        $row = $this->where('product_id', $productId)->where('vendor_id', $vendorId)->where('is_active', 1)->first();
        if (!$row) return null;
        // last_cost may be null
        return $row;
    }
}
