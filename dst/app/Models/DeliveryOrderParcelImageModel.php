<?php

namespace App\Models;

use CodeIgniter\Model;

class DeliveryOrderParcelImageModel extends Model
{
    protected $table         = 'delivery_order_parcel_images';
    protected $primaryKey    = 'id';
    protected $useTimestamps = false;
    protected $allowedFields = ['delivery_order_id', 'image_path', 'created_at'];

    public function getForDo(int $doId): array
    {
        return $this->where('delivery_order_id', $doId)
                    ->orderBy('id', 'ASC')
                    ->findAll();
    }
}
