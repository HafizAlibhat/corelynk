<?php
namespace App\Models;

use CodeIgniter\Model;

class VariantInventoryModel extends Model
{
    protected $table = 'variant_inventory';
    protected $primaryKey = 'id';
    protected $allowedFields = ['variant_id','warehouse_id','quantity','reserved','created_at','updated_at'];
    protected $useTimestamps = false;
}
