<?php

namespace App\Models;

use CodeIgniter\Model;

class TagModel extends Model
{
    protected $table = 'tags';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'name',
        'slug',
        'description',
        'created_by',
        'created_at',
        'updated_at',
    ];
    protected $useTimestamps = false;
    protected $validationRules = [
        'name' => 'required|max_length[100]',
        'slug' => 'required|alpha_dash|max_length[120]',
        'description' => 'permit_empty|max_length[1000]',
    ];
}
