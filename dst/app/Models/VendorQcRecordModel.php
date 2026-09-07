<?php

namespace App\Models;

use CodeIgniter\Model;

class VendorQcRecordModel extends Model
{
    protected $table            = 'vendor_qc_records';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'receive_item_id',
        'check_name',
        'status',
        'remarks',
        'created_at',
    ];
}
