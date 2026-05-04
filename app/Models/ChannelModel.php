<?php

namespace App\Models;

use CodeIgniter\Model;

class ChannelModel extends Model
{
    protected $table = 'channels';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'name',
        'short_code',
        'width',
        'height',
        'max_file_size',
        'allowed_formats',
        'rules_json',
        'background_rule',
        'notes',
        'created_by',
        'created_at',
    ];
    protected $useTimestamps = false;

    protected $validationRules = [
        'id' => 'permit_empty|integer',
        'name' => 'required|min_length[2]|max_length[120]|is_unique[channels.name,id,{id}]',
        'short_code' => 'permit_empty|max_length[20]',
        'width' => 'permit_empty|integer|greater_than_equal_to[1]',
        'height' => 'permit_empty|integer|greater_than_equal_to[1]',
        'max_file_size' => 'permit_empty|integer|greater_than_equal_to[1]',
        'background_rule' => 'permit_empty|in_list[white,transparent,any]',
    ];
}
