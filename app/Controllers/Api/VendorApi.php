<?php

namespace App\Controllers\Api;

use App\Models\VendorModel;

/**
 * GET /api/vendors        — Paginated vendor list
 * GET /api/vendors/{id}   — Single vendor detail
 */
class VendorApi extends BaseApiController
{
    /**
     * GET /api/vendors
     *
     * Query params:
     *   q        — Search against name / vendor_code / email
     *   page     — Page number (default 1)
     *   per_page — Items per page (default 20, max 100)
     *   active   — 1 | 0 (omit for all)
     */
    public function index(): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }
        if (!$this->requirePermission('vendors', 'read')) {
            return $this->response;
        }

        $model = new VendorModel();

        $q       = (string) ($this->request->getGet('q')       ?? '');
        $active  = $this->request->getGet('active');
        $page    = max(1, (int) ($this->request->getGet('page')     ?? 1));
        $perPage = min(1000, max(1, (int) ($this->request->getGet('per_page') ?? 20)));

        if ($q !== '') {
            $model->groupStart()
                  ->like('name', $q)
                  ->orLike('vendor_code', $q)
                  ->orLike('email', $q)
                  ->groupEnd();
        }

        if ($active !== null && $active !== '') {
            $model->where('is_active', (int) $active);
        }

        $total  = $model->countAllResults(false);
        $offset = ($page - 1) * $perPage;
        $items  = $model->select('id, vendor_code, name, contact_person, phone, email, is_active, created_at, updated_at')
                        ->orderBy('name', 'ASC')
                        ->findAll($perPage, $offset);

        return $this->success([
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ]);
    }

    /**
     * GET /api/vendors/{id}
     */
    public function show(int $id): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }
        if (!$this->requirePermission('vendors', 'read')) {
            return $this->response;
        }

        $model  = new VendorModel();
        $vendor = $model->find($id);

        if ($vendor === null) {
            return $this->error('Vendor not found.', 404);
        }

        return $this->success($vendor);
    }
}
