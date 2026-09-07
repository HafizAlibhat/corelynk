<?php

namespace App\Controllers;

use App\Models\ProcessingRecordModel;
use App\Models\QcRejectionReasonModel;
use App\Models\VendorQcRecordModel;
use App\Models\VendorReceiveItemModel;
use App\Models\VendorReceiveNoteModel;
use App\Models\VendorSendNoteItemModel;
use App\Models\VendorSendNoteModel;
use App\Models\WarehouseLocationModel;
use App\Services\InventoryService;

class VendorReceive extends BaseController
{
    private VendorSendNoteModel $sendNoteModel;
    private VendorSendNoteItemModel $sendNoteItemModel;
    private VendorReceiveNoteModel $receiveNoteModel;
    private VendorReceiveItemModel $receiveItemModel;
    private VendorQcRecordModel $qcRecordModel;
    private QcRejectionReasonModel $rejectionReasonModel;
    private ProcessingRecordModel $processingRecordModel;
    private WarehouseLocationModel $locationModel;

    public function __construct()
    {
        $this->sendNoteModel = new VendorSendNoteModel();
        $this->sendNoteItemModel = new VendorSendNoteItemModel();
        $this->receiveNoteModel = new VendorReceiveNoteModel();
        $this->receiveItemModel = new VendorReceiveItemModel();
        $this->qcRecordModel = new VendorQcRecordModel();
        $this->rejectionReasonModel = new QcRejectionReasonModel();
        $this->processingRecordModel = new ProcessingRecordModel();
        $this->locationModel = new WarehouseLocationModel();
    }

    public function index()
    {
        $this->requireAuth();
        $this->requirePermission('sales_orders.edit');

        $db = \Config\Database::connect();

        $rows = $db->table('vendor_send_notes vsn')
            ->select('vsn.*, v.name AS vendor_name, p.name AS product_name, p.code AS product_code, ps.name AS step_name')
            ->select('(vsn.qty - COALESCE((SELECT SUM(vri.qty_received) FROM vendor_receive_notes vrn INNER JOIN vendor_receive_items vri ON vri.receive_note_id = vrn.id WHERE vrn.send_note_id = vsn.id), 0)) AS remaining_qty', false)
            ->join('vendors v', 'v.id = vsn.vendor_id', 'left')
            ->join('products p', 'p.id = vsn.product_id', 'left')
            ->join('preparation_steps ps', 'ps.id = vsn.step_id', 'left')
            ->whereIn('vsn.status', ['sent', 'draft'])
            ->orderBy('vsn.id', 'DESC')
            ->get()
            ->getResultArray();

        $sendNotes = [];
        foreach ($rows as $row) {
            if ((float) ($row['remaining_qty'] ?? 0) <= 0) {
                continue;
            }
            $sendNotes[] = $row;
        }

        return view('vendor_receive/index', [
            'send_notes' => $sendNotes,
        ]);
    }

    public function receiveForm($sendNoteId)
    {
        $this->requireAuth();
        $this->requirePermission('sales_orders.edit');

        $sendNoteId = (int) $sendNoteId;
        $db = \Config\Database::connect();

        $sendNote = $db->table('vendor_send_notes vsn')
            ->select('vsn.*, v.name AS vendor_name, p.name AS product_name, p.code AS product_code, ps.name AS step_name, ps.step_order')
            ->join('vendors v', 'v.id = vsn.vendor_id', 'left')
            ->join('products p', 'p.id = vsn.product_id', 'left')
            ->join('preparation_steps ps', 'ps.id = vsn.step_id', 'left')
            ->where('vsn.id', $sendNoteId)
            ->get()
            ->getRowArray();

        if (! $sendNote) {
            return redirect()->back()->with('error', 'Vendor send note not found.');
        }

        // Load send note items (including optional unit_price)
        $sendItems = [];
        try {
            $sendItems = $db->table('vendor_send_note_items vsi')
                ->select('vsi.*, p.name AS product_name, p.code AS product_code')
                ->join('products p', 'p.id = vsi.product_id', 'left')
                ->where('vsi.send_note_id', $sendNoteId)
                ->orderBy('vsi.id', 'ASC')
                ->get()
                ->getResultArray();
        } catch (\Throwable $_) {
            $sendItems = [];
        }

        $receivedTotalRow = $db->table('vendor_receive_notes vrn')
            ->select('COALESCE(SUM(vri.qty_received), 0) AS received_total', false)
            ->join('vendor_receive_items vri', 'vri.receive_note_id = vrn.id', 'left')
            ->where('vrn.send_note_id', $sendNoteId)
            ->get()
            ->getRowArray();

        $alreadyReceived = (float) ($receivedTotalRow['received_total'] ?? 0);
        $sentQty = (float) ($sendNote['qty'] ?? 0);
        $remainingQty = max(0, $sentQty - $alreadyReceived);

        $rejectionReasons = $this->rejectionReasonModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
        $locations = $db->table('warehouse_locations wl')
            ->select('wl.id, wl.name, wl.warehouse_id, w.name AS warehouse_name')
            ->join('warehouses w', 'w.id = wl.warehouse_id', 'left')
            ->where('wl.is_active', 1)
            ->orderBy('w.name', 'ASC')
            ->orderBy('wl.name', 'ASC')
            ->get()
            ->getResultArray();
        $vendors = $db->table('vendors')->select('id, name')->where('is_active', 1)->orderBy('name', 'ASC')->get()->getResultArray();

        $nextSteps = $db->table('preparation_steps ps')
            ->select('ps.id, ps.name, ps.step_order')
            ->join('preparation_steps cur', 'cur.profile_id = ps.profile_id', 'inner')
            ->where('cur.id', (int) ($sendNote['step_id'] ?? 0))
            ->where('ps.step_order >', (int) ($sendNote['step_order'] ?? 0))
            ->orderBy('ps.step_order', 'ASC')
            ->get()
            ->getResultArray();

        return view('vendor_receive/form', [
            'send_note' => $sendNote,
            'remaining_qty' => $remainingQty,
            'already_received' => $alreadyReceived,
            'rejection_reasons' => $rejectionReasons,
            'locations' => $locations,
            'vendors' => $vendors,
            'next_steps' => $nextSteps,
            'send_items' => $sendItems,
        ]);
    }

    public function store()
    {
        $this->requireAuth();
        $this->requirePermission('sales_orders.edit');

        $sendNoteId = (int) ($this->request->getPost('send_note_id') ?? 0);
        $qtyReceived = (float) ($this->request->getPost('qty_received') ?? 0);
        $qtyAccepted = (float) ($this->request->getPost('qty_accepted') ?? 0);
        $qtyRejected = (float) ($this->request->getPost('qty_rejected') ?? 0);
        $acceptedToLocationId = (int) ($this->request->getPost('accepted_to_location_id') ?? 0);
        $nextStepId = (int) ($this->request->getPost('next_step_id') ?? 0);
        $rejectedAction = (string) ($this->request->getPost('rejected_action') ?? 'hold');
        $rejectionReasonId = (int) ($this->request->getPost('rejection_reason_id') ?? 0);
        $reworkVendorId = (int) ($this->request->getPost('rework_vendor_id') ?? 0);
        $reworkToLocationId = (int) ($this->request->getPost('rework_to_location_id') ?? 0);

        $qcCheckNames = $this->request->getPost('qc_check_name') ?? [];
        $qcStatuses = $this->request->getPost('qc_status') ?? [];
        $qcRemarks = $this->request->getPost('qc_remarks') ?? [];

        $db = \Config\Database::connect();
        $sendNote = $this->sendNoteModel->find($sendNoteId);
        if (! $sendNote) {
            return redirect()->back()->withInput()->with('error', 'Vendor send note not found.');
        }
        if (! in_array((string) ($sendNote['status'] ?? ''), ['sent', 'draft'], true)) {
            return redirect()->back()->withInput()->with('error', 'Only sent or draft notes can be received.');
        }

        $receivedTotalRow = $db->table('vendor_receive_notes vrn')
            ->select('COALESCE(SUM(vri.qty_received), 0) AS received_total', false)
            ->join('vendor_receive_items vri', 'vri.receive_note_id = vrn.id', 'left')
            ->where('vrn.send_note_id', $sendNoteId)
            ->get()
            ->getRowArray();
        $alreadyReceived = (float) ($receivedTotalRow['received_total'] ?? 0);
        $sentQty = (float) ($sendNote['qty'] ?? 0);
        $remainingQty = max(0, $sentQty - $alreadyReceived);

        if ($qtyReceived <= 0) {
            return redirect()->back()->withInput()->with('error', 'Received quantity must be greater than zero.');
        }
        if ($qtyReceived - $remainingQty > 0.0001) {
            return redirect()->back()->withInput()->with('error', 'Received quantity cannot exceed sent quantity remaining.');
        }
        if (abs(($qtyAccepted + $qtyRejected) - $qtyReceived) > 0.0001) {
            return redirect()->back()->withInput()->with('error', 'Accepted + Rejected must equal Received quantity.');
        }

        $checks = [];
        foreach ((array) $qcCheckNames as $i => $nameRaw) {
            $name = trim((string) $nameRaw);
            $status = trim((string) ($qcStatuses[$i] ?? ''));
            $remarks = trim((string) ($qcRemarks[$i] ?? ''));
            if ($name === '') {
                continue;
            }
            if (! in_array($status, ['pass', 'fail'], true)) {
                return redirect()->back()->withInput()->with('error', 'All QC checks must have pass or fail status.');
            }
            $checks[] = [
                'check_name' => $name,
                'status' => $status,
                'remarks' => $remarks !== '' ? $remarks : null,
            ];
        }

        if (count($checks) < 1) {
            return redirect()->back()->withInput()->with('error', 'QC is mandatory. Add at least one QC check.');
        }

        $hasQcFail = false;
        foreach ($checks as $check) {
            if ($check['status'] === 'fail') {
                $hasQcFail = true;
                break;
            }
        }

        if ($qtyRejected > 0 && $rejectionReasonId <= 0) {
            return redirect()->back()->withInput()->with('error', 'Rejection reason is required when rejected quantity is greater than zero.');
        }
        if ($qtyRejected > 0 && ! $hasQcFail) {
            return redirect()->back()->withInput()->with('error', 'Rejected quantity requires at least one failed QC check.');
        }
        if ($qtyRejected <= 0 && $hasQcFail) {
            return redirect()->back()->withInput()->with('error', 'Failed QC check requires rejected quantity.');
        }

        if ($qtyAccepted > 0 && $acceptedToLocationId <= 0) {
            return redirect()->back()->withInput()->with('error', 'Accepted destination location is required for accepted quantity.');
        }

        if ($qtyRejected > 0 && $rejectedAction === 'rework') {
            if ($reworkVendorId <= 0 || $reworkToLocationId <= 0) {
                return redirect()->back()->withInput()->with('error', 'Rework vendor and destination location are required for rework action.');
            }
        }

        $sourceVendorLocationId = (int) ($sendNote['to_location_id'] ?? 0);
        $sourceVendorLocation = $this->locationModel->find($sourceVendorLocationId);
        if (! $sourceVendorLocation) {
            return redirect()->back()->withInput()->with('error', 'Source vendor location is not valid on send note.');
        }

        $userId = (int) (session()->get('user_id') ?? session()->get('id') ?? 0);
        $referenceNo = 'VRN-' . date('YmdHis') . '-' . str_pad(strtoupper(substr(dechex(mt_rand(0, 65535)), 0, 4)), 4, '0', STR_PAD_LEFT);

        try {
            $db->transStart();

            $this->receiveNoteModel->insert([
                'reference_no' => $referenceNo,
                'vendor_id' => (int) $sendNote['vendor_id'],
                'send_note_id' => $sendNoteId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $receiveNoteId = (int) $this->receiveNoteModel->getInsertID();

            $this->receiveItemModel->insert([
                'receive_note_id' => $receiveNoteId,
                'product_id' => (int) $sendNote['product_id'],
                'qty_received' => number_format($qtyReceived, 4, '.', ''),
                'qty_accepted' => number_format($qtyAccepted, 4, '.', ''),
                'qty_rejected' => number_format($qtyRejected, 4, '.', ''),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $receiveItemId = (int) $this->receiveItemModel->getInsertID();

            $reasonName = null;
            if ($rejectionReasonId > 0) {
                $reasonRow = $this->rejectionReasonModel->find($rejectionReasonId);
                $reasonName = $reasonRow['name'] ?? null;
            }

            foreach ($checks as $check) {
                $remarks = $check['remarks'];
                if ($reasonName !== null && $check['status'] === 'fail') {
                    $remarks = trim((string) (($remarks ?? '') . ' | Rejection: ' . $reasonName));
                }

                $this->qcRecordModel->insert([
                    'receive_item_id' => $receiveItemId,
                    'check_name' => $check['check_name'],
                    'status' => $check['status'],
                    'remarks' => $remarks !== '' ? $remarks : null,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $inventoryService = new InventoryService();

            if ($qtyAccepted > 0) {
                $acceptedLocation = $this->locationModel->find($acceptedToLocationId);
                if (! $acceptedLocation) {
                    throw new \RuntimeException('Accepted destination location not found.');
                }

                $inventoryService->internalTransfer(
                    (int) $sendNote['product_id'],
                    (int) ($sourceVendorLocation['warehouse_id'] ?? 0),
                    (int) $sourceVendorLocationId,
                    (int) ($acceptedLocation['warehouse_id'] ?? 0),
                    (int) $acceptedToLocationId,
                    $qtyAccepted,
                    'Vendor receive accepted ' . $referenceNo,
                    $userId
                );

                if ($nextStepId > 0) {
                    $this->processingRecordModel->insert([
                        'product_id' => (int) $sendNote['product_id'],
                        'step_id' => $nextStepId,
                        'vendor_id' => null,
                        'qty' => number_format($qtyAccepted, 4, '.', ''),
                        'status' => 'in_progress',
                        'location_id' => (int) $acceptedToLocationId,
                        'parent_id' => null,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            if ($qtyRejected > 0) {
                $this->processingRecordModel->insert([
                    'product_id' => (int) $sendNote['product_id'],
                    'step_id' => (int) $sendNote['step_id'],
                    'vendor_id' => (int) $sendNote['vendor_id'],
                    'qty' => number_format($qtyRejected, 4, '.', ''),
                    'status' => 'ready_for_qc',
                    'location_id' => (int) $sourceVendorLocationId,
                    'parent_id' => null,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

                if ($rejectedAction === 'rework') {
                    $reworkLocation = $this->locationModel->find($reworkToLocationId);
                    if (! $reworkLocation) {
                        throw new \RuntimeException('Rework destination location not found.');
                    }

                    $newSendRef = 'VSN-' . date('YmdHis') . '-' . str_pad(strtoupper(substr(dechex(mt_rand(0, 65535)), 0, 4)), 4, '0', STR_PAD_LEFT);

                    $this->sendNoteModel->insert([
                        'reference_no' => $newSendRef,
                        'vendor_id' => $reworkVendorId,
                        'step_id' => (int) $sendNote['step_id'],
                        'product_id' => (int) $sendNote['product_id'],
                        'qty' => number_format($qtyRejected, 4, '.', ''),
                        'from_location_id' => (int) $sourceVendorLocationId,
                        'to_location_id' => (int) $reworkToLocationId,
                        'status' => 'sent',
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                    $newSendId = (int) $this->sendNoteModel->getInsertID();

                    $this->sendNoteItemModel->insert([
                        'send_note_id' => $newSendId,
                        'product_id' => (int) $sendNote['product_id'],
                        'qty' => number_format($qtyRejected, 4, '.', ''),
                    ]);

                    $inventoryService->internalTransfer(
                        (int) $sendNote['product_id'],
                        (int) ($sourceVendorLocation['warehouse_id'] ?? 0),
                        (int) $sourceVendorLocationId,
                        (int) ($reworkLocation['warehouse_id'] ?? 0),
                        (int) $reworkToLocationId,
                        $qtyRejected,
                        'Rework resend from receive ' . $referenceNo,
                        $userId
                    );

                    $this->processingRecordModel->insert([
                        'product_id' => (int) $sendNote['product_id'],
                        'step_id' => (int) $sendNote['step_id'],
                        'vendor_id' => $reworkVendorId,
                        'qty' => number_format($qtyRejected, 4, '.', ''),
                        'status' => 'in_progress',
                        'location_id' => (int) $reworkToLocationId,
                        'parent_id' => null,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            $newReceived = $alreadyReceived + $qtyReceived;
            $newStatus = $newReceived + 0.0001 >= $sentQty ? 'completed' : 'sent';
            $this->sendNoteModel->update($sendNoteId, ['status' => $newStatus]);

            if ($newStatus === 'completed') {
                $openProcessing = $this->processingRecordModel
                    ->where('product_id', (int) $sendNote['product_id'])
                    ->where('step_id', (int) $sendNote['step_id'])
                    ->where('vendor_id', (int) $sendNote['vendor_id'])
                    ->whereIn('status', ['in_progress', 'ready_for_qc'])
                    ->orderBy('id', 'ASC')
                    ->first();

                if ($openProcessing && !empty($openProcessing['id'])) {
                    $this->processingRecordModel->update((int) $openProcessing['id'], [
                        'status' => 'completed',
                    ]);
                }
            }

            $db->transComplete();

            if (! $db->transStatus()) {
                return redirect()->back()->withInput()->with('error', 'Failed to save vendor receiving.');
            }
        } catch (\Throwable $e) {
            if ($db->transStatus()) {
                $db->transRollback();
            }
            log_message('error', 'Vendor receive store failed: ' . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Failed to save receiving: ' . $e->getMessage());
        }

        return redirect()->to('/vendor-receive/' . $sendNoteId)->with('success', 'Vendor receiving and QC saved successfully.');
    }
}
