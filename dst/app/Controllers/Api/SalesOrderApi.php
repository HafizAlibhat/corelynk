<?php

namespace App\Controllers\Api;

/**
 * GET /api/sales-orders          — Paginated sales orders
 * GET /api/sales-orders/{id}     — Single sales order with lines
 */
class SalesOrderApi extends BaseApiController
{
    public function index(): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }
        if (!$this->requirePermission('sales_orders', 'read')) {
            return $this->response;
        }

        $db = \Config\Database::connect();

        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = min(100, max(1, (int) ($this->request->getGet('per_page') ?? 20)));
        $status  = $this->request->getGet('status');
        $latest  = $this->request->getGet('latest');
        $q       = (string) ($this->request->getGet('q') ?? '');
        $offset  = ($page - 1) * $perPage;

        $where = [];
        $params = [];

        if ($status !== null && $status !== '') {
            $where[] = 'so.status = ?';
            $params[] = $status;
        }

        if ($q !== '') {
            $where[] = '(so.order_number LIKE ? OR c.name LIKE ?)';
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count total
        $countSql = "SELECT COUNT(*) AS total FROM sales_orders so
                     LEFT JOIN customers c ON so.customer_id = c.id
                     {$whereClause}";
        $total = (int) ($db->query($countSql, $params)->getRowArray()['total'] ?? 0);

        // If latest=1, return last 10 orders
        if ($latest) {
            $perPage = 10;
            $offset = 0;
        }

        $sql = "SELECT so.id, so.order_number, so.order_date, so.status,
                       so.subtotal, so.tax_total, so.total,
                       COALESCE(so.currency, so.currency_code, 'PKR') AS currency,
                       so.customer_id,
                       COALESCE(c.name, c.company_name, 'Unknown') AS customer_name
                FROM sales_orders so
                LEFT JOIN customers c ON so.customer_id = c.id
                {$whereClause}
                ORDER BY so.id DESC
                LIMIT ? OFFSET ?";

        $params[] = $perPage;
        $params[] = $offset;

        $items = $db->query($sql, $params)->getResultArray();

        // Cast numeric fields — CodeIgniter returns all DB values as strings
        $items = array_map(static function (array $row): array {
            $row['id']          = (int)   $row['id'];
            $row['customer_id'] = isset($row['customer_id']) ? (int) $row['customer_id'] : null;
            $row['subtotal']    = (float) $row['subtotal'];
            $row['tax_total']   = (float) $row['tax_total'];
            $row['total']       = (float) $row['total'];
            return $row;
        }, $items);

        return $this->success([
            'data'        => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ]);
    }

    public function show(int $id): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }
        if (!$this->requirePermission('sales_orders', 'read')) {
            return $this->response;
        }

        $db = \Config\Database::connect();

        $order = $db->query(
            "SELECT so.*, COALESCE(c.name, c.company_name, 'Unknown') AS customer_name
             FROM sales_orders so
             LEFT JOIN customers c ON so.customer_id = c.id
             WHERE so.id = ?",
            [$id]
        )->getRowArray();

        if (!$order) {
            return $this->error('Sales order not found.', 404);
        }

        // Get lines with product info
        $lines = $db->query(
            "SELECT sol.*, COALESCE(p.name, sol.description) AS product_name,
                     COALESCE(pv.art_number, p.code, p.sku) AS product_code
             FROM sales_order_lines sol
             LEFT JOIN products p ON sol.product_id = p.id
             LEFT JOIN product_variants pv ON sol.product_variant_id = pv.id
             WHERE sol.sales_order_id = ?
             ORDER BY sol.id",
            [$id]
        )->getResultArray();

        $order['lines'] = $lines;

        return $this->success($order);
    }
}
