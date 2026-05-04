<?php

namespace App\Models;

use CodeIgniter\Model;
use App\Traits\PublicIdTrait;

class SubcontractOrderModel extends Model
{
    use PublicIdTrait;
    protected $table            = 'subcontract_orders';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'public_id', 'order_number', 'vendor_id', 'service_product_id', 'service_variant_id',
        'po_id', 'status', 'quantity', 'unit_price', 'currency', 'total',
        'issued_date', 'expected_return_date', 'actual_return_date',
        'warehouse_id', 'location_id', 'notes', 'created_by',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'vendor_id'          => 'required|integer',
        'service_product_id' => 'required|integer',
        'quantity'           => 'required|numeric|greater_than[0]',
        'unit_price'         => 'required|numeric|greater_than_equal_to[0]',
        'status'             => 'permit_empty|in_list[draft,confirmed,issued,partial_return,done,cancelled]',
    ];

    protected $validationMessages = [
        'vendor_id'          => ['required' => 'Vendor is required'],
        'service_product_id' => ['required' => 'Service product is required'],
        'quantity'           => ['required' => 'Quantity is required', 'greater_than' => 'Quantity must be greater than 0'],
    ];

    /**
     * Statuses with labels and badge classes
     */
    public static function statusOptions(): array
    {
        return [
            'draft'          => ['label' => 'Draft',          'badge' => 'secondary'],
            'confirmed'      => ['label' => 'Confirmed',      'badge' => 'primary'],
            'issued'         => ['label' => 'Materials Issued','badge' => 'warning'],
            'partial_return' => ['label' => 'Partial Return',  'badge' => 'info'],
            'done'           => ['label' => 'Done',            'badge' => 'success'],
            'cancelled'      => ['label' => 'Cancelled',       'badge' => 'danger'],
        ];
    }

    /**
     * Generate the next order number: SC-YYYY-NNNNN
     */
    public function generateOrderNumber(): string
    {
        $prefix = 'SC-' . date('Y') . '-';
        $db = \Config\Database::connect();

        // Use sequences table for atomicity
        try {
            $db->query("UPDATE `sequences` SET `last_value` = LAST_INSERT_ID(`last_value` + 1), `updated_at` = NOW() WHERE `name` = 'subcontract_order'");
            $nextVal = (int) $db->query("SELECT LAST_INSERT_ID() as val")->getRow()->val;
            if ($nextVal > 0) {
                return $prefix . str_pad($nextVal, 5, '0', STR_PAD_LEFT);
            }
        } catch (\Throwable $e) {
            // Fallback: derive from max order number
        }

        // Fallback
        $last = $this->like('order_number', $prefix, 'after')
                     ->orderBy('id', 'DESC')
                     ->first();

        $next = 1;
        if ($last) {
            $next = intval(substr($last['order_number'], strlen($prefix))) + 1;
        }
        return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Get order with vendor and service product info
     */
    public function getWithDetails(int $id): ?array
    {
        return $this->select('subcontract_orders.*, v.name as vendor_name, p.name as service_product_name, p.code as service_product_code, p.unit as service_unit')
                    ->join('vendors v', 'v.id = subcontract_orders.vendor_id', 'left')
                    ->join('products p', 'p.id = subcontract_orders.service_product_id', 'left')
                    ->where('subcontract_orders.id', $id)
                    ->first();
    }

    /**
     * Get list with filters and pagination
     */
    public function getListFiltered(?string $search = null, ?string $status = null, ?int $vendorId = null, int $perPage = 20): array
    {
        $builder = $this->select('subcontract_orders.*, v.name as vendor_name, p.name as service_product_name')
                        ->join('vendors v', 'v.id = subcontract_orders.vendor_id', 'left')
                        ->join('products p', 'p.id = subcontract_orders.service_product_id', 'left');

        if (!empty($search)) {
            $builder->groupStart()
                    ->like('subcontract_orders.order_number', $search)
                    ->orLike('v.name', $search)
                    ->orLike('p.name', $search)
                    ->groupEnd();
        }
        if (!empty($status)) {
            $builder->where('subcontract_orders.status', $status);
        }
        if (!empty($vendorId)) {
            $builder->where('subcontract_orders.vendor_id', $vendorId);
        }

        $builder->orderBy('subcontract_orders.created_at', 'DESC');
        return $builder->paginate($perPage);
    }

    /**
     * Get lines for an order
     */
    public function getLines(int $orderId): array
    {
        $db = \Config\Database::connect();
        return $db->table('subcontract_order_lines sol')
                  ->select('sol.*, p.name as product_name, p.code as product_code, p.unit as product_unit, pv.art_number as variant_art_number, pv.name as variant_name')
                  ->join('products p', 'p.id = sol.product_id', 'left')
                  ->join('product_variants pv', 'pv.id = sol.variant_id', 'left')
                  ->where('sol.subcontract_order_id', $orderId)
                  ->orderBy('sol.id', 'ASC')
                  ->get()
                  ->getResultArray();
    }

    /**
     * Calculate total for an order
     */
    public function recalculateTotal(int $orderId): void
    {
        $order = $this->find($orderId);
        if ($order) {
            $total = round((float)$order['quantity'] * (float)$order['unit_price'], 2);
            $this->update($orderId, ['total' => $total]);
        }
    }
}
