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

    /**
     * Printable proof of what physically left our store for the vendor.
     */
    public function sendSlip(int $sendNoteId)
    {
        $this->requireAuth();
        $this->requirePermission('sales_orders.read');

        $db = \Config\Database::connect();

        $note = $db->table('vendor_send_notes vsn')
            ->select('vsn.*, v.name AS vendor_name, ps.name AS step_name, so.order_number')
            ->select('fl.name AS from_location_name, tl.name AS to_location_name')
            ->join('vendors v', 'v.id = vsn.vendor_id', 'left')
            ->join('preparation_steps ps', 'ps.id = vsn.step_id', 'left')
            ->join('sales_orders so', 'so.id = vsn.sales_order_id', 'left')
            ->join('warehouse_locations fl', 'fl.id = vsn.from_location_id', 'left')
            ->join('warehouse_locations tl', 'tl.id = vsn.to_location_id', 'left')
            ->where('vsn.id', $sendNoteId)
            ->get()->getRowArray();

        if (! $note) {
            return redirect()->to('/vendor-receive')->with('error', 'Vendor send note not found.');
        }

        $lines = $db->table('vendor_send_note_items vsi')
            ->select('vsi.qty, vsi.unit_price, p.name AS product_name, p.sku AS product_code')
            ->join('products p', 'p.id = vsi.product_id', 'left')
            ->where('vsi.send_note_id', $sendNoteId)
            ->get()->getResultArray();

        return view('vendor_receive/slip', [
            'kind' => 'send',
            'title' => 'Vendor Send Note',
            'note' => $note,
            'lines' => $lines,
            'rejections' => [],
        ]);
    }

    /**
     * Printable proof of what came back: received, accepted and rejected.
     */
    public function receiveSlip(int $receiveNoteId)
    {
        $this->requireAuth();
        $this->requirePermission('sales_orders.read');

        $db = \Config\Database::connect();

        $note = $db->table('vendor_receive_notes vrn')
            ->select('vrn.*, v.name AS vendor_name, vsn.reference_no AS send_reference_no, vsn.qty AS sent_qty')
            ->select('ps.name AS step_name, so.order_number')
            ->join('vendors v', 'v.id = vrn.vendor_id', 'left')
            ->join('vendor_send_notes vsn', 'vsn.id = vrn.send_note_id', 'left')
            ->join('preparation_steps ps', 'ps.id = vsn.step_id', 'left')
            ->join('sales_orders so', 'so.id = vsn.sales_order_id', 'left')
            ->where('vrn.id', $receiveNoteId)
            ->get()->getRowArray();

        if (! $note) {
            return redirect()->to('/vendor-receive')->with('error', 'Vendor receive note not found.');
        }

        $lines = $db->table('vendor_receive_items vri')
            ->select('vri.qty_received, vri.qty_accepted, vri.qty_rejected, p.name AS product_name, p.sku AS product_code')
            ->join('products p', 'p.id = vri.product_id', 'left')
            ->where('vri.receive_note_id', $receiveNoteId)
            ->get()->getResultArray();

        $rejections = $db->table('vendor_receive_rejections')
            ->where('receive_note_id', $receiveNoteId)
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();

        return view('vendor_receive/slip', [
            'kind' => 'receive',
            'title' => 'Vendor Receive Note',
            'note' => $note,
            'lines' => $lines,
            'rejections' => $rejections,
        ]);
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
        // Goods we take back land in our own storage; goods handed on for rework
        // land at a vendor's place. Two different lists, never mixed.
        $ownLocations = $db->table('warehouse_locations wl')
            ->select('wl.id, wl.name, wl.warehouse_id, w.name AS warehouse_name')
            ->join('warehouses w', 'w.id = wl.warehouse_id', 'left')
            ->where('wl.is_active', 1)
            ->where('wl.vendor_id IS NULL')
            ->orderBy('w.name', 'ASC')
            ->orderBy('wl.name', 'ASC')
            ->get()
            ->getResultArray();
        $vendorLocations = $this->locationModel->getVendorLocations();
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
            'own_locations' => $ownLocations,
            'vendor_locations' => $vendorLocations,
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
        $returnToLocationId = (int) ($this->request->getPost('return_to_location_id') ?? 0);

        $qcCheckNames = $this->request->getPost('qc_check_name') ?? [];
        $qcStatuses = $this->request->getPost('qc_status') ?? [];
        $qcRemarks = $this->request->getPost('qc_remarks') ?? [];

        $db = \Config\Database::connect();
        $sendNote = $this->sendNoteModel->find($sendNoteId);
        if (! $sendNote) {
            return redirect()->back()->withInput()->with('error', 'Vendor send note not found.');
        }

        // Stock moves follow the shipped item, which on a sales order is the raw
        // material, not the finished product named on the note header.
        $physicalProductId = $this->sendNoteModel->physicalProductId($sendNoteId, (int) $sendNote['product_id']);
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
        if ($qtyRejected > 0 && $rejectedAction === 'return' && $returnToLocationId <= 0) {
            return redirect()->back()->withInput()->with('error', 'Return location is required when rejected goods come back to our stock.');
        }
        if ($qtyRejected > 0 && ! in_array($rejectedAction, ['hold', 'return', 'rework'], true)) {
            return redirect()->back()->withInput()->with('error', 'Unknown action for the rejected quantity.');
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
            $producedQty = 0.0;

            // The batch this vendor was holding splits here: what came back stops
            // being work-in-progress, whatever they still keep stays open.
            $this->processingRecordModel->splitOpenForStep(
                (int) $sendNote['product_id'],
                (int) $sendNote['step_id'],
                (int) $sendNote['vendor_id'],
                $qtyReceived,
                'Received ' . number_format($qtyReceived, 2) . ' on ' . $referenceNo
            );

            if ($qtyAccepted > 0) {
                $acceptedLocation = $this->locationModel->find($acceptedToLocationId);
                if (! $acceptedLocation) {
                    throw new \RuntimeException('Accepted destination location not found.');
                }

                $inventoryService->internalTransfer(
                    $physicalProductId,
                    (int) ($sourceVendorLocation['warehouse_id'] ?? 0),
                    (int) $sourceVendorLocationId,
                    (int) ($acceptedLocation['warehouse_id'] ?? 0),
                    (int) $acceptedToLocationId,
                    $qtyAccepted,
                    'Vendor receive accepted ' . $referenceNo,
                    $userId
                );

                // Only the accepted quantity counts as this step being done.
                $this->processingRecordModel->insert([
                    'product_id' => (int) $sendNote['product_id'],
                    'step_id' => (int) $sendNote['step_id'],
                    'vendor_id' => (int) $sendNote['vendor_id'],
                    'sales_order_id' => $sendNote['sales_order_id'] ?? null,
                    'sales_order_line_id' => $sendNote['sales_order_line_id'] ?? null,
                    'qty' => number_format($qtyAccepted, 4, '.', ''),
                    'status' => 'completed',
                    'location_id' => (int) $acceptedToLocationId,
                    'parent_send_note_id' => $sendNoteId,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

                if ($nextStepId > 0) {
                    $this->processingRecordModel->insert([
                        'product_id' => (int) $sendNote['product_id'],
                        'step_id' => $nextStepId,
                        'vendor_id' => null,
                        'sales_order_id' => $sendNote['sales_order_id'] ?? null,
                        'sales_order_line_id' => $sendNote['sales_order_line_id'] ?? null,
                        'qty' => number_format($qtyAccepted, 4, '.', ''),
                        'status' => 'in_progress',
                        'location_id' => (int) $acceptedToLocationId,
                        'parent_id' => null,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }

                // Last step done: the prepared material becomes the finished
                // product, otherwise the sales order line stays short forever.
                $producedQty += (new \App\Services\PreparationOutputService())->produceIfFinalStep(
                    (int) $sendNote['product_id'],
                    (int) $sendNote['step_id'],
                    $qtyAccepted,
                    (int) $acceptedToLocationId,
                    $userId,
                    $receiveNoteId
                );
            }

            if ($qtyRejected > 0) {
                // Every rejection is recorded, whatever we decide to do with the
                // goods — that row is the audit trail for the failed quantity.
                $rejectionRef = 'REJ-' . date('YmdHis') . '-' . str_pad(strtoupper(substr(dechex(mt_rand(0, 65535)), 0, 4)), 4, '0', STR_PAD_LEFT);
                $rejectionStatus = [
                    'return' => 'returned_to_stock',
                    'rework' => 'sent_for_rework',
                ][$rejectedAction] ?? 'on_hold';

                $db->table('vendor_receive_rejections')->insert([
                    'rejection_ref' => $rejectionRef,
                    'receive_note_id' => $receiveNoteId,
                    'receive_item_id' => $receiveItemId,
                    'send_note_id' => $sendNoteId,
                    'vendor_id' => (int) $sendNote['vendor_id'],
                    'product_id' => (int) $sendNote['product_id'],
                    'sales_order_id' => $sendNote['sales_order_id'] ?? null,
                    'qty_rejected' => number_format($qtyRejected, 4, '.', ''),
                    'action_type' => $rejectedAction,
                    'status' => $rejectionStatus,
                    'rejection_reason_id' => $rejectionReasonId > 0 ? $rejectionReasonId : null,
                    'rejection_reason_text' => $reasonName,
                    'handled_by_user_id' => $userId > 0 ? $userId : null,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

                if ($rejectedAction === 'return') {
                    // Bad pieces come back into our own stock so they can be
                    // refinished here and sent out again as fresh work.
                    $returnLocation = $this->locationModel->find($returnToLocationId);
                    if (! $returnLocation) {
                        throw new \RuntimeException('Return destination location not found.');
                    }

                    $inventoryService->internalTransfer(
                        $physicalProductId,
                        (int) ($sourceVendorLocation['warehouse_id'] ?? 0),
                        (int) $sourceVendorLocationId,
                        (int) ($returnLocation['warehouse_id'] ?? 0),
                        (int) $returnToLocationId,
                        $qtyRejected,
                        'Rejected stock returned ' . $rejectionRef,
                        $userId
                    );

                    // 'rejected' is neither done nor in progress, so the step's
                    // outstanding quantity opens back up and the material can be
                    // refinished and sent out again from the sales order.
                    $this->processingRecordModel->insert([
                        'product_id' => (int) $sendNote['product_id'],
                        'step_id' => (int) $sendNote['step_id'],
                        'vendor_id' => (int) $sendNote['vendor_id'],
                        'sales_order_id' => $sendNote['sales_order_id'] ?? null,
                        'sales_order_line_id' => $sendNote['sales_order_line_id'] ?? null,
                        'qty' => number_format($qtyRejected, 4, '.', ''),
                        'status' => 'rejected',
                        'location_id' => (int) $returnToLocationId,
                        'parent_send_note_id' => $sendNoteId,
                        'rework_reason_id' => $rejectionReasonId > 0 ? $rejectionReasonId : null,
                        'notes' => 'Returned to stock for refinishing (' . $rejectionRef . ')',
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                } elseif ($rejectedAction === 'rework') {
                    $reworkLocation = $this->locationModel->find($reworkToLocationId);
                    if (! $reworkLocation) {
                        throw new \RuntimeException('Rework destination location not found.');
                    }
                    if ((int) ($reworkLocation['vendor_id'] ?? 0) !== $reworkVendorId) {
                        throw new \RuntimeException('Rework location does not belong to the selected vendor.');
                    }

                    $newSendRef = 'VSN-' . date('YmdHis') . '-' . str_pad(strtoupper(substr(dechex(mt_rand(0, 65535)), 0, 4)), 4, '0', STR_PAD_LEFT);

                    $this->sendNoteModel->insert([
                        'reference_no' => $newSendRef,
                        'parent_send_note_id' => $sendNoteId,
                        'origin' => 'rework',
                        'vendor_id' => $reworkVendorId,
                        'step_id' => (int) $sendNote['step_id'],
                        'product_id' => (int) $sendNote['product_id'],
                        'sales_order_id' => $sendNote['sales_order_id'] ?? null,
                        'sales_order_line_id' => $sendNote['sales_order_line_id'] ?? null,
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
                        $physicalProductId,
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
                        'sales_order_id' => $sendNote['sales_order_id'] ?? null,
                        'sales_order_line_id' => $sendNote['sales_order_line_id'] ?? null,
                        'qty' => number_format($qtyRejected, 4, '.', ''),
                        'status' => 'in_progress',
                        'location_id' => (int) $reworkToLocationId,
                        'parent_send_note_id' => $newSendId,
                        'rework_reason_id' => $rejectionReasonId > 0 ? $rejectionReasonId : null,
                        'rework_vendor_id' => $reworkVendorId,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                } else {
                    // 'hold': the goods physically stay at the vendor until someone
                    // decides to return them or send them on.
                    $this->processingRecordModel->insert([
                        'product_id' => (int) $sendNote['product_id'],
                        'step_id' => (int) $sendNote['step_id'],
                        'vendor_id' => (int) $sendNote['vendor_id'],
                        'sales_order_id' => $sendNote['sales_order_id'] ?? null,
                        'sales_order_line_id' => $sendNote['sales_order_line_id'] ?? null,
                        'qty' => number_format($qtyRejected, 4, '.', ''),
                        'status' => 'ready_for_qc',
                        'location_id' => (int) $sourceVendorLocationId,
                        'parent_send_note_id' => $sendNoteId,
                        'rework_reason_id' => $rejectionReasonId > 0 ? $rejectionReasonId : null,
                        'notes' => 'Rejected, held at vendor (' . $rejectionRef . ')',
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            // If this lot's job cost was already invoiced onto a subcontract PO
            // line, mark that line received for billing purposes only — the
            // goods already moved stock above, so this must never touch
            // inventory again (no GRN, no InventoryService call here).
            if ($qtyAccepted > 0) {
                $jobPoLine = $db->table('purchase_order_lines')
                    ->where('vendor_send_note_id', $sendNoteId)
                    ->get()->getRowArray();
                if ($jobPoLine) {
                    $newReceived = min((float) $jobPoLine['qty'], (float) $jobPoLine['qty_received'] + $qtyAccepted);
                    $db->table('purchase_order_lines')->where('id', $jobPoLine['id'])->update(['qty_received' => $newReceived]);
                    (new \App\Services\PurchaseOrderStatusService())->deriveAndUpdateStatus((int) $jobPoLine['po_id']);
                }
            }

            $this->sendNoteModel->update($sendNoteId, [
                'status' => $this->sendNoteModel->outstandingQty($sendNoteId) <= 0.0001 ? 'completed' : 'sent',
            ]);

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

        $message = 'Vendor receiving and QC saved successfully.';
        if ($producedQty > 0) {
            $message .= ' ' . number_format($producedQty, 2) . ' pcs of the finished product are now in our stock.';
        }

        // Back to the order the goods belong to — that is where the user is
        // watching the shortage clear.
        if (! empty($sendNote['sales_order_id'])) {
            return redirect()->to('/sales-orders/view/' . (int) $sendNote['sales_order_id'])->with('success', $message);
        }

        return redirect()->to('/vendor-receive/' . $sendNoteId)->with('success', $message);
    }
}
