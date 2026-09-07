<?php

namespace App\Models;

use CodeIgniter\Model;

class WarehouseLocationModel extends Model
{
    protected $table = 'warehouse_locations';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps = true;
    protected $allowedFields = ['warehouse_id','name','parent_id','is_active','created_at','updated_at'];
}
