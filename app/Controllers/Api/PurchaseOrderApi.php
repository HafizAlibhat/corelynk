<?php

namespace App\Controllers\Api;

/**
 * GET /api/purchase-orders          — Paginated purchase orders
 * GET /api/purchase-orders/{id}     — Single PO with lines
 */
class PurchaseOrderApi extends BaseApiController
{
    public function index(): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }
        if (!$this->requirePermission('purchase_orders', 'read')) {
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
            $where[] = 'po.status = ?';
            $params[] = $status;
        }

        if ($q !== '') {
            $where[] = '(po.po_number LIKE ? OR v.name LIKE ?)';
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(*) AS total FROM purchase_orders po
                     LEFT JOIN vendors v ON po.vendor_id = v.id
                     {$whereClause}";
        $total = (int) ($db->query($countSql, $params)->getRowArray()['total'] ?? 0);

        if ($latest) {
            $perPage = 10;
            $offset = 0;
        }

        $sql = "SELECT po.id, po.po_number AS order_number, po.order_date AS date, po.status,
                       po.subtotal, po.tax_total, po.total AS total_amount,
                       COALESCE(po.currency, po.currency_code, 'PKR') AS currency,
                       po.vendor_id,
                       COALESCE(v.name, 'Unknown') AS supplier_name
                FROM purchase_orders po
                LEFT JOIN vendors v ON po.vendor_id = v.id
                {$whereClause}
                ORDER BY po.id DESC
                LIMIT ? OFFSET ?";

        $params[] = $perPage;
        $params[] = $offset;

        $items = $db->query($sql, $params)->getResultArray();

        // Cast numeric fields — CodeIgniter returns all DB values as strings
        $items = array_map(static function (array $row): array {
            $row['id']           = (int)   $row['id'];
            $row['vendor_id']    = isset($row['vendor_id']) ? (int) $row['vendor_id'] : null;
            $row['subtotal']     = (float) $row['subtotal'];
            $row['tax_total']    = (float) $row['tax_total'];
            $row['total_amount'] = (float) $row['total_amount'];
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
        if (!$this->requirePermission('purchase_orders', 'read')) {
            return $this->response;
        }

        $db = \Config\Database::connect();

        $order = $db->query(
            "SELECT po.*, po.total AS total_amount, COALESCE(v.name, 'Unknown') AS supplier_name
             FROM purchase_orders po
             LEFT JOIN vendors v ON po.vendor_id = v.id
             WHERE po.id = ?",
            [$id]
        )->getRowArray();

        if (!$order) {
            return $this->error('Purchase order not found.', 404);
        }

        $lines = $db->query(
            "SELECT pol.*, COALESCE(p.name, pol.description) AS product_name,
                     COALESCE(pv.art_number, p.code, p.sku) AS product_code
             FROM purchase_order_lines pol
             LEFT JOIN products p ON pol.product_id = p.id
             LEFT JOIN product_variants pv ON pol.variant_id = pv.id
             WHERE pol.po_id = ?
             ORDER BY pol.id",
            [$id]
        )->getResultArray();

        // Compute line_total server-side when the stored value is 0 (legacy rows)
        foreach ($lines as &$line) {
            $storedTotal = (float) ($line['line_total'] ?? 0);
            if ($storedTotal == 0) {
                $qty       = (float) ($line['qty'] ?? $line['quantity'] ?? 0);
                $unitPrice = (float) ($line['unit_price'] ?? 0);
                $line['line_total'] = round($qty * $unitPrice, 2);
            }
        }
        unset($line);

        $order['lines'] = $lines;

        return $this->success($order);
    }
}
