<?php

namespace App\Controllers\Api;

use App\Models\SalesOrderModel;
use App\Models\SalesOrderLineModel;

/**
 * POST /api/sales-orders          — Create a new sales order
 *
 * Payload:
 * {
 *   "customer_id": 1,
 *   "order_date": "2026-04-04",   // optional, defaults to today
 *   "currency": "PKR",            // optional
 *   "lines": [
 *     { "product_id": 5, "description": "Widget", "quantity": 10, "unit_price": 250 },
 *     ...
 *   ]
 * }
 */
class SalesOrderCreateApi extends BaseApiController
{
    public function create(): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }
        if (!$this->requirePermission('sales_orders', 'write')) {
            return $this->response;
        }

        $body = $this->getJsonBody();

        // Validate required fields
        if (empty($body['customer_id'])) {
            return $this->error('customer_id is required.');
        }
        if (empty($body['lines']) || !is_array($body['lines'])) {
            return $this->error('At least one line item is required.');
        }

        $db = \Config\Database::connect();
        $soModel   = new SalesOrderModel();
        $lineModel = new SalesOrderLineModel();

        // Generate order number
        $orderNumber = $this->generateSONumber($db);

        // Calculate totals
        $subtotal = 0;
        $taxTotal = 0;
        foreach ($body['lines'] as $ln) {
            $qty   = (float) ($ln['quantity'] ?? 0);
            $price = (float) ($ln['unit_price'] ?? 0);
            $lineTotal = $qty * $price;
            $subtotal += $lineTotal;
        }
        $total = $subtotal + $taxTotal;

        $db->transStart();

        // Insert header
        $soModel->insert([
            'order_number' => $orderNumber,
            'customer_id'  => (int) $body['customer_id'],
            'order_date'   => $body['order_date'] ?? date('Y-m-d'),
            'subtotal'     => round($subtotal, 2),
            'tax_total'    => round($taxTotal, 2),
            'total'        => round($total, 2),
            'currency'     => strtoupper($body['currency'] ?? 'PKR'),
            'currency_code'=> strtoupper($body['currency'] ?? 'PKR'),
            'status'       => 'draft',
            'created_by'   => $this->apiUser['id'] ?? null,
        ]);
        $soId = (int) $db->insertID();

        if (!$soId) {
            $db->transComplete();
            return $this->error('Failed to create sales order.');
        }

        // Insert lines
        foreach ($body['lines'] as $ln) {
            $qty   = (float) ($ln['quantity'] ?? 0);
            $price = (float) ($ln['unit_price'] ?? 0);

            $lineModel->insert([
                'sales_order_id'   => $soId,
                'product_id'       => !empty($ln['product_id']) ? (int) $ln['product_id'] : null,
                'product_variant_id' => !empty($ln['product_variant_id']) ? (int) $ln['product_variant_id'] : null,
                'description'      => $ln['description'] ?? '',
                'quantity'         => $qty,
                'unit_price'       => round($price, 2),
                'line_total'       => round($qty * $price, 2),
            ]);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->error('Transaction failed while creating sales order.');
        }

        return $this->success([
            'id'           => $soId,
            'order_number' => $orderNumber,
        ], 'Sales order created successfully.', 201);
    }

    private function generateSONumber($db): string
    {
        $prefix = 'RI-S';
        try {
            $row = $db->table('sales_orders')
                ->select('order_number')
                ->like('order_number', $prefix, 'after')
                ->orderBy('id', 'DESC')
                ->limit(1)
                ->get()->getRowArray();

            $last = 0;
            if ($row && preg_match('/RI-S(\d+)/', $row['order_number'], $m)) {
                $last = (int) $m[1];
            }
            return $prefix . str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
        } catch (\Throwable $_) {
            return $prefix . '0001';
        }
    }
}
