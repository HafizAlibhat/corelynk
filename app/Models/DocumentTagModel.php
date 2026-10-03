<?php

namespace App\Models;

use CodeIgniter\Model;

class DocumentTagModel extends Model
{
    protected $table = 'document_tags';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'tag_id',
        'document_type',
        'document_id',
        'created_by',
        'created_at',
    ];
    protected $useTimestamps = false;
    protected $validationRules = [
        'tag_id' => 'required|integer',
        'document_type' => 'required|max_length[80]',
        'document_id' => 'required|integer',
        'created_by' => 'permit_empty|integer',
    ];
}
