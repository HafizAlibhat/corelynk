<?php

namespace App\Controllers\Api;

/**
 * GET /api/customers          — Paginated customer list
 * GET /api/customers/{id}     — Single customer with balance info
 */
class CustomerApi extends BaseApiController
{
    public function index(): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }
        if (!$this->requirePermission('customers', 'read')) {
            return $this->response;
        }

        $db = \Config\Database::connect();

        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = min(1000, max(1, (int) ($this->request->getGet('per_page') ?? 20)));
        $q       = (string) ($this->request->getGet('q') ?? '');
        $offset  = ($page - 1) * $perPage;

        $where = [];
        $params = [];

        if ($q !== '') {
            $where[] = '(c.name LIKE ? OR c.company_name LIKE ? OR c.customer_code LIKE ? OR c.email LIKE ?)';
            $params = array_merge($params, ["%{$q}%", "%{$q}%", "%{$q}%", "%{$q}%"]);
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(*) AS total FROM customers c {$whereClause}";
        $total = (int) ($db->query($countSql, $params)->getRowArray()['total'] ?? 0);

        $sql = "SELECT c.id, c.customer_code, c.name, c.company_name, c.email, c.phone, c.mobile,
                       c.status, c.created_at
                FROM customers c
                {$whereClause}
                ORDER BY c.name ASC
                LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;

        $items = $db->query($sql, $params)->getResultArray();

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
        if (!$this->requirePermission('customers', 'read')) {
            return $this->response;
        }

        $db = \Config\Database::connect();

        $customer = $db->query(
            "SELECT * FROM customers WHERE id = ?", [$id]
        )->getRowArray();

        if (!$customer) {
            return $this->error('Customer not found.', 404);
        }

        // Outstanding invoices
        $outstanding = $db->query(
            "SELECT COALESCE(SUM(total_amount), 0) AS total
             FROM customer_invoices
             WHERE customer_id = ? AND status NOT IN ('paid','cancelled') AND deleted_at IS NULL",
            [$id]
        )->getRowArray();

        $customer['outstanding_amount'] = round((float) ($outstanding['total'] ?? 0), 2);

        // Recent invoices
        $customer['recent_invoices'] = $db->query(
            "SELECT id, invoice_number, total_amount, currency_code, status, issue_date, due_date
             FROM customer_invoices
             WHERE customer_id = ? AND deleted_at IS NULL
             ORDER BY id DESC LIMIT 10",
            [$id]
        )->getResultArray();

        return $this->success($customer);
    }
}
