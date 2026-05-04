<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductAttributeValueModel extends Model
{
    protected $table            = 'product_attribute_values';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields    = [
        'attribute_id',
        'value',
        'code',
        'sort_order',
        'is_active',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'attribute_id' => 'required|integer',
        'value'        => 'required|min_length[1]|max_length[150]',
        'code'         => 'required|min_length[1]|max_length[32]',
        'sort_order'   => 'permit_empty|integer',
        'is_active'    => 'permit_empty|in_list[0,1]',
    ];
}
