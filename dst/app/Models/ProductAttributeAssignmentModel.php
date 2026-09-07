<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductAttributeAssignmentModel extends Model
{
    protected $table            = 'product_attribute_assignments';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields    = [
        'product_id',
        'attribute_id',
        'position',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'product_id'   => 'required|integer',
        'attribute_id' => 'required|integer',
        'position'     => 'permit_empty|integer',
    ];
}
