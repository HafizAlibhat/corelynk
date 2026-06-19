<?php

namespace App\Controllers;

use App\Models\CustomsInvoiceAuditLogModel;
use App\Models\CustomsInvoiceVersionModel;
use App\Services\CustomsInvoiceService;

class CustomsInvoices extends BaseController
{
    private CustomsInvoiceService $service;
    private CustomsInvoiceVersionModel $versionModel;
    private CustomsInvoiceAuditLogModel $auditModel;

    public function __construct()
    {
        $this->service = new CustomsInvoiceService();
        $this->versionModel = new CustomsInvoiceVersionModel();
        $this->auditModel = new CustomsInvoiceAuditLogModel();
    }

    public function index()
    {
        $this->requirePermission('customs_invoices.view');

        $filters = [
            'status' => $this->request->getGet('status'),
            'mode' => $this->request->getGet('mode'),
            'invoice_number' => $this->request->getGet('invoice_number'),
            'customer' => $this->request->getGet('customer'),
            'tracking_no' => $this->request->getGet('tracking_no'),
        ];

        $limit = max(1, min(200, (int) ($this->request->getGet('limit') ?? 50)));
        $offset = max(0, (int) ($this->request->getGet('offset') ?? 0));

        $rows = $this->service->list($filters, $limit, $offset);

        if ($this->wantsJson()) {
            return $this->jsonResponse(['success' => true, 'data' => $rows]);
        }

        return view('customs_invoices/index', $this->setPageData([
            'page_title' => 'Customs Invoice Customization',
            'rows' => $rows,
            'filters' => $filters,
            'can_create' => $this->hasPermission('customs_invoices.write'),
            'can_edit' => $this->hasPermission('customs_invoices.edit'),
            'can_approve' => $this->hasPermission('customs_invoices.approve'),
            'can_finalize' => $this->hasPermission('customs_invoices.finalize'),
        ]));
    }

    public function workspace($uuid = null)
    {
        $this->requirePermission('customs_invoices.view');

        $data = $this->service->getByUuid((string) $uuid);
        if (! $data) {
            return redirect()->to('/customs-invoices')->with('error', 'Customs invoice not found');
        }

        return view('customs_invoices/workspace', $this->setPageData([
            'page_title' => 'Customs Invoice Workspace',
            'doc' => $data,
            'doc_uuid' => (string) $uuid,
            'can_edit' => $this->hasPermission('customs_invoices.edit'),
            'can_approve' => $this->hasPermission('customs_invoices.approve'),
            'can_finalize' => $this->hasPermission('customs_invoices.finalize'),
        ]));
    }

    public function createFromInvoice($originalInvoiceId = null)
    {
        $this->requirePermission('customs_invoices.write');

        try {
            $isJson = str_contains((string) $this->request->getHeaderLine('Content-Type'), 'application/json');
            $payload = $isJson ? ($this->request->getJSON(true) ?? []) : $this->request->getPost();
            $mode = strtoupper((string) ($payload['mode'] ?? 'VALUE_ONLY'));
            $meta = [
                'shipment_id' => $payload['shipment_id'] ?? null,
                'tracking_no' => $payload['tracking_no'] ?? null,
            ];

            $result = $this->service->createFromOriginalInvoice((int) $originalInvoiceId, $mode, (int) session('user_id'), $meta);

            if (! $this->wantsJson()) {
                $target = $result['customs_invoice']['uuid'] ?? null;
                if ($target) {
                    return redirect()->to('/customs-invoices/workspace/' . urlencode((string) $target))
                        ->with('success', 'Customs invoice created successfully.');
                }
            }

            return $this->jsonResponse(['success' => true, 'data' => $result], 201);
        } catch (\Throwable $e) {
            if (! $this->wantsJson()) {
                return redirect()->back()->withInput()->with('error', $e->getMessage());
            }
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function show($uuid = null)
    {
        $this->requirePermission('customs_invoices.view');
        $data = $this->service->getByUuid((string) $uuid);
        if (! $data) {
            if ($this->wantsJson()) {
                return $this->jsonResponse(['success' => false, 'message' => 'Customs invoice not found'], 404);
            }
            return redirect()->to('/customs-invoices')->with('error', 'Customs invoice not found');
        }

        if (! $this->wantsJson()) {
            return redirect()->to('/customs-invoices/workspace/' . urlencode((string) $uuid));
        }
        return $this->jsonResponse(['success' => true, 'data' => $data]);
    }

    public function saveDraft($uuid = null)
    {
        $this->requirePermission('customs_invoices.edit');

        try {
            $payload = $this->request->getJSON(true) ?: [];
            $header = is_array($payload['header'] ?? null) ? $payload['header'] : [];
            $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];

            $data = $this->service->saveDraft((string) $uuid, $header, $items, (int) session('user_id'));
            return $this->jsonResponse(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function submitApproval($uuid = null)
    {
        $this->requirePermission('customs_invoices.edit');

        try {
            $payload = $this->request->getJSON(true) ?: [];
            $data = $this->service->submitForApproval((string) $uuid, (int) session('user_id'), $payload);
            return $this->jsonResponse(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function approve($uuid = null)
    {
        $this->requirePermission('customs_invoices.approve');

        try {
            $payload = $this->request->getJSON(true) ?: [];
            $data = $this->service->approve((string) $uuid, (int) session('user_id'), $payload);
            return $this->jsonResponse(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function reject($uuid = null)
    {
        $this->requirePermission('customs_invoices.approve');

        try {
            $payload = $this->request->getJSON(true) ?: [];
            $data = $this->service->reject((string) $uuid, (int) session('user_id'), $payload);
            return $this->jsonResponse(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function finalize($uuid = null)
    {
        $this->requirePermission('customs_invoices.finalize');

        try {
            $payload = $this->request->getJSON(true) ?: [];
            $data = $this->service->finalize((string) $uuid, (int) session('user_id'), $payload);
            return $this->jsonResponse(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function sendToAgent($uuid = null)
    {
        $this->requirePermission('customs_invoices.finalize');

        try {
            $payload = $this->request->getJSON(true) ?: [];
            $data = $this->service->sendToShippingAgent((string) $uuid, (int) session('user_id'), $payload);
            return $this->jsonResponse(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function archive($uuid = null)
    {
        $this->requirePermission('customs_invoices.finalize');

        try {
            $payload = $this->request->getJSON(true) ?: [];
            $data = $this->service->archive((string) $uuid, (int) session('user_id'), $payload);
            return $this->jsonResponse(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function generatePreviewPdf($uuid = null)
    {
        $this->requirePermission('customs_invoices.view');
        try {
            $data = $this->service->generatePdf((string) $uuid, 'preview', (int) session('user_id'));
            return $this->jsonResponse(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function generateFinalPdf($uuid = null)
    {
        $this->requirePermission('customs_invoices.finalize');
        try {
            $data = $this->service->generatePdf((string) $uuid, 'final', (int) session('user_id'));
            return $this->jsonResponse(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function files($uuid = null)
    {
        $this->requirePermission('customs_invoices.view');
        $rows = $this->service->listFiles((string) $uuid);
        return $this->jsonResponse(['success' => true, 'data' => $rows]);
    }

    public function downloadFile($uuid = null, $fileId = null)
    {
        $this->requirePermission('customs_invoices.view');
        $row = $this->service->getFileById((string) $uuid, (int) $fileId);
        if (! $row) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('File not found');
        }

        $path = (string) ($row['storage_path'] ?? '');
        if ($path === '' || ! is_file($path)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Stored file missing');
        }

        return $this->response->download($path, null)->setFileName((string) ($row['file_name'] ?? basename($path)));
    }

    public function versions($uuid = null)
    {
        $this->requirePermission('customs_invoices.view');

        $invoice = $this->service->getByUuid((string) $uuid);
        if (! $invoice || empty($invoice['customs_invoice']['id'])) {
            return $this->jsonResponse(['success' => false, 'message' => 'Customs invoice not found'], 404);
        }

        $versions = $this->versionModel
            ->where('customs_invoice_id', (int) $invoice['customs_invoice']['id'])
            ->orderBy('version_no', 'DESC')
            ->findAll();

        return $this->jsonResponse(['success' => true, 'data' => $versions]);
    }

    public function audit($uuid = null)
    {
        $this->requirePermission('customs_invoices.audit');

        $invoice = $this->service->getByUuid((string) $uuid);
        if (! $invoice || empty($invoice['customs_invoice']['id'])) {
            return $this->jsonResponse(['success' => false, 'message' => 'Customs invoice not found'], 404);
        }

        $logs = $this->auditModel
            ->where('customs_invoice_id', (int) $invoice['customs_invoice']['id'])
            ->orderBy('id', 'DESC')
            ->findAll();

        return $this->jsonResponse(['success' => true, 'data' => $logs]);
    }

    public function approvalPortal($token = null)
    {
        $ctx = $this->service->getPendingApprovalByToken((string) $token);
        if (! $ctx) {
            return view('errors/html/error_404', $this->setPageData([
                'message' => 'Approval link is invalid or expired.',
            ]));
        }

        return view('customs_invoices/approval_portal', $this->setPageData([
            'page_title' => 'Customs Invoice Approval',
            'token' => (string) $token,
            'ctx' => $ctx,
        ]));
    }

    public function approvalDecision($token = null)
    {
        try {
            $payload = $this->request->getJSON(true);
            if (! is_array($payload) || empty($payload)) {
                $payload = $this->request->getPost();
            }

            $decision = strtoupper((string) ($payload['decision'] ?? ''));
            $comment = (string) ($payload['comment'] ?? '');
            $result = $this->service->decideByToken((string) $token, $decision, $comment);

            if ($this->wantsJson()) {
                return $this->jsonResponse(['success' => true, 'data' => $result]);
            }

            return redirect()->to('/customs-approval/' . urlencode((string) $token))
                ->with('success', 'Decision submitted successfully.');
        } catch (\Throwable $e) {
            if ($this->wantsJson()) {
                return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->to('/customs-approval/' . urlencode((string) $token))
                ->with('error', $e->getMessage());
        }
    }

    private function wantsJson(): bool
    {
        return $this->request->isAJAX()
            || stripos((string) $this->request->getHeaderLine('Accept'), 'application/json') !== false;
    }
}
