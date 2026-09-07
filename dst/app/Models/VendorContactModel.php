<?php

namespace App\Models;

use CodeIgniter\Model;

class VendorContactModel extends Model
{
    protected $table            = 'vendor_contacts';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'vendor_id','name','phone','cnic','email','designation','is_primary','notes'
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function getByVendor(int $vendorId): array
    {
        return $this->where('vendor_id', $vendorId)
                    ->orderBy('is_primary', 'DESC')
                    ->orderBy('name', 'ASC')
                    ->findAll();
    }
}
