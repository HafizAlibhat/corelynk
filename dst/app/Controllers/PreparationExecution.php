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

        $result = $this->executeSendToVendor($salesOrderId, [
            'sales_order_line_id' => (int) ($this->request->getPost('sales_order_line_id') ?? 0),
            'product_id'          => (int) ($this->request->getPost('product_id') ?? 0),
            'step_id'             => (int) ($this->request->getPost('step_id') ?? 0),
            'vendor_id'           => (int) ($this->request->getPost('vendor_id') ?? 0),
            'qty'                 => (float) ($this->request->getPost('qty') ?? 0),
            'material_product_id' => (int) ($this->request->getPost('material_product_id') ?? 0),
            'from_location_id'    => (int) ($this->request->getPost('from_location_id') ?? 0),
            'to_location_id'      => (int) ($this->request->getPost('to_location_id') ?? 0),
            'unit_price'          => (float) ($this->request->getPost('unit_price') ?? 0),
        ]);

        return $this->redirectBackToOrder($salesOrderId, $result['message'], $result['ok'] ? 'success' : 'error');
    }

    /**
     * Send multiple preparation steps to (possibly different) vendors in one submission.
     * Each row in `items[]` uses the same fields as sendToVendor().
     */
    public function bulkSendToVendor()
    {
        $this->requireAuth();
        $this->requirePermission('sales_orders.edit');

        $salesOrderId = (int) ($this->request->getPost('sales_order_id') ?? 0);
        $items = $this->request->getPost('items') ?? [];

        if (! is_array($items) || empty($items)) {
            return $this->redirectBackToOrder($salesOrderId, 'No steps were selected to send.', 'error');
        }

        $okCount = 0;
        $errors = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $result = $this->executeSendToVendor($salesOrderId, [
                'sales_order_line_id' => (int) ($item['sales_order_line_id'] ?? 0),
                'product_id'          => (int) ($item['product_id'] ?? 0),
                'step_id'             => (int) ($item['step_id'] ?? 0),
                'vendor_id'           => (int) ($item['vendor_id'] ?? 0),
                'qty'                 => (float) ($item['qty'] ?? 0),
                'material_product_id' => (int) ($item['material_product_id'] ?? 0),
                'from_location_id'    => (int) ($item['from_location_id'] ?? 0),
                'to_location_id'      => (int) ($item['to_location_id'] ?? 0),
                'unit_price'          => (float) ($item['unit_price'] ?? 0),
            ]);

            if ($result['ok']) {
                $okCount++;
            } else {
                $errors[] = $result['message'];
            }
        }

        $message = $okCount . ' step(s) sent to vendor.';
        if (! empty($errors)) {
            $message .= ' Issues: ' . implode(' | ', array_slice($errors, 0, 5));
        }

        return $this->redirectBackToOrder($salesOrderId, $message, empty($errors) ? 'success' : 'error');
    }

    /**
     * Bundle every un-invoiced vendor lot on this order into one payable PO
     * per vendor (a step can fan out to several vendors, each at their own
     * price per lot/color). These POs are billed straight from the vendor
     * receive flow (see VendorReceive::store()) and never go through the
     * normal GRN receiving screen, since the goods already moved stock.
     */
    public function createJobPos()
    {
        $this->requireAuth();
        $this->requirePermission('sales_orders.edit');

        $salesOrderId = (int) ($this->request->getPost('sales_order_id') ?? 0);
        if ($salesOrderId <= 0) {
            return $this->redirectBackToOrder($salesOrderId, 'Invalid sales order.', 'error');
        }

        $userId = (int) (session()->get('user_id') ?? session()->get('id') ?? 0);
        $result = (new \App\Services\VendorJobPoService())->createForSalesOrder($salesOrderId, $userId);

        $message = $result['message'];
        if (! empty($result['created'])) {
            $parts = [];
            foreach ($result['created'] as $po) {
                $parts[] = $po['po_number'] . ' (' . $po['vendor_name'] . ', ' . number_format($po['total'], 2) . ')';
            }
            $message .= ' ' . implode(', ', $parts);
        }

        return $this->redirectBackToOrder($salesOrderId, $message, $result['ok'] ? 'success' : 'error');
    }

    /**
     * Close an in-house step: the work is done here, so the material becomes the
     * finished product on the spot. Vendor work cannot be closed from here — the
     * goods are physically at the vendor and must come back through the receive
     * form, which is what produces the receipt and moves the stock.
     */
    public function complete()
    {
        $this->requireAuth();
        $this->requirePermission('sales_orders.edit');

        $salesOrderId = (int) ($this->request->getPost('sales_order_id') ?? 0);
        $recordId = (int) ($this->request->getPost('record_id') ?? 0);

        $record = $recordId > 0 ? $this->processingRecordModel->find($recordId) : null;
        if (! $record || ($record['status'] ?? '') !== 'in_progress') {
            return $this->redirectBackToOrder($salesOrderId, 'Invalid or already completed processing record.', 'error');
        }

        if (! empty($record['vendor_id'])) {
            $sendNoteId = (int) ($record['parent_send_note_id'] ?? 0);
            if ($sendNoteId <= 0) {
                $sendNote = $this->sendNoteModel
                    ->where('product_id', $record['product_id'])
                    ->where('step_id', $record['step_id'])
                    ->where('vendor_id', $record['vendor_id'])
                    ->whereIn('status', ['sent', 'draft'])
                    ->orderBy('id', 'DESC')
                    ->first();
                $sendNoteId = (int) ($sendNote['id'] ?? 0);
            }

            if ($sendNoteId <= 0) {
                return $this->redirectBackToOrder($salesOrderId, 'No open vendor send note found for this step.', 'error');
            }

            return redirect()->to('/vendor-receive/' . $sendNoteId)
                ->with('success', 'Record what came back from the vendor here — stock and the receipt are created on save.');
        }

        $db = \Config\Database::connect();

        try {
            $db->transStart();

            $this->processingRecordModel->update($recordId, ['status' => 'completed']);

            $producedQty = (new \App\Services\PreparationOutputService())->produceIfFinalStep(
                (int) $record['product_id'],
                (int) $record['step_id'],
                (float) $record['qty'],
                (int) ($record['location_id'] ?? 0),
                (int) (session()->get('user_id') ?? session()->get('id') ?? 0),
                $recordId
            );

            $db->transComplete();
            if (! $db->transStatus()) {
                return $this->redirectBackToOrder($salesOrderId, 'Failed to mark step complete.', 'error');
            }
        } catch (\Throwable $e) {
            if ($db->transStatus()) {
                $db->transRollback();
            }
            log_message('error', 'PreparationExecution::complete failed: ' . $e->getMessage());
            return $this->redirectBackToOrder($salesOrderId, 'Failed to mark step complete: ' . $e->getMessage(), 'error');
        }

        $message = 'Step marked as complete.';
        if ($producedQty > 0) {
            $message .= ' ' . number_format($producedQty, 2) . ' pcs of the finished product are now in our stock.';
        }

        return $this->redirectBackToOrder($salesOrderId, $message, 'success');
    }

    /**
     * Every physical move of one product on one sales order, oldest first: what
     * went out to which vendor, what came back, what was accepted, what failed QC
     * and where those pieces went next. This is the answer to "what became of the
     * 100 pcs we sent to Waqar?".
     */
    public function trail(int $salesOrderId, int $productId)
    {
        $this->requireAuth();
        $this->requirePermission('sales_orders.read');

        $db = \Config\Database::connect();

        $sendNotes = $db->table('vendor_send_notes vsn')
            ->select('vsn.*, v.name AS vendor_name, ps.name AS step_name, fl.name AS from_location_name, tl.name AS to_location_name')
            ->select('COALESCE((SELECT SUM(vri.qty_received) FROM vendor_receive_notes vrn
                        INNER JOIN vendor_receive_items vri ON vri.receive_note_id = vrn.id
                        WHERE vrn.send_note_id = vsn.id), 0) AS qty_received', false)
            ->join('vendors v', 'v.id = vsn.vendor_id', 'left')
            ->join('preparation_steps ps', 'ps.id = vsn.step_id', 'left')
            ->join('warehouse_locations fl', 'fl.id = vsn.from_location_id', 'left')
            ->join('warehouse_locations tl', 'tl.id = vsn.to_location_id', 'left')
            ->where('vsn.product_id', $productId)
            ->where('vsn.sales_order_id', $salesOrderId)
            ->orderBy('vsn.id', 'ASC')
            ->get()->getResultArray();

        $sendNoteIds = array_column($sendNotes, 'id');

        $receives = [];
        $rejections = [];
        if ($sendNoteIds !== []) {
            $receives = $db->table('vendor_receive_notes vrn')
                ->select('vrn.id, vrn.reference_no, vrn.send_note_id, vrn.created_at, v.name AS vendor_name')
                ->select('vri.qty_received, vri.qty_accepted, vri.qty_rejected')
                ->join('vendor_receive_items vri', 'vri.receive_note_id = vrn.id', 'left')
                ->join('vendors v', 'v.id = vrn.vendor_id', 'left')
                ->whereIn('vrn.send_note_id', $sendNoteIds)
                ->orderBy('vrn.id', 'ASC')
                ->get()->getResultArray();

            $rejections = $db->table('vendor_receive_rejections')
                ->whereIn('send_note_id', $sendNoteIds)
                ->orderBy('id', 'ASC')
                ->get()->getResultArray();
        }

        $receivesByNote = [];
        foreach ($receives as $r) {
            $receivesByNote[(int) $r['send_note_id']][] = $r;
        }
        $rejectionsByNote = [];
        foreach ($rejections as $r) {
            $rejectionsByNote[(int) $r['send_note_id']][] = $r;
        }

        return view('sales_orders/partials/preparation_trail', [
            'sendNotes' => $sendNotes,
            'receivesByNote' => $receivesByNote,
            'rejectionsByNote' => $rejectionsByNote,
        ]);
    }

    /**
     * Collect goods from a vendor who cannot finish in time and hand them to a
     * different vendor for the same step — no receiving, no QC, because the work
     * was never done and the material never comes back to us. Partial is normal:
     * a vendor may keep 40 and hand over 60.
     *
     * The original note is not rewritten; the moved quantity is recorded as
     * qty_rerouted and a child note (origin = reroute) carries the trail forward.
     */
    public function rerouteToVendor()
    {
        $this->requireAuth();
        $this->requirePermission('sales_orders.edit');

        $sendNoteId = (int) ($this->request->getPost('send_note_id') ?? 0);
        $vendorId = (int) ($this->request->getPost('vendor_id') ?? 0);
        $toLocationId = (int) ($this->request->getPost('to_location_id') ?? 0);
        $qty = (float) ($this->request->getPost('qty') ?? 0);
        $reason = trim((string) ($this->request->getPost('reason') ?? ''));

        $sendNote = $sendNoteId > 0 ? $this->sendNoteModel->find($sendNoteId) : null;
        $salesOrderId = (int) ($sendNote['sales_order_id'] ?? 0);

        if (! $sendNote) {
            return $this->redirectBackToOrder($salesOrderId, 'Vendor send note not found.', 'error');
        }
        if (! in_array((string) ($sendNote['status'] ?? ''), ['sent', 'draft'], true)) {
            return $this->redirectBackToOrder($salesOrderId, 'Only an open send note can be rerouted.', 'error');
        }
        if ($vendorId <= 0 || $toLocationId <= 0 || $qty <= 0) {
            return $this->redirectBackToOrder($salesOrderId, 'New vendor, drop-off location and quantity are required.', 'error');
        }
        if ($vendorId === (int) $sendNote['vendor_id']) {
            return $this->redirectBackToOrder($salesOrderId, 'Pick a different vendor to reroute to.', 'error');
        }

        $outstanding = $this->sendNoteModel->outstandingQty($sendNoteId);
        if ($qty > $outstanding + 0.0001) {
            return $this->redirectBackToOrder($salesOrderId, 'Only ' . number_format($outstanding, 2) . ' is still with this vendor.', 'error');
        }

        $fromLocation = $this->locationModel->find((int) $sendNote['to_location_id']);
        $toLocation = $this->locationModel->find($toLocationId);
        if (! $fromLocation || ! $toLocation) {
            return $this->redirectBackToOrder($salesOrderId, 'Invalid source or destination location.', 'error');
        }
        if ((int) ($toLocation['vendor_id'] ?? 0) !== $vendorId) {
            return $this->redirectBackToOrder($salesOrderId, 'Drop-off location does not belong to the selected vendor.', 'error');
        }
        if (! $this->stepAllowsExecutionType((int) $sendNote['step_id'], 'vendor', $vendorId)) {
            return $this->redirectBackToOrder($salesOrderId, 'This step is not configured for the selected vendor.', 'error');
        }

        $physicalProductId = $this->sendNoteModel->physicalProductId($sendNoteId, (int) $sendNote['product_id']);

        $db = \Config\Database::connect();
        $userId = (int) (session()->get('user_id') ?? session()->get('id') ?? 0);
        $referenceNo = 'VSN-' . date('YmdHis') . '-' . str_pad(strtoupper(substr(dechex(mt_rand(0, 65535)), 0, 4)), 4, '0', STR_PAD_LEFT);

        try {
            $db->transStart();

            $newSendNoteId = $this->sendNoteModel->createSendNote([
                'reference_no' => $referenceNo,
                'parent_send_note_id' => $sendNoteId,
                'origin' => 'reroute',
                'vendor_id' => $vendorId,
                'step_id' => (int) $sendNote['step_id'],
                'product_id' => (int) $sendNote['product_id'],
                'sales_order_id' => $sendNote['sales_order_id'] ?? null,
                'sales_order_line_id' => $sendNote['sales_order_line_id'] ?? null,
                'qty' => number_format($qty, 4, '.', ''),
                'from_location_id' => (int) $sendNote['to_location_id'],
                'to_location_id' => $toLocationId,
                'status' => 'sent',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $this->sendNoteItemModel->addItem([
                'send_note_id' => $newSendNoteId,
                'product_id' => $physicalProductId,
                'qty' => number_format($qty, 4, '.', ''),
            ]);

            (new InventoryService())->internalTransfer(
                $physicalProductId,
                (int) ($fromLocation['warehouse_id'] ?? 0),
                (int) $sendNote['to_location_id'],
                (int) ($toLocation['warehouse_id'] ?? 0),
                $toLocationId,
                $qty,
                'Rerouted to another vendor ' . $referenceNo,
                $userId
            );

            $this->sendNoteModel->update($sendNoteId, [
                'qty_rerouted' => number_format((float) ($sendNote['qty_rerouted'] ?? 0) + $qty, 4, '.', ''),
            ]);
            if ($this->sendNoteModel->outstandingQty($sendNoteId) <= 0.0001) {
                $this->sendNoteModel->update($sendNoteId, ['status' => 'completed']);
            }

            $this->processingRecordModel->splitOpenForStep(
                (int) $sendNote['product_id'],
                (int) $sendNote['step_id'],
                (int) $sendNote['vendor_id'],
                $qty,
                'Rerouted ' . number_format($qty, 2) . ' to another vendor' . ($reason !== '' ? ': ' . $reason : '')
            );

            $this->processingRecordModel->insert([
                'product_id' => (int) $sendNote['product_id'],
                'step_id' => (int) $sendNote['step_id'],
                'vendor_id' => $vendorId,
                'sales_order_id' => $sendNote['sales_order_id'] ?? null,
                'sales_order_line_id' => $sendNote['sales_order_line_id'] ?? null,
                'qty' => number_format($qty, 4, '.', ''),
                'status' => 'in_progress',
                'location_id' => $toLocationId,
                'parent_send_note_id' => $newSendNoteId,
                'notes' => $reason !== '' ? $reason : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $db->transComplete();
            if (! $db->transStatus()) {
                return $this->redirectBackToOrder($salesOrderId, 'Failed to reroute the goods.', 'error');
            }
        } catch (\Throwable $e) {
            if ($db->transStatus()) {
                $db->transRollback();
            }
            log_message('error', 'rerouteToVendor failed: ' . $e->getMessage());

            return $this->redirectBackToOrder($salesOrderId, 'Failed to reroute: ' . $e->getMessage(), 'error');
        }

        return $this->redirectBackToOrder($salesOrderId, number_format($qty, 2) . ' pcs rerouted to the new vendor (' . $referenceNo . ').', 'success');
    }

    /**
     * Register another drop-off point for a vendor — a branch, an office, a second
     * working setup. Vendors routinely have more than one, and the send-to-vendor
     * form is where the need shows up, so it is created inline from there.
     */
    public function addVendorLocation()
    {
        $this->requireAuth();
        $this->requirePermission('sales_orders.edit');

        $vendorId = (int) ($this->request->getPost('vendor_id') ?? 0);
        $name = trim((string) ($this->request->getPost('name') ?? ''));

        if ($vendorId <= 0 || $name === '') {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => 'Vendor and location name are required.']);
        }

        $vendor = (new \App\Models\VendorModel())->find($vendorId);
        if (! $vendor) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => 'Unknown vendor.']);
        }

        $existing = $this->locationModel
            ->where('vendor_id', $vendorId)
            ->where('name', $name)
            ->first();
        if ($existing) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => 'This vendor already has a location with that name.']);
        }

        try {
            $warehouseId = $this->resolveVendorWarehouseId();
            $locationId = $this->locationModel->insert([
                'warehouse_id' => $warehouseId,
                'vendor_id'    => $vendorId,
                'name'         => $name,
                'is_active'    => 1,
            ], true);
        } catch (\Throwable $e) {
            log_message('error', 'addVendorLocation failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON(['ok' => false, 'message' => 'Could not save the location.']);
        }

        $warehouse = (new \App\Models\WarehouseModel())->find($warehouseId);

        return $this->response->setJSON([
            'ok' => true,
            'location' => [
                'id'             => (int) $locationId,
                'name'           => $name,
                'vendor_id'      => $vendorId,
                'warehouse_name' => $warehouse['name'] ?? '',
            ],
        ]);
    }

    /**
     * Vendor drop-off points live in one dedicated warehouse so vendor-held stock
     * stays separate from our own. Created on first use rather than seeded.
     */
    private function resolveVendorWarehouseId(): int
    {
        $warehouseModel = new \App\Models\WarehouseModel();

        $warehouse = $warehouseModel->where('code', 'VENDOR-PROC')->first()
            ?: $warehouseModel->like('name', 'vendor')->where('is_active', 1)->first();

        if ($warehouse) {
            return (int) $warehouse['id'];
        }

        return (int) $warehouseModel->insert([
            'name'      => 'Vendor Processing',
            'code'      => 'VENDOR-PROC',
            'is_active' => 1,
        ], true);
    }

    /**
     * Core logic shared by sendToVendor() and bulkSendToVendor().
     *
     * @return array{ok: bool, message: string}
     */
    private function executeSendToVendor(int $salesOrderId, array $input): array
    {
        $salesOrderLineId = (int) ($input['sales_order_line_id'] ?? 0);
        $productId = (int) ($input['product_id'] ?? 0);
        $stepId = (int) ($input['step_id'] ?? 0);
        $vendorId = (int) ($input['vendor_id'] ?? 0);
        $qty = (float) ($input['qty'] ?? 0);
        // What physically leaves the warehouse is the profile's base material, not the
        // finished product (which does not exist yet). Falls back to the product itself
        // for profiles with no components.
        $materialProductId = (int) ($input['material_product_id'] ?? 0) ?: $productId;
        $fromLocationId = (int) ($input['from_location_id'] ?? 0);
        $toLocationId = (int) ($input['to_location_id'] ?? 0);
        $unitPrice = (float) ($input['unit_price'] ?? 0);

        $line = $salesOrderLineId > 0 ? $this->salesOrderLineModel->find($salesOrderLineId) : null;
        $lineQty = (float) ($line['quantity'] ?? 0);
        if ($line && $lineQty > 0 && $qty > $lineQty) {
            return ['ok' => false, 'message' => 'Execution quantity cannot exceed the sales order line quantity.'];
        }

        $validationError = $this->validateBaseInput($productId, $stepId, $qty, $fromLocationId);
        if ($validationError !== null) {
            return ['ok' => false, 'message' => $validationError];
        }
        if ($vendorId <= 0 || $toLocationId <= 0) {
            return ['ok' => false, 'message' => 'Vendor and destination location are required.'];
        }

        $step = $this->stepModel->find($stepId);
        if (! $step || (int) ($step['profile_id'] ?? 0) <= 0) {
            return ['ok' => false, 'message' => 'Invalid preparation step selected.'];
        }

        $profile = $this->profileModel->find((int) $step['profile_id']);
        if (! $profile || (int) ($profile['product_id'] ?? 0) !== $productId || (int) ($profile['is_active'] ?? 0) !== 1) {
            return ['ok' => false, 'message' => 'Selected step does not match an active preparation profile for this product.'];
        }

        if (! $this->stepAllowsExecutionType($stepId, 'vendor', $vendorId)) {
            return ['ok' => false, 'message' => 'Selected step is not configured for vendor execution with this vendor.'];
        }

        $dependencyError = $this->validateStepDependency($productId, $step);
        if ($dependencyError !== null) {
            return ['ok' => false, 'message' => $dependencyError];
        }

        // Partial batches are allowed: block only once the whole line quantity is
        // either finished or already out with the vendor.
        if ($lineQty > 0) {
            $qtySummary = $this->processingRecordModel->qtySummaryForStep($productId, $stepId);
            $remaining = $lineQty - $qtySummary['done'] - $qtySummary['open'];
            if ($qty > $remaining + 0.0001) {
                return ['ok' => false, 'message' => 'Only ' . number_format(max(0, $remaining), 2) . ' left to execute for this step.'];
            }
        } elseif ($this->processingRecordModel->hasOpenRecordForStep($productId, $stepId)) {
            return ['ok' => false, 'message' => 'This step is already in progress for this product.'];
        }

        $sourceLocation = $this->locationModel->find($fromLocationId);
        $destLocation = $this->locationModel->find($toLocationId);
        if (! $sourceLocation || ! $destLocation) {
            return ['ok' => false, 'message' => 'Invalid source or destination location selected.'];
        }

        // Goods may only be dropped at a place belonging to the vendor doing the work,
        // never at one of our own storage locations.
        if ((int) ($destLocation['vendor_id'] ?? 0) !== $vendorId) {
            return ['ok' => false, 'message' => 'Drop-off location does not belong to the selected vendor.'];
        }

        $fromWarehouseId = (int) ($sourceLocation['warehouse_id'] ?? 0);
        $toWarehouseId = (int) ($destLocation['warehouse_id'] ?? 0);
        if ($fromWarehouseId <= 0 || $toWarehouseId <= 0) {
            return ['ok' => false, 'message' => 'Selected locations are missing warehouse mapping.'];
        }

        $db = \Config\Database::connect();
        $userId = (int) (session()->get('user_id') ?? session()->get('id') ?? 0);
        $referenceSuffix = strtoupper(substr(dechex(mt_rand(0, 65535)), 0, 4));
        $referenceNo = 'VSN-' . date('YmdHis') . '-' . str_pad($referenceSuffix, 4, '0', STR_PAD_LEFT);

        // Pre-check source location stock for this product to provide early warning
        try {
            $balCols = array_flip($db->getFieldNames('stock_balances'));
            $q = $db->table('stock_balances')->select('SUM(quantity) as qty')->where('location_id', $fromLocationId);
            if (isset($balCols['item_key'])) {
                $q->where('item_key', 'p' . $materialProductId);
            } else {
                $q->where('product_id', $materialProductId);
            }
            $row = $q->get()->getRowArray();
            $available = (float)($row['qty'] ?? 0);
            if ($available + 0.00001 < $qty) {
                return ['ok' => false, 'message' => 'Insufficient stock at source location. Available: ' . number_format($available, 4)];
            }
        } catch (\Throwable $_) {
            // If pre-check fails, fall back to the internalTransfer validation which will prevent negative transfer
        }

        try {
            $db->transStart();

            $sendNoteId = $this->sendNoteModel->createSendNote([
                'reference_no' => $referenceNo,
                'origin' => 'sales_order',
                'vendor_id' => $vendorId,
                'step_id' => $stepId,
                'product_id' => $productId,
                'sales_order_id' => $salesOrderId ?: null,
                'sales_order_line_id' => $salesOrderLineId ?: null,
                'qty' => number_format($qty, 4, '.', ''),
                'from_location_id' => $fromLocationId,
                'to_location_id' => $toLocationId,
                'status' => 'draft',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $itemData = [
                'send_note_id' => $sendNoteId,
                'product_id' => $materialProductId,
                'qty' => number_format($qty, 4, '.', ''),
            ];
            if ($unitPrice > 0) {
                $itemData['unit_price'] = number_format($unitPrice, 4, '.', '');
            }
            $this->sendNoteItemModel->addItem($itemData);

            $inventoryService = new InventoryService();
            $inventoryService->internalTransfer(
                $materialProductId,
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
                'sales_order_id' => $salesOrderId ?: null,
                'sales_order_line_id' => $salesOrderLineId ?: null,
                'qty' => number_format($qty, 4, '.', ''),
                'status' => 'in_progress',
                'location_id' => $toLocationId,
                'parent_id' => $previousRecord['id'] ?? null,
                'parent_send_note_id' => $sendNoteId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $this->sendNoteModel->update($sendNoteId, ['status' => 'sent']);

            $db->transComplete();
            if (! $db->transStatus()) {
                return ['ok' => false, 'message' => 'Failed to create vendor send action.'];
            }
        } catch (\Throwable $e) {
            if ($db->transStatus()) {
                $db->transRollback();
            }
            log_message('error', 'sendToVendor failed: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Failed to send to vendor: ' . $e->getMessage()];
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

        return ['ok' => true, 'message' => 'Sent to vendor successfully.' . $rfqMessage];
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

        // Partial batches are allowed: block only once the whole line quantity is
        // either finished or already out with the vendor.
        if ($lineQty > 0) {
            $qtySummary = $this->processingRecordModel->qtySummaryForStep($productId, $stepId);
            $remaining = $lineQty - $qtySummary['done'] - $qtySummary['open'];
            if ($qty > $remaining + 0.0001) {
                return $this->redirectBackToOrder($salesOrderId, 'Only ' . number_format(max(0, $remaining), 2) . ' left to execute for this step.', 'error');
            }
        } elseif ($this->processingRecordModel->hasOpenRecordForStep($productId, $stepId)) {
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
