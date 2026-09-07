<?php

namespace App\Models;

use CodeIgniter\Model;

class SubcontractOrderLineModel extends Model
{
    protected $table            = 'subcontract_order_lines';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'subcontract_order_id', 'product_id', 'variant_id',
        'description', 'qty_sent', 'qty_received', 'qty_scrap',
        'warehouse_id', 'location_id',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'subcontract_order_id' => 'required|integer',
        'product_id'           => 'required|integer',
        'qty_sent'             => 'required|numeric|greater_than[0]',
    ];

    /**
     * Get total sent/received/scrap for an order
     */
    public function getOrderTotals(int $orderId): array
    {
        $db = \Config\Database::connect();
        $row = $db->table($this->table)
                  ->select('SUM(qty_sent) as total_sent, SUM(qty_received) as total_received, SUM(qty_scrap) as total_scrap')
                  ->where('subcontract_order_id', $orderId)
                  ->get()
                  ->getRowArray();

        return [
            'total_sent'     => (float) ($row['total_sent'] ?? 0),
            'total_received' => (float) ($row['total_received'] ?? 0),
            'total_scrap'    => (float) ($row['total_scrap'] ?? 0),
        ];
    }
}
