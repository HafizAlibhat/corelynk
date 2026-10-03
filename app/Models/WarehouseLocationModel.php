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
    protected $allowedFields = ['warehouse_id','vendor_id','name','parent_id','is_active','created_at','updated_at'];

    /**
     * Drop-off points that belong to a vendor (branch / office / working setup).
     * Locations with no vendor_id are our own storage and must never be offered
     * as a vendor destination.
     */
    public function getVendorLocations(?int $vendorId = null): array
    {
        $builder = $this->select('warehouse_locations.id, warehouse_locations.name, warehouse_locations.vendor_id, w.name AS warehouse_name')
            ->join('warehouses w', 'w.id = warehouse_locations.warehouse_id', 'left')
            ->where('warehouse_locations.vendor_id IS NOT NULL')
            ->where('warehouse_locations.is_active', 1);

        if ($vendorId !== null) {
            $builder->where('warehouse_locations.vendor_id', $vendorId);
        }

        return $builder->orderBy('warehouse_locations.name', 'ASC')->findAll();
    }
}
