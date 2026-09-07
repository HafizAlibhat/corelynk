<?php

namespace App\Controllers\Api;

use App\Models\QuotationModel;
use App\Models\QuotationLineModel;

/**
 * POST /api/quotations            — Create new quotation
 * GET  /api/quotations            — List quotations
 * GET  /api/quotations/{id}       — Quotation detail with lines
 */
class QuotationApi extends BaseApiController
{
    public function index(): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }
        if (!$this->requirePermission('quotations', 'read')) {
            return $this->response;
        }

        $db = \Config\Database::connect();

        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = min(100, max(1, (int) ($this->request->getGet('per_page') ?? 20)));
        $status  = $this->request->getGet('status');
        $q       = (string) ($this->request->getGet('q') ?? '');
        $offset  = ($page - 1) * $perPage;

        $where  = [];
        $params = [];

        if ($status !== null && $status !== '') {
            $where[]  = 'qt.status = ?';
            $params[] = $status;
        }
        if ($q !== '') {
            $where[]  = '(qt.quote_number LIKE ? OR c.name LIKE ?)';
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = (int) ($db->query(
            "SELECT COUNT(*) AS total FROM quotations qt LEFT JOIN customers c ON qt.customer_id = c.id {$whereClause}",
            $params
        )->getRowArray()['total'] ?? 0);

        $sql = "SELECT qt.id, qt.quote_number, qt.issue_date, qt.status, qt.total,
                       COALESCE(qt.quote_currency, qt.base_currency, 'PKR') AS currency,
                       qt.customer_id,
                       COALESCE(c.name, c.company_name, 'Unknown') AS customer_name
                FROM quotations qt
                LEFT JOIN customers c ON qt.customer_id = c.id
                {$whereClause}
                ORDER BY qt.id DESC
                LIMIT ? OFFSET ?";

        $params[] = $perPage;
        $params[] = $offset;
        $items = $db->query($sql, $params)->getResultArray();

        return $this->success([
            'data'        => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil($total / max(1, $perPage)),
        ]);
    }

    public function show(int $id): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }
        if (!$this->requirePermission('quotations', 'read')) {
            return $this->response;
        }

        $model = new QuotationModel();
        $quote = $model->getWithLines($id);

        if (!$quote) {
            return $this->error('Quotation not found.', 404);
        }

        return $this->success($quote);
    }

    public function create(): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }
        if (!$this->requirePermission('quotations', 'write')) {
            return $this->response;
        }

        $body = $this->getJsonBody();

        if (empty($body['customer_id'])) {
            return $this->error('customer_id is required.');
        }
        if (empty($body['lines']) || !is_array($body['lines'])) {
            return $this->error('At least one line item is required.');
        }

        $model = new QuotationModel();

        $data = [
            'customer_id' => (int) $body['customer_id'],
            'issue_date'     => $body['issue_date'] ?? date('Y-m-d'),
            'status'         => 'draft',
            'quote_currency' => strtoupper($body['currency'] ?? 'PKR'),
            'shipping_amount' => (float) ($body['shipping_amount'] ?? 0),
            'notes'       => $body['notes'] ?? null,
            'created_by'  => $this->apiUser['id'] ?? null,
            'lines'       => [],
        ];

        foreach ($body['lines'] as $ln) {
            $productId = !empty($ln['product_id']) ? (int) $ln['product_id'] : 0;
            $quantity  = (float) ($ln['quantity'] ?? 0);
            $unitPrice = (float) ($ln['unit_price'] ?? 0);

            if ($this->priceRequiresOverride($data['customer_id'], $productId, $quantity, $unitPrice)
                && !service('policy')->can('quotations', 'override_price')) {
                return $this->error('This price requires override permission.');
            }

            $data['lines'][] = [
                'product_id'    => $productId ?: null,
                'description'   => $ln['description'] ?? '',
                'quantity'      => $quantity,
                'unit_price'    => $unitPrice,
                'discount_type' => $ln['discount_type'] ?? 'percent',
                'discount_value'=> (float) ($ln['discount_value'] ?? 0),
                'tax_rate'      => (float) ($ln['tax_rate'] ?? 0),
                'unit'          => $ln['unit'] ?? 'pcs',
            ];
        }

        try {
            $insertId = $model->createQuotation($data);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        if ($insertId === false) {
            return $this->error('Failed to create quotation.');
        }

        // Fetch the quote number
        $created = $model->find($insertId);
        return $this->success([
            'id'           => $insertId,
            'quote_number' => $created['quote_number'] ?? '',
        ], 'Quotation created successfully.', 201);
    }

    /**
     * True if $unitPrice doesn't match any known valid price for the product
     * (customer's tiered price-list price, else the product master sale price),
     * meaning the quotations.override_price permission is required to save it.
     * A $productId of 0 (free-text/manual line) is never validated.
     */
    private function priceRequiresOverride(int $customerId, int $productId, float $quantity, float $unitPrice): bool
    {
        if ($productId <= 0) {
            return false;
        }
        $validPrices = [];
        if ($customerId > 0) {
            try {
                $item = (new \App\Models\PriceListItemModel())->getCustomerProductPrice($customerId, $productId, (int) max(1, $quantity));
                if ($item && isset($item['special_price'])) {
                    $validPrices[] = (float) $item['special_price'];
                }
            } catch (\Throwable $e) { /* best-effort */ }
        }
        try {
            $pricing = (new \App\Models\ProductModel())->getPricingInfo($productId);
            if (isset($pricing['sale_price'])) {
                $validPrices[] = (float) $pricing['sale_price'];
            }
        } catch (\Throwable $e) { /* best-effort */ }

        if (empty($validPrices)) {
            return false;
        }
        foreach ($validPrices as $vp) {
            if (abs($vp - $unitPrice) < 0.005) {
                return false;
            }
        }
        return true;
    }
}
