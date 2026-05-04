<?php

namespace App\Models;

use CodeIgniter\Model;

class DeliveryOrderTrackingDocModel extends Model
{
    protected $table         = 'delivery_order_tracking_docs';
    protected $primaryKey    = 'id';
    protected $useTimestamps = false;
    protected $allowedFields = ['delivery_order_id', 'file_path', 'original_name', 'created_at'];

    public function getForDo(int $doId): array
    {
        return $this->where('delivery_order_id', $doId)
                    ->orderBy('id', 'ASC')
                    ->findAll();
    }
}
