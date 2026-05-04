<?php
namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\CustomerModel;
use CodeIgniter\HTTP\ResponseInterface;

class Customers extends BaseController
{
    protected $model;

    public function __construct()
    {
        $this->model = new CustomerModel();
    }

    public function index()
    {
        // List customers (simple pagination)
        $page = (int) ($this->request->getGet('page') ?? 1);
        $perPage = (int) ($this->request->getGet('per_page') ?? 25);
        $list = $this->model->paginate($perPage, 'default', $page);
        return $this->response->setJSON(['data' => $list]);
    }

    public function show($id)
    {
        $cust = $this->model->find($id);
        if (!$cust) return $this->response->setStatusCode(ResponseInterface::HTTP_NOT_FOUND)->setJSON(['error' => 'Not found']);
        // TODO: check permissions for sensitive fields
        return $this->response->setJSON(['data' => $cust]);
    }

    public function create()
    {
        $payload = $this->request->getJSON(true);
        if (empty($payload['name'])) {
            return $this->response->setStatusCode(ResponseInterface::HTTP_BAD_REQUEST)->setJSON(['error' => 'Name required']);
        }
        $created = $this->model->createCustomer($payload, $this->request->getHeaderLine('Idempotency-Key'));
        return $this->response->setStatusCode(ResponseInterface::HTTP_CREATED)->setJSON(['data' => $created]);
    }

    public function update($id)
    {
        $payload = $this->request->getJSON(true);
        $baseVersionHeader = $this->request->getHeaderLine('If-Match');
        $baseVersion = null;
        if ($baseVersionHeader && preg_match('/version:(\d+)/', $baseVersionHeader, $m)) $baseVersion = (int) $m[1];
        $updated = $this->model->updateCustomer((int)$id, $payload, $baseVersion);
        return $this->response->setJSON(['data' => $updated]);
    }

    public function delete($id)
    {
        // Soft delete: set status = inactive
        $this->model->update((int)$id, ['status' => 'inactive']);
        // create audit record via model or helper (TODO)
        return $this->response->setJSON(['data' => ['id' => (int)$id, 'status' => 'inactive']]);
    }
}
