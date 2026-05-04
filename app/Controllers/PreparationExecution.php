<?php

namespace App\Controllers;

use App\Models\PreparationProfileModel;
use App\Models\PreparationStepModel;
use App\Models\ProcessingRecordModel;
use App\Models\SalesOrderLineModel;
use App\Models\VendorSendNoteItemModel;
use App\Models\VendorSendNoteModel;
use App\Models\WarehouseLocationModel;
use App\Services\AutoPurchaseSuggestionService;
use App\Services\InventoryService;

class PreparationExecution extends BaseController
{
    private PreparationProfileModel $profileModel;
    private PreparationStepModel $stepModel;
    private ProcessingRecordModel $processingRecordModel;
    private VendorSendNoteModel $sendNoteModel;
    private VendorSendNoteItemModel $sendNoteItemModel;
    private WarehouseLocationModel $locationModel;
    private SalesOrderLineModel $salesOrderLineModel;

    public function __construct()
    {
        $this->profileModel = new PreparationProfileModel();
        $this->stepModel = new PreparationStepModel();
        $this->processingRecordModel = new ProcessingRecordModel();
        $this->sendNoteModel = new VendorSendNoteModel();
        $this->sendNoteItemModel = new VendorSendNoteItemModel();
        $this->locationModel = new WarehouseLocationModel();
        $this->salesOrderLineModel = new SalesOrderLineModel();
    }

    public function sendToVendor()
    {
        $this->requireAuth();
        $this->requirePermission('sales_orders.edit');

        $salesOrderId = (int) ($this->request->getPost('sales_order_id') ?? 0);
        $salesOrderLineId = (int) ($this->request->getPost('sales_order_line_id') ?? 0);
        $productId = (int) ($this->request->getPost('product_id') ?? 0);
        $stepId = (int) ($this->request->getPost('step_id') ?? 0);
        $vendorId = (int) ($this->request->getPost('vendor_id') ?? 0);
        $qty = (float) ($this->request->getPost('qty') ?? 0);
        $fromLocationId = (int) ($this->request->getPost('from_location_id') ?? 0);
        $toLocationId = (int) ($this->request->getPost('to_location_id') ?? 0);

        $line = $salesOrderLineId > 0 ? $this->salesOrderLineModel->find($salesOrderLineId) : null;
        $lineQty = (float) ($line['quantity'] ?? 0);
        if ($line && $lineQty > 0 && $qty > $lineQty) {
            return $this->redirectBackToOrder($salesOrderId, 'Execution quantity cannot exceed the sales order line quantity.', 'error');
        }

        $validationError = $this->validateBaseInput($productId, $stepId, $qty, $fromLocationId);
        if ($validationError !== null) {
            return $this->redirectBackToOrder($salesOrderId, $validationError, 'error');
        }
        if ($vendorId <= 0 || $toLocationId <= 0) {
            return $this->redirectBackToOrder($salesOrderId, 'Vendor and destination location are required.', 'error');
        }

        $step = $this->stepModel->find($stepId);
        if (! $step || (int) ($step['profile_id'] ?? 0) <= 0) {
            return $this->redirectBackToOrder($salesOrderId, 'Invalid preparation step selected.', 'error');
        }

        $profile = $this->profileModel->find((int) $step['profile_id']);
        if (! $profile || (int) ($profile['product_id'] ?? 0) !== $productId || (int) ($profile['is_active'] ?? 0) !== 1) {
            return $this->redirectBackToOrder($salesOrderId, 'Selected step does not match an active preparation profile for this product.', 'error');
        }

        if (! $this->stepAllowsExecutionType($stepId, 'vendor', $vendorId)) {
            return $this->redirectBackToOrder($salesOrderId, 'Selected step is not configured for vendor execution with this vendor.', 'error');
        }

        $dependencyError = $this->validateStepDependency($productId, $step);
        if ($dependencyError !== null) {
            return $this->redirectBackToOrder($salesOrderId, $dependencyError, 'error');
        }

        if ($this->processingRecordModel->hasOpenRecordForStep($productId, $stepId)) {
            return $this->redirectBackToOrder($salesOrderId, 'This step is already in progress for this product.', 'error');
        }

        $sourceLocation = $this->locationModel->find($fromLocationId);
        $destLocation = $this->locationModel->find($toLocationId);
        if (! $sourceLocation || ! $destLocation) {
            return $this->redirectBackToOrder($salesOrderId, 'Invalid source or destination location selected.', 'error');
        }

        $fromWarehouseId = (int) ($sourceLocation['warehouse_id'] ?? 0);
        $toWarehouseId = (int) ($destLocation['warehouse_id'] ?? 0);
        if ($fromWarehouseId <= 0 || $toWarehouseId <= 0) {
            return $this->redirectBackToOrder($salesOrderId, 'Selected locations are missing warehouse mapping.', 'error');
        }

        $db = \Config\Database::connect();
        $userId = (int) (session()->get('user_id') ?? session()->get('id') ?? 0);
        $referenceSuffix = strtoupper(substr(dechex(mt_rand(0, 65535)), 0, 4));
        $referenceNo = 'VSN-' . date('YmdHis') . '-' . str_pad($referenceSuffix, 4, '0', STR_PAD_LEFT);

        try {
            $db->transStart();

            $sendNoteId = $this->sendNoteModel->createSendNote([
                'reference_no' => $referenceNo,
                'vendor_id' => $vendorId,
                'step_id' => $stepId,
                'product_id' => $productId,
                'qty' => number_format($qty, 4, '.', ''),
                'from_location_id' => $fromLocationId,
                'to_location_id' => $toLocationId,
                'status' => 'draft',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $this->sendNoteItemModel->addItem([
                'send_note_id' => $sendNoteId,
                'product_id' => $productId,
                'qty' => number_format($qty, 4, '.', ''),
            ]);

            $inventoryService = new InventoryService();
            $inventoryService->internalTransfer(
                $productId,
                $fromWarehouseId,
                $fromLocationId,
                $toWarehouseId,
                $toLocationId,
                $qty,
                'Vendor send note ' . $referenceNo,
                $userId
            );

            $previousRecord = $this->findPreviousCompletedRecord($productId, $step);
            $this->processingRecordModel->insert([
                'product_id' => $productId,
                'step_id' => $stepId,
                'vendor_id' => $vendorId,
                'qty' => number_format($qty, 4, '.', ''),
                'status' => 'in_progress',
                'location_id' => $toLocationId,
                'parent_id' => $previousRecord['id'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $this->sendNoteModel->update($sendNoteId, ['status' => 'sent']);

            $db->transComplete();
            if (! $db->transStatus()) {
                return $this->redirectBackToOrder($salesOrderId, 'Failed to create vendor send action.', 'error');
            }
        } catch (\Throwable $e) {
            if ($db->transStatus()) {
                $db->transRollback();
            }
            log_message('error', 'sendToVendor failed: ' . $e->getMessage());
            return $this->redirectBackToOrder($salesOrderId, 'Failed to send to vendor: ' . $e->getMessage(), 'error');
        }

        $rfqMessage = '';
        if ($salesOrderId > 0) {
            try {
                $autoPurchase = new AutoPurchaseSuggestionService();
                $rfqResult = $autoPurchase->createDraftRFQsFromSalesOrder($salesOrderId, $userId > 0 ? $userId : 1);
                if (! empty($rfqResult['success']) && ! empty($rfqResult['created_pos'])) {
                    $rfqMessage = ' RFQ drafts were created for remaining shortages.';
                }
            } catch (\Throwable $e) {
                log_message('warning', 'sendToVendor RFQ auto-create skipped: ' . $e->getMessage());
            }
        }

        return $this->redirectBackToOrder($salesOrderId, 'Sent to vendor successfully.' . $rfqMessage, 'success');
    }

    public function startInHouse()
    {
        $this->requireAuth();
        $this->requirePermission('sales_orders.edit');

        $salesOrderId = (int) ($this->request->getPost('sales_order_id') ?? 0);
        $salesOrderLineId = (int) ($this->request->getPost('sales_order_line_id') ?? 0);
        $productId = (int) ($this->request->getPost('product_id') ?? 0);
        $stepId = (int) ($this->request->getPost('step_id') ?? 0);
        $qty = (float) ($this->request->getPost('qty') ?? 0);
        $locationId = (int) ($this->request->getPost('location_id') ?? 0);

        $line = $salesOrderLineId > 0 ? $this->salesOrderLineModel->find($salesOrderLineId) : null;
        $lineQty = (float) ($line['quantity'] ?? 0);
        if ($line && $lineQty > 0 && $qty > $lineQty) {
            return $this->redirectBackToOrder($salesOrderId, 'Execution quantity cannot exceed the sales order line quantity.', 'error');
        }

        $validationError = $this->validateBaseInput($productId, $stepId, $qty, $locationId);
        if ($validationError !== null) {
            return $this->redirectBackToOrder($salesOrderId, $validationError, 'error');
        }

        $step = $this->stepModel->find($stepId);
        if (! $step || (int) ($step['profile_id'] ?? 0) <= 0) {
            return $this->redirectBackToOrder($salesOrderId, 'Invalid preparation step selected.', 'error');
        }

        $profile = $this->profileModel->find((int) $step['profile_id']);
        if (! $profile || (int) ($profile['product_id'] ?? 0) !== $productId || (int) ($profile['is_active'] ?? 0) !== 1) {
            return $this->redirectBackToOrder($salesOrderId, 'Selected step does not match an active preparation profile for this product.', 'error');
        }

        if (! $this->stepAllowsExecutionType($stepId, 'inhouse')) {
            return $this->redirectBackToOrder($salesOrderId, 'Selected step is not configured for in-house execution.', 'error');
        }

        $dependencyError = $this->validateStepDependency($productId, $step);
        if ($dependencyError !== null) {
            return $this->redirectBackToOrder($salesOrderId, $dependencyError, 'error');
        }

        if ($this->processingRecordModel->hasOpenRecordForStep($productId, $stepId)) {
            return $this->redirectBackToOrder($salesOrderId, 'This step is already in progress for this product.', 'error');
        }

        $location = $this->locationModel->find($locationId);
        if (! $location) {
            return $this->redirectBackToOrder($salesOrderId, 'Invalid location selected.', 'error');
        }

        try {
            $previousRecord = $this->findPreviousCompletedRecord($productId, $step);
            $this->processingRecordModel->insert([
                'product_id' => $productId,
                'step_id' => $stepId,
                'vendor_id' => null,
                'qty' => number_format($qty, 4, '.', ''),
                'status' => 'in_progress',
                'location_id' => $locationId,
                'parent_id' => $previousRecord['id'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'startInHouse failed: ' . $e->getMessage());
            return $this->redirectBackToOrder($salesOrderId, 'Failed to start in-house work: ' . $e->getMessage(), 'error');
        }

        return $this->redirectBackToOrder($salesOrderId, 'In-house work started successfully.', 'success');
    }

    private function validateBaseInput(int $productId, int $stepId, float $qty, int $locationId): ?string
    {
        if ($productId <= 0) {
            return 'Product is required.';
        }
        if ($stepId <= 0) {
            return 'Step is required.';
        }
        if ($qty <= 0) {
            return 'Quantity must be greater than zero.';
        }
        if ($locationId <= 0) {
            return 'Location is required.';
        }

        return null;
    }

    private function redirectBackToOrder(int $salesOrderId, string $message, string $type)
    {
        $redirect = $salesOrderId > 0
            ? redirect()->to('/sales-orders/view/' . $salesOrderId)
            : redirect()->back();

        return $redirect->with($type, $message);
    }

    private function stepAllowsExecutionType(int $stepId, string $executionType, ?int $vendorId = null): bool
    {
        $db = \Config\Database::connect();
        if (! $db->tableExists('step_execution_options')) {
            return false;
        }

        $query = $db->table('step_execution_options')
            ->where('step_id', $stepId)
            ->where('execution_type', $executionType);

        if ($executionType === 'vendor' && $vendorId !== null && $vendorId > 0) {
            $query->groupStart()
                ->where('vendor_id', $vendorId)
                ->orWhere('vendor_id IS NULL', null, false)
                ->groupEnd();
        }

        return (bool) $query->countAllResults();
    }

    private function validateStepDependency(int $productId, array $selectedStep): ?string
    {
        $profileId = (int) ($selectedStep['profile_id'] ?? 0);
        $stepOrder = (int) ($selectedStep['step_order'] ?? 0);
        if ($profileId <= 0 || $stepOrder <= 1) {
            return null;
        }

        $previousStep = $this->stepModel
            ->where('profile_id', $profileId)
            ->where('step_order <', $stepOrder)
            ->orderBy('step_order', 'DESC')
            ->first();

        if (! $previousStep) {
            return null;
        }

        $previousCompleted = $this->processingRecordModel->findLatestCompletedForStep($productId, (int) $previousStep['id']);
        if (! $previousCompleted) {
            return 'This step cannot start yet. Complete the previous step first.';
        }

        return null;
    }

    private function findPreviousCompletedRecord(int $productId, array $selectedStep): ?array
    {
        $profileId = (int) ($selectedStep['profile_id'] ?? 0);
        $stepOrder = (int) ($selectedStep['step_order'] ?? 0);
        if ($profileId <= 0 || $stepOrder <= 1) {
            return null;
        }

        $previousStep = $this->stepModel
            ->where('profile_id', $profileId)
            ->where('step_order <', $stepOrder)
            ->orderBy('step_order', 'DESC')
            ->first();

        if (! $previousStep) {
            return null;
        }

        return $this->processingRecordModel->findLatestCompletedForStep($productId, (int) $previousStep['id']);
    }
}
