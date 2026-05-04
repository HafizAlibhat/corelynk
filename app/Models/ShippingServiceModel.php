<?php
namespace App\Models;

use CodeIgniter\Model;

class ShippingServiceModel extends Model
{
    protected $table = 'shipping_services';
    protected $primaryKey = 'id';
    protected $allowedFields = ['carrier','vendor_id','service_name','min_weight','base_rate','rate_per_kg','cost_pkr','base_rate_pkr','rate_per_kg_pkr','product_id','account_number','currency','active','metadata','created_at','updated_at'];

    public function getActiveServices(): array
    {
        return $this->where('active', 1)->orderBy('carrier')->orderBy('service_name')->findAll();
    }

    public function findById($id)
    {
        return $this->asArray()->find($id);
    }
}
