<?php

namespace App\Controllers\Api;

use App\Models\PurchaseOrderModel;
use App\Models\PurchaseOrderLineModel;

/**
 * POST /api/purchase-orders       — Create a new purchase order
 *
 * Payload:
 * {
 *   "vendor_id": 1,
 *   "order_date": "2026-04-04",
 *   "delivery_date": "2026-04-15",   // optional
 *   "currency": "PKR",               // optional
 *   "lines": [
 *     { "product_id": 5, "description": "Raw material", "qty": 100, "unit_price": 50 },
 *     ...
 *   ]
 * }
 */
class PurchaseOrderCreateApi extends BaseApiController
{
    public function create(): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }
        if (!$this->requirePermission('purchase_orders', 'write')) {
            return $this->response;
        }

        $body = $this->getJsonBody();

        if (empty($body['vendor_id'])) {
            return $this->error('vendor_id is required.');
        }
        if (empty($body['lines']) || !is_array($body['lines'])) {
            return $this->error('At least one line item is required.');
        }

        $db = \Config\Database::connect();
        $poModel   = new PurchaseOrderModel();
        $lineModel = new PurchaseOrderLineModel();

        $poNumber = $this->generatePONumber($db);

        // Calculate totals
        $subtotal = 0;
        foreach ($body['lines'] as $ln) {
            $qty   = (float) ($ln['qty'] ?? $ln['quantity'] ?? 0);
            $price = (float) ($ln['unit_price'] ?? 0);
            $subtotal += $qty * $price;
        }
        $taxTotal = 0;
        $total = $subtotal + $taxTotal;

        $db->transStart();

        $poModel->insert([
            'po_number'     => $poNumber,
            'vendor_id'     => (int) $body['vendor_id'],
            'order_date'    => $body['order_date'] ?? date('Y-m-d'),
            'delivery_date' => $body['delivery_date'] ?? null,
            'subtotal'      => round($subtotal, 2),
            'tax_total'     => round($taxTotal, 2),
            'total'         => round($total, 2),
            'currency'      => strtoupper($body['currency'] ?? 'PKR'),
            'currency_code' => strtoupper($body['currency'] ?? 'PKR'),
            'status'        => 'draft',
            'created_by'    => $this->apiUser['id'] ?? null,
        ]);
        $poId = (int) $db->insertID();

        if (!$poId) {
            $db->transComplete();
            return $this->error('Failed to create purchase order.');
        }

        foreach ($body['lines'] as $ln) {
            $qty   = (float) ($ln['qty'] ?? $ln['quantity'] ?? 0);
            $price = (float) ($ln['unit_price'] ?? 0);

            $lineModel->insert([
                'po_id'       => $poId,
                'product_id'  => !empty($ln['product_id']) ? (int) $ln['product_id'] : null,
                'variant_id'  => !empty($ln['variant_id']) ? (int) $ln['variant_id'] : null,
                'description' => $ln['description'] ?? '',
                'qty'         => $qty,
                'unit_price'  => round($price, 2),
                'qty_received'=> 0,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->error('Transaction failed while creating purchase order.');
        }

        return $this->success([
            'id'        => $poId,
            'po_number' => $poNumber,
        ], 'Purchase order created successfully.', 201);
    }

    private function generatePONumber($db): string
    {
        $prefix = 'RI-PO-';
        try {
            $row = $db->table('purchase_orders')
                ->select('po_number')
                ->like('po_number', $prefix, 'after')
                ->orderBy('id', 'DESC')
                ->limit(1)
                ->get()->getRowArray();

            $last = 0;
            if ($row && preg_match('/RI-PO-(\d+)/', $row['po_number'], $m)) {
                $last = (int) $m[1];
            }
            return $prefix . str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
        } catch (\Throwable $_) {
            return $prefix . '0001';
        }
    }
}
