<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductAssetListingModel extends Model
{
    protected $table = 'product_asset_listings';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'product_id',
        'channel_id',
        'listing_url',
        'notes',
        'created_by',
        'created_at',
    ];
    protected $useTimestamps = false;

    protected $validationRules = [
        'product_id' => 'required|integer',
        'channel_id' => 'required|integer',
        'listing_url' => 'required|valid_url|max_length[255]',
        'notes' => 'permit_empty|max_length[2000]',
    ];
}
