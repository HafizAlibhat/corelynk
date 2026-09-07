<?php
namespace App\Models;

use CodeIgniter\Model;

class GRNModel extends Model
{
    protected $table = 'grns';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['po_id','vendor_id','grn_number','received_at','notes','created_by','created_at','updated_at'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
