<?php
namespace App\Models;

use CodeIgniter\Model;

class ProductAttributeModel extends Model
{
    protected $table = 'product_attributes';
    protected $primaryKey = 'id';
    protected $allowedFields = ['name','values','is_active','created_at','updated_at'];
    protected $useTimestamps = false;
}
