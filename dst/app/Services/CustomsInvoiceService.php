<?php

namespace App\Services;

use App\Models\CustomerInvoiceLineModel;
use App\Models\CustomerInvoiceModel;
use App\Models\CustomsInvoiceApprovalModel;
use App\Models\CustomsInvoiceAuditLogModel;
use App\Models\CustomsInvoiceFileModel;
use App\Models\CustomsInvoiceItemModel;
use App\Models\CustomsInvoiceModel;
use App\Models\CustomsInvoiceVersionModel;
use App\Traits\PublicIdTrait;
use App\Libraries\CustomsInvoicePdfGenerator;

class CustomsInvoiceService
{
    private CustomsInvoiceModel $customsModel;
    private CustomsInvoiceVersionModel $versionModel;
    private CustomsInvoiceItemModel $itemModel;
    private CustomsInvoiceAuditLogModel $auditModel;
    private CustomsInvoiceApprovalModel $approvalModel;
    private CustomsInvoiceFileModel $fileModel;
    private CustomerInvoiceModel $invoiceModel;
    private CustomerInvoiceLineModel $invoiceLineModel;
    private CustomsInvoicePdfGenerator $pdfGenerator;

    public function __construct()
    {
        $this->customsModel = new CustomsInvoiceModel();
        $this->versionModel = new CustomsInvoiceVersionModel();
        $this->itemModel = new CustomsInvoiceItemModel();
        $this->auditModel = new CustomsInvoiceAuditLogModel();
        $this->approvalModel = new CustomsInvoiceApprovalModel();
        $this->fileModel = new CustomsInvoiceFileModel();
        $this->invoiceModel = new CustomerInvoiceModel();
        $this->invoiceLineModel = new CustomerInvoiceLineModel();
        $this->pdfGenerator = new CustomsInvoicePdfGenerator();
    }

    public function createFromOriginalInvoice(int $originalInvoiceId, string $mode, int $actorId, array $meta = []): array
    {
        $db = \Config\Database::connect();
        $mode = strtoupper(trim($mode));
        if (! in_array($mode, ['VALUE_ONLY', 'FULL_REWRITE'], true)) {
            throw new \InvalidArgumentException('Invalid mode. Allowed: VALUE_ONLY, FULL_REWRITE');
        }

        $invoice = $this->invoiceModel->find($originalInvoiceId);
        if (! $invoice) {
            throw new \RuntimeException('Original invoice not found');
        }

        $invoiceLines = $this->invoiceLineModel
            ->where('invoice_id', $originalInvoiceId)
            ->where('display_type', 'line')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();

        $sourceSnapshot = [
            'invoice' => $invoice,
            'lines' => $invoiceLines,
            'meta' => $meta,
        ];
        $sourceSnapshotHash = hash('sha256', json_encode($sourceSnapshot));

        $db->transStart();

        $customsUuid = PublicIdTrait::uuid4();
        $customsInvoiceNo = $this->generateCustomsInvoiceNo();

        $customsId = $this->customsModel->insert([
            'uuid' => $customsUuid,
            'original_invoice_id' => $originalInvoiceId,
            'customs_invoice_no' => $customsInvoiceNo,
            'mode' => $mode,
            'status' => 'DRAFT',
            'current_version_no' => 1,
            'currency_code' => strtoupper((string) ($invoice['currency_code'] ?? 'USD')),
            'declared_total' => (float) ($invoice['total_amount'] ?? 0),
            'shipment_id' => $meta['shipment_id'] ?? null,
            'tracking_no' => $meta['tracking_no'] ?? null,
            'source_snapshot_hash' => $sourceSnapshotHash,
            'lock_state' => 'UNLOCKED',
            'row_version' => 1,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ], true);

        if (! $customsId) {
            throw new \RuntimeException('Failed to create customs invoice');
        }

        $versionId = $this->versionModel->insert([
            'uuid' => PublicIdTrait::uuid4(),
            'customs_invoice_id' => $customsId,
            'version_no' => 1,
            'change_type' => 'CREATE',
            'change_reason' => 'Initial snapshot from original invoice',
            'snapshot_json' => json_encode($sourceSnapshot),
            'snapshot_hash' => $sourceSnapshotHash,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ], true);

        $lineNo = 1;
        foreach ($invoiceLines as $line) {
            $qty = (float) ($line['quantity'] ?? 0);
            $unitPrice = (float) ($line['unit_price'] ?? 0);
            $lineTotal = (float) ($line['line_total'] ?? ($qty * $unitPrice));
            $description = trim((string) ($line['description'] ?? $line['product_name'] ?? ''));
            if ($description === '') {
                $description = 'Customs Item ' . $lineNo;
            }

            $this->itemModel->insert([
                'uuid' => PublicIdTrait::uuid4(),
                'customs_invoice_id' => $customsId,
                'customs_invoice_version_id' => $versionId,
                'line_no' => $lineNo,
                'line_type' => 'ORIGINAL_MAPPED',
                'source_invoice_line_id' => (int) ($line['id'] ?? 0) ?: null,
                'source_product_id' => (int) ($line['product_id'] ?? 0) ?: null,
                'custom_description' => $description,
                'declared_qty' => $qty,
                'uom' => (string) ($line['unit'] ?? ''),
                'declared_unit_price' => $unitPrice,
                'declared_line_total' => $lineTotal,
                'currency_code' => strtoupper((string) ($invoice['currency_code'] ?? 'USD')),
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
            $lineNo++;
        }

        $this->customsModel->update($customsId, [
            'current_version_id' => $versionId,
            'updated_by' => $actorId,
        ]);

        $this->recordAudit($customsId, $versionId, 'CREATE', null, null, [
            'mode' => $mode,
            'original_invoice_id' => $originalInvoiceId,
        ], $actorId);

        $db->transComplete();
        if (! $db->transStatus()) {
            throw new \RuntimeException('Failed to create customs invoice transaction');
        }

        return $this->getByUuid($customsUuid);
    }

    public function getByUuid(string $uuid): ?array
    {
        $customs = $this->customsModel->where('uuid', $uuid)->first();
        if (! $customs) {
            return null;
        }

        $version = null;
        if (! empty($customs['current_version_id'])) {
            $version = $this->versionModel->find((int) $customs['current_version_id']);
        }

        $items = [];
        if (! empty($customs['current_version_id'])) {
            $items = $this->itemModel
                ->where('customs_invoice_version_id', (int) $customs['current_version_id'])
                ->orderBy('line_no', 'ASC')
                ->findAll();
        }

        return [
            'customs_invoice' => $customs,
            'current_version' => $version,
            'items' => $items,
        ];
    }

    public function list(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $builder = $this->customsModel
            ->select('customs_invoices.*, ci.invoice_number as original_invoice_number, c.name as customer_name')
            ->join('customer_invoices ci', 'ci.id = customs_invoices.original_invoice_id', 'left')
            ->join('customers c', 'c.id = ci.customer_id', 'left');

        if (! empty($filters['status'])) {
            $builder->where('customs_invoices.status', $filters['status']);
        }
        if (! empty($filters['mode'])) {
            $builder->where('customs_invoices.mode', strtoupper((string) $filters['mode']));
        }
        if (! empty($filters['invoice_number'])) {
            $builder->like('customs_invoices.customs_invoice_no', (string) $filters['invoice_number']);
        }
        if (! empty($filters['customer'])) {
            $builder->like('c.name', (string) $filters['customer']);
        }
        if (! empty($filters['tracking_no'])) {
            $builder->like('customs_invoices.tracking_no', (string) $filters['tracking_no']);
        }

        return $builder->orderBy('customs_invoices.id', 'DESC')->findAll($limit, $offset);
    }

    public function saveDraft(string $uuid, array $header, array $items, int $actorId): array
    {
        $db = \Config\Database::connect();
        $customs = $this->customsModel->where('uuid', $uuid)->first();
        if (! $customs) {
            throw new \RuntimeException('Customs invoice not found');
        }

        if (in_array($customs['status'], ['FINALIZED', 'ARCHIVED', 'SENT_TO_AGENT'], true)) {
            throw new \RuntimeException('Cannot edit finalized/archived customs invoice');
        }

        $db->transStart();

        $currentVersionNo = (int) ($customs['current_version_no'] ?? 1);
        $nextVersionNo = $currentVersionNo + 1;
        $newStatus = 'DRAFT';

        $normalizedItems = $this->normalizeAndValidateItems($customs['mode'], $items);
        $declaredTotal = array_sum(array_map(static fn(array $r): float => (float) $r['declared_line_total'], $normalizedItems));

        $headerUpdate = [
            'status' => $newStatus,
            'currency_code' => strtoupper((string) ($header['currency_code'] ?? $customs['currency_code'] ?? 'USD')),
            'declared_total' => $declaredTotal,
            'shipment_id' => $header['shipment_id'] ?? $customs['shipment_id'],
            'tracking_no' => $header['tracking_no'] ?? $customs['tracking_no'],
            'current_version_no' => $nextVersionNo,
            'row_version' => ((int) ($customs['row_version'] ?? 0)) + 1,
            'updated_by' => $actorId,
        ];

        $snapshot = [
            'header' => $headerUpdate,
            'items' => $normalizedItems,
            'previous_version' => (int) ($customs['current_version_id'] ?? 0),
        ];
        $snapshotHash = hash('sha256', json_encode($snapshot));

        $versionId = $this->versionModel->insert([
            'uuid' => PublicIdTrait::uuid4(),
            'customs_invoice_id' => (int) $customs['id'],
            'version_no' => $nextVersionNo,
            'parent_version_id' => (int) ($customs['current_version_id'] ?? 0) ?: null,
            'change_type' => ((string) $customs['status'] === 'APPROVED') ? 'POST_APPROVAL_REVISION' : 'EDIT',
            'change_reason' => (string) ($header['change_reason'] ?? 'Draft updated'),
            'snapshot_json' => json_encode($snapshot),
            'snapshot_hash' => $snapshotHash,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ], true);

        foreach ($normalizedItems as $idx => $item) {
            $this->itemModel->insert([
                'uuid' => PublicIdTrait::uuid4(),
                'customs_invoice_id' => (int) $customs['id'],
                'customs_invoice_version_id' => $versionId,
                'line_no' => $idx + 1,
                'line_type' => (string) ($item['line_type'] ?? 'MANUAL'),
                'source_invoice_line_id' => $item['source_invoice_line_id'] ?? null,
                'source_product_id' => $item['source_product_id'] ?? null,
                'custom_description' => (string) $item['custom_description'],
                'hs_code' => $item['hs_code'] ?? null,
                'declared_qty' => (float) $item['declared_qty'],
                'uom' => (string) ($item['uom'] ?? ''),
                'declared_unit_price' => (float) $item['declared_unit_price'],
                'declared_line_total' => (float) $item['declared_line_total'],
                'declared_weight' => $item['declared_weight'] ?? null,
                'weight_uom' => $item['weight_uom'] ?? null,
                'currency_code' => strtoupper((string) ($item['currency_code'] ?? $headerUpdate['currency_code'])),
                'group_key' => $item['group_key'] ?? null,
                'metadata_json' => !empty($item['metadata_json']) ? json_encode($item['metadata_json']) : null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
        }

        $headerUpdate['current_version_id'] = $versionId;
        $this->customsModel->update((int) $customs['id'], $headerUpdate);

        $this->recordAudit((int) $customs['id'], $versionId, 'DRAFT_UPDATED', null, null, [
            'declared_total' => $declaredTotal,
            'line_count' => count($normalizedItems),
        ], $actorId);

        $db->transComplete();
        if (! $db->transStatus()) {
            throw new \RuntimeException('Failed to save draft');
        }

        return $this->getByUuid($uuid) ?? [];
    }

    public function submitForApproval(string $uuid, int $actorId, array $payload = []): array
    {
        $plainToken = !empty($payload['approval_token']) ? (string) $payload['approval_token'] : bin2hex(random_bytes(16));
        $payload['approval_token'] = $plainToken;
        if (empty($payload['token_expires_at'])) {
            $payload['token_expires_at'] = date('Y-m-d H:i:s', strtotime('+7 days'));
        }

        $result = $this->transitionStatus($uuid, 'PENDING_APPROVAL', 'SUBMIT_APPROVAL', $actorId, $payload, function (array $customs) use ($actorId, $payload) {
            $approvalId = $this->approvalModel->insert([
                'uuid' => PublicIdTrait::uuid4(),
                'customs_invoice_id' => (int) $customs['id'],
                'customs_invoice_version_id' => (int) ($customs['current_version_id'] ?? 0),
                'approval_status' => 'PENDING',
                'approval_channel' => strtoupper((string) ($payload['approval_channel'] ?? 'PORTAL')),
                'requested_to_name' => $payload['requested_to_name'] ?? null,
                'requested_to_email' => $payload['requested_to_email'] ?? null,
                'request_message' => $payload['request_message'] ?? null,
                'token_hash' => hash('sha256', (string) $payload['approval_token']),
                'token_expires_at' => $payload['token_expires_at'] ?? null,
                'requested_by_user_id' => $actorId,
                'requested_at' => date('Y-m-d H:i:s'),
            ], true);

            return ['approval_id' => $approvalId];
        });

        $result['approval_token'] = $plainToken;
        return $result;
    }

    public function approve(string $uuid, int $actorId, array $payload = []): array
    {
        return $this->transitionStatus($uuid, 'APPROVED', 'APPROVED', $actorId, $payload, function (array $customs) use ($actorId, $payload) {
            $this->approvalModel
                ->where('customs_invoice_id', (int) $customs['id'])
                ->where('customs_invoice_version_id', (int) ($customs['current_version_id'] ?? 0))
                ->where('approval_status', 'PENDING')
                ->set([
                    'approval_status' => 'APPROVED',
                    'decision_comment' => (string) ($payload['decision_comment'] ?? ''),
                    'decided_by_user_id' => $actorId,
                    'decided_at' => date('Y-m-d H:i:s'),
                ])
                ->update();

            $this->versionModel->update((int) ($customs['current_version_id'] ?? 0), [
                'is_approved_snapshot' => 1,
                'sealed_at' => date('Y-m-d H:i:s'),
                'updated_by' => $actorId,
            ]);

            return [];
        }, ['lock_state' => 'LOCKED_APPROVED']);
    }

    public function reject(string $uuid, int $actorId, array $payload = []): array
    {
        return $this->transitionStatus($uuid, 'REJECTED', 'REJECTED', $actorId, $payload, function (array $customs) use ($actorId, $payload) {
            $this->approvalModel
                ->where('customs_invoice_id', (int) $customs['id'])
                ->where('customs_invoice_version_id', (int) ($customs['current_version_id'] ?? 0))
                ->where('approval_status', 'PENDING')
                ->set([
                    'approval_status' => 'REJECTED',
                    'decision_comment' => (string) ($payload['decision_comment'] ?? 'Rejected by reviewer'),
                    'decided_by_user_id' => $actorId,
                    'decided_at' => date('Y-m-d H:i:s'),
                ])
                ->update();

            return [];
        }, ['lock_state' => 'UNLOCKED']);
    }

    public function finalize(string $uuid, int $actorId, array $payload = []): array
    {
        return $this->transitionStatus($uuid, 'FINALIZED', 'FINALIZED', $actorId, $payload, function (array $customs) use ($actorId) {
            $this->versionModel->update((int) ($customs['current_version_id'] ?? 0), [
                'is_final_snapshot' => 1,
                'sealed_at' => date('Y-m-d H:i:s'),
                'updated_by' => $actorId,
            ]);

            return [];
        }, ['lock_state' => 'LOCKED_FINALIZED']);
    }

    public function sendToShippingAgent(string $uuid, int $actorId, array $payload = []): array
    {
        return $this->transitionStatus($uuid, 'SENT_TO_AGENT', 'SENT_TO_AGENT', $actorId, $payload);
    }

    public function archive(string $uuid, int $actorId, array $payload = []): array
    {
        return $this->transitionStatus($uuid, 'ARCHIVED', 'ARCHIVED', $actorId, $payload);
    }

    public function generatePdf(string $uuid, string $variant, int $actorId): array
    {
        $variant = strtolower(trim($variant));
        if (! in_array($variant, ['preview', 'final'], true)) {
            throw new \RuntimeException('Invalid PDF variant. Allowed: preview, final');
        }

        $full = $this->getByUuid($uuid);
        if (! $full || empty($full['customs_invoice'])) {
            throw new \RuntimeException('Customs invoice not found');
        }

        $header = $full['customs_invoice'];
        $items = $full['items'] ?? [];

        $original = $this->invoiceModel->find((int) ($header['original_invoice_id'] ?? 0));
        $payload = [
            'customs_invoice_no' => $header['customs_invoice_no'] ?? '',
            'status' => $header['status'] ?? 'DRAFT',
            'mode' => $header['mode'] ?? 'VALUE_ONLY',
            'tracking_no' => $header['tracking_no'] ?? '',
            'currency_code' => $header['currency_code'] ?? 'USD',
            'declared_total' => $header['declared_total'] ?? 0,
            'customer_name' => $original['customer_name'] ?? null,
            'customer_email' => $original['customer_email'] ?? null,
            'items' => $items,
        ];

        $file = $this->pdfGenerator->generate($payload, $variant);
        $fileType = $variant === 'final' ? 'PDF_FINAL' : 'PDF_PREVIEW';

        $fileId = $this->fileModel->insert([
            'uuid' => PublicIdTrait::uuid4(),
            'customs_invoice_id' => (int) $header['id'],
            'customs_invoice_version_id' => (int) ($header['current_version_id'] ?? 0) ?: null,
            'file_type' => $fileType,
            'storage_disk' => 'local',
            'storage_path' => $file['path'],
            'file_name' => $file['name'],
            'mime_type' => $file['mime_type'],
            'file_size' => (int) $file['size'],
            'sha256_hash' => $file['sha256'],
            'template_version' => 'v1',
            'render_engine_version' => 'dompdf',
            'is_current' => 1,
            'created_by' => $actorId,
            'created_at' => date('Y-m-d H:i:s'),
        ], true);

        if ($variant === 'final') {
            $this->versionModel->update((int) ($header['current_version_id'] ?? 0), [
                'pdf_file_id' => $fileId,
                'updated_by' => $actorId,
            ]);
        }

        $this->recordAudit(
            (int) $header['id'],
            (int) ($header['current_version_id'] ?? 0) ?: null,
            'PDF_GENERATED',
            null,
            ['variant' => $variant, 'file_id' => $fileId],
            ['file_type' => $fileType, 'name' => $file['name']],
            $actorId
        );

        return [
            'file_id' => $fileId,
            'file_name' => $file['name'],
            'file_type' => $fileType,
            'storage_path' => $file['path'],
            'sha256' => $file['sha256'],
        ];
    }

    public function listFiles(string $uuid): array
    {
        $customs = $this->customsModel->where('uuid', $uuid)->first();
        if (! $customs) {
            return [];
        }

        return $this->fileModel
            ->where('customs_invoice_id', (int) $customs['id'])
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    public function getFileById(string $uuid, int $fileId): ?array
    {
        $customs = $this->customsModel->where('uuid', $uuid)->first();
        if (! $customs) {
            return null;
        }

        return $this->fileModel
            ->where('customs_invoice_id', (int) $customs['id'])
            ->where('id', $fileId)
            ->first();
    }

    public function getPendingApprovalByToken(string $token): ?array
    {
        $tokenHash = hash('sha256', $token);
        $approval = $this->approvalModel
            ->where('token_hash', $tokenHash)
            ->where('approval_status', 'PENDING')
            ->first();

        if (! $approval) {
            return null;
        }

        $expiresAt = !empty($approval['token_expires_at']) ? strtotime((string) $approval['token_expires_at']) : null;
        if ($expiresAt !== null && $expiresAt < time()) {
            return null;
        }

        $doc = $this->getByUuid((string)($this->customsModel->find((int)$approval['customs_invoice_id'])['uuid'] ?? ''));
        if (! $doc) {
            return null;
        }

        return [
            'approval' => $approval,
            'doc' => $doc,
        ];
    }

    public function decideByToken(string $token, string $decision, string $comment = ''): array
    {
        $ctx = $this->getPendingApprovalByToken($token);
        if (! $ctx || empty($ctx['approval']) || empty($ctx['doc']['customs_invoice']['uuid'])) {
            throw new \RuntimeException('Invalid or expired approval token');
        }

        $approval = $ctx['approval'];
        $uuid = (string) $ctx['doc']['customs_invoice']['uuid'];
        $decision = strtoupper(trim($decision));

        if ($decision === 'APPROVE') {
            return $this->approve($uuid, (int)($approval['requested_by_user_id'] ?? 0), ['decision_comment' => $comment !== '' ? $comment : 'Approved by token']);
        }

        if ($decision === 'REJECT') {
            return $this->reject($uuid, (int)($approval['requested_by_user_id'] ?? 0), ['decision_comment' => $comment !== '' ? $comment : 'Rejected by token']);
        }

        throw new \RuntimeException('Unsupported decision');
    }

    private function transitionStatus(
        string $uuid,
        string $toStatus,
        string $eventType,
        int $actorId,
        array $payload = [],
        ?callable $sideEffect = null,
        array $extraUpdate = []
    ): array {
        $db = \Config\Database::connect();
        $customs = $this->customsModel->where('uuid', $uuid)->first();
        if (! $customs) {
            throw new \RuntimeException('Customs invoice not found');
        }

        $fromStatus = (string) ($customs['status'] ?? 'DRAFT');
        if (! $this->isValidStatusTransition($fromStatus, $toStatus)) {
            throw new \RuntimeException("Invalid status transition {$fromStatus} -> {$toStatus}");
        }

        $db->transStart();

        if ($sideEffect) {
            $sideEffect($customs);
        }

        $update = array_merge([
            'status' => $toStatus,
            'row_version' => ((int) ($customs['row_version'] ?? 0)) + 1,
            'updated_by' => $actorId,
        ], $extraUpdate);

        $this->customsModel->update((int) $customs['id'], $update);

        $this->recordAudit(
            (int) $customs['id'],
            (int) ($customs['current_version_id'] ?? 0) ?: null,
            $eventType,
            ['status' => $fromStatus],
            ['status' => $toStatus],
            $payload,
            $actorId
        );

        $db->transComplete();
        if (! $db->transStatus()) {
            throw new \RuntimeException('Failed to update status');
        }

        return $this->getByUuid($uuid) ?? [];
    }

    private function normalizeAndValidateItems(string $mode, array $items): array
    {
        $mode = strtoupper((string) $mode);
        if (empty($items)) {
            throw new \RuntimeException('At least one customs line item is required');
        }

        $normalized = [];
        foreach ($items as $idx => $item) {
            if (! is_array($item)) {
                continue;
            }

            $desc = trim((string) ($item['custom_description'] ?? ''));
            $qty = (float) ($item['declared_qty'] ?? 0);
            $unitPrice = (float) ($item['declared_unit_price'] ?? 0);
            $lineTotal = isset($item['declared_line_total'])
                ? (float) $item['declared_line_total']
                : (float) ($qty * $unitPrice);

            if ($desc === '') {
                throw new \RuntimeException('Line ' . ($idx + 1) . ': custom_description is required');
            }
            if ($qty < 0 || $unitPrice < 0 || $lineTotal < 0) {
                throw new \RuntimeException('Line ' . ($idx + 1) . ': negative values are not allowed');
            }

            if ($mode === 'VALUE_ONLY') {
                if (empty($item['source_invoice_line_id'])) {
                    throw new \RuntimeException('Line ' . ($idx + 1) . ': VALUE_ONLY mode requires source_invoice_line_id for all lines');
                }
            }

            $normalized[] = [
                'line_type' => $item['line_type'] ?? ($mode === 'VALUE_ONLY' ? 'ORIGINAL_MAPPED' : 'MANUAL'),
                'source_invoice_line_id' => !empty($item['source_invoice_line_id']) ? (int) $item['source_invoice_line_id'] : null,
                'source_product_id' => !empty($item['source_product_id']) ? (int) $item['source_product_id'] : null,
                'custom_description' => $desc,
                'hs_code' => $item['hs_code'] ?? null,
                'declared_qty' => $qty,
                'uom' => $item['uom'] ?? null,
                'declared_unit_price' => $unitPrice,
                'declared_line_total' => $lineTotal,
                'declared_weight' => $item['declared_weight'] ?? null,
                'weight_uom' => $item['weight_uom'] ?? null,
                'currency_code' => strtoupper((string) ($item['currency_code'] ?? 'USD')),
                'group_key' => $item['group_key'] ?? null,
                'metadata_json' => $item['metadata_json'] ?? null,
            ];
        }

        if (empty($normalized)) {
            throw new \RuntimeException('No valid line items provided');
        }

        return $normalized;
    }

    private function isValidStatusTransition(string $from, string $to): bool
    {
        $allowed = [
            'DRAFT' => ['PENDING_APPROVAL', 'ARCHIVED'],
            'PENDING_APPROVAL' => ['APPROVED', 'REJECTED', 'ARCHIVED'],
            'APPROVED' => ['FINALIZED', 'DRAFT', 'ARCHIVED'],
            'REJECTED' => ['DRAFT', 'ARCHIVED'],
            'FINALIZED' => ['SENT_TO_AGENT', 'ARCHIVED'],
            'SENT_TO_AGENT' => ['ARCHIVED'],
            'ARCHIVED' => [],
        ];

        return in_array($to, $allowed[$from] ?? [], true);
    }

    private function generateCustomsInvoiceNo(): string
    {
        $prefix = 'CINV-' . date('Ym') . '-';
        $row = $this->customsModel
            ->select('customs_invoice_no')
            ->like('customs_invoice_no', $prefix, 'after')
            ->orderBy('id', 'DESC')
            ->first();

        $next = 1;
        if ($row && !empty($row['customs_invoice_no'])) {
            $lastNum = (int) substr((string) $row['customs_invoice_no'], -4);
            $next = $lastNum + 1;
        }

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function recordAudit(
        int $customsInvoiceId,
        ?int $versionId,
        string $eventType,
        $before,
        $after,
        $diff,
        int $actorId
    ): void {
        $user = session()->get('role') ?? null;
        $this->auditModel->insert([
            'uuid' => PublicIdTrait::uuid4(),
            'customs_invoice_id' => $customsInvoiceId,
            'customs_invoice_version_id' => $versionId,
            'event_type' => $eventType,
            'before_value' => $before !== null ? json_encode($before) : null,
            'after_value' => $after !== null ? json_encode($after) : null,
            'diff_json' => $diff !== null ? json_encode($diff) : null,
            'actor_user_id' => $actorId,
            'actor_role' => $user ? (string) $user : null,
            'actor_ip' => service('request')->getIPAddress(),
            'actor_user_agent' => service('request')->getUserAgent() ? (string) service('request')->getUserAgent() : null,
            'correlation_id' => service('request')->getHeaderLine('X-Request-ID') ?: PublicIdTrait::uuid4(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
