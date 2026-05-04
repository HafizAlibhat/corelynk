<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductAssetGroupModel extends Model
{
    protected $table = 'product_asset_groups';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'product_id',
        'variant_id',
        'name',
        'description',
        'created_by',
        'created_at',
    ];
    protected $useTimestamps = false;

    protected $validationRules = [
        'product_id' => 'permit_empty|integer',
        'variant_id' => 'permit_empty|integer',
        'name' => 'required|min_length[2]|max_length[150]',
        'description' => 'permit_empty|max_length[2000]',
    ];
}
