<?php

namespace App\Models;

use CodeIgniter\Model;

class CustomerAddressModel extends Model
{
    protected $table = 'customer_addresses';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'customer_id', 'line1', 'line2', 'city_id', 'state_id', 'country_id', 'city_name', 'state_name', 'postal_code', 'is_billing', 'is_shipping', 'is_default', 'label'
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
