<?php

namespace App\Models;

use CodeIgniter\Model;
use App\Traits\PublicIdTrait;

class DeliveryOrderModel extends Model
{
    use PublicIdTrait;
    protected $table = 'delivery_orders';
    protected $primaryKey = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'public_id',
        'sales_order_id',
        'do_number',
        'status',
        'shipping_vendor_id',
        'shipping_service_id',
        'final_weight_kg',
        'shipping_cost_pkr',
        'tracking_number',
        'tracking_url',
        'destination_country',
        'shipping_notes',
        'parcel_image',
        'shipped_at',
        'estimated_delivery_days',
        'delivery_status',
        'delivery_confirmed_at',
        'delivery_notes',
        'shipping_po_id',
        'shipping_bill_id',
        'delivered_at',
        'delivery_screenshot',
    ];

    public function getWithLines(int $doId)
    {
        $do = $this->find($doId);
        if (!$do) {
            return null;
        }

        $lineModel = new DeliveryOrderLineModel();
        $do['lines'] = $lineModel->where('delivery_order_id', $doId)->findAll();

        return $do;
    }
}
