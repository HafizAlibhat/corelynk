<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductAssetModel extends Model
{
    protected $table = 'product_assets';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'asset_group_id',
        'channel_id',
        'source_asset_id',
        'type',
        'section_key',
        'section_label',
        'file_path',
        'thumbnail_path',
        'file_name',
        'file_size',
        'mime_type',
        'is_primary',
        'tags',
        'uploaded_by',
        'created_at',
    ];
    protected $useTimestamps = false;

    protected $validationRules = [
        'asset_group_id' => 'required|integer',
        'channel_id' => 'permit_empty|integer',
        'source_asset_id' => 'permit_empty|integer',
        'type' => 'required|in_list[source,final,watermark,template]',
        'file_path' => 'required|max_length[255]',
        'file_name' => 'required|max_length[255]',
        'file_size' => 'required|integer|greater_than_equal_to[0]',
        'mime_type' => 'required|max_length[120]',
        'is_primary' => 'permit_empty|in_list[0,1]',
    ];
}
