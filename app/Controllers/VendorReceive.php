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
        helper(['text', 'number', 'barcode']);
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
            ->select('vsn.*, v.name AS vendor_name, v.vendor_code, v.phone AS vendor_phone, v.email AS vendor_email, v.address AS vendor_address, v.contact_person AS vendor_contact')
            ->select('ps.name AS step_name, so.order_number')
            ->select('fl.name AS from_location_name, tl.name AS to_location_name, fw.name AS from_warehouse_name, tw.name AS to_warehouse_name')
            ->join('vendors v', 'v.id = vsn.vendor_id', 'left')
            ->join('preparation_steps ps', 'ps.id = vsn.step_id', 'left')
            ->join('sales_orders so', 'so.id = vsn.sales_order_id', 'left')
            ->join('warehouse_locations fl', 'fl.id = vsn.from_location_id', 'left')
            ->join('warehouses fw', 'fw.id = fl.warehouse_id', 'left')
            ->join('warehouse_locations tl', 'tl.id = vsn.to_location_id', 'left')
            ->join('warehouses tw', 'tw.id = tl.warehouse_id', 'left')
            ->where('vsn.id', $sendNoteId)
            ->get()->getRowArray();

        if (! $note) {
            return redirect()->to('/vendor-receive')->with('error', 'Vendor send note not found.');
        }

        $linesQuery = $db->table('vendor_send_note_items vsi')
            ->select('vsi.qty, vsi.unit_price, p.name AS product_name, p.code AS product_code, p.sku AS product_sku, p.unit AS product_unit, p.description AS product_desc')
            ->join('products p', 'p.id = vsi.product_id', 'left');

        if ($db->fieldExists('variant_id', 'vendor_send_note_items')) {
            $linesQuery->select('pv.art_number AS variant_art_number, pv.name AS variant_name')
                ->join('product_variants pv', 'pv.id = vsi.variant_id', 'left');
        }

        $lines = $linesQuery->where('vsi.send_note_id', $sendNoteId)
            ->orderBy('vsi.id', 'ASC')
            ->get()->getResultArray();

        foreach ($lines as &$line) {
            $code = ! empty($line['variant_art_number']) ? $line['variant_art_number'] : (! empty($line['product_code']) ? $line['product_code'] : ($line['product_sku'] ?? ''));
            $line['resolved_code'] = $code;
        }
        unset($line);

        $company = (new \App\Models\CompanySettingsModel())->orderBy('id', 'DESC')->first() ?: [];

        $verifyToken = generate_secure_outwork_token('lot', (int) $note['id'], (string) ($note['reference_no'] ?? ''));
        $verifyUrl = site_url('vendor-receive/verify/' . $verifyToken);

        return view('vendor_receive/slip', [
            'kind' => 'send',
            'title' => 'Vendor Send Note',
            'note' => $note,
            'lines' => $lines,
            'rejections' => [],
            'company' => $company,
            'verifyToken' => $verifyToken,
            'verifyUrl' => $verifyUrl,
        ]);
    }

    /**
     * Combined printable manifest of all material lots dispatched for a sales order.
     */
    public function combinedSendSlip(int $salesOrderId)
    {
        $this->requireAuth();
        $this->requirePermission('sales_orders.read');

        $db = \Config\Database::connect();

        $order = $db->table('sales_orders so')
            ->select('so.*, c.name AS customer_name, c.customer_code')
            ->join('customers c', 'c.id = so.customer_id', 'left')
            ->where('so.id', $salesOrderId)
            ->get()->getRowArray();

        if (! $order) {
            return redirect()->to('/sales-orders')->with('error', 'Sales order not found.');
        }

        if (! empty($order['customer_snapshot']) && is_string($order['customer_snapshot'])) {
            $snap = json_decode($order['customer_snapshot'], true);
            if (is_array($snap)) {
                $order['customer_phone'] = $snap['phone'] ?? '';
                $order['customer_address'] = $snap['address'] ?? '';
            }
        }

        $vendorFilterId = (int) ($this->request->getGet('vendor_id') ?? 0);
        $statusFilter = trim((string) ($this->request->getGet('status') ?? ''));

        $query = $db->table('vendor_send_notes vsn')
            ->select('vsn.*, v.name AS vendor_name, v.vendor_code, v.phone AS vendor_phone, v.email AS vendor_email, v.address AS vendor_address, v.contact_person AS vendor_contact')
            ->select('ps.name AS step_name, so.order_number')
            ->select('fl.name AS from_location_name, tl.name AS to_location_name, fw.name AS from_warehouse_name, tw.name AS to_warehouse_name')
            ->select('p_target.name AS target_product_name, p_target.code AS target_product_code, p_target.sku AS target_product_sku')
            ->select('sol.description AS sol_description, sol.product_variant_id AS sol_variant_id')
            ->select('target_pv.name AS target_variant_name, target_pv.art_number AS target_variant_art')
            ->join('vendors v', 'v.id = vsn.vendor_id', 'left')
            ->join('preparation_steps ps', 'ps.id = vsn.step_id', 'left')
            ->join('sales_orders so', 'so.id = vsn.sales_order_id', 'left')
            ->join('products p_target', 'p_target.id = vsn.product_id', 'left')
            ->join('sales_order_lines sol', 'sol.id = vsn.sales_order_line_id', 'left')
            ->join('product_variants target_pv', 'target_pv.id = sol.product_variant_id', 'left')
            ->join('warehouse_locations fl', 'fl.id = vsn.from_location_id', 'left')
            ->join('warehouses fw', 'fw.id = fl.warehouse_id', 'left')
            ->join('warehouse_locations tl', 'tl.id = vsn.to_location_id', 'left')
            ->join('warehouses tw', 'tw.id = tl.warehouse_id', 'left')
            ->where('vsn.sales_order_id', $salesOrderId);

        if ($vendorFilterId > 0) {
            $query->where('vsn.vendor_id', $vendorFilterId);
        }
        if ($statusFilter !== '') {
            $query->where('vsn.status', $statusFilter);
        } else {
            // Exclude cancelled notes by default
            $query->where('vsn.status !=', 'cancelled');
        }

        $notes = $query->orderBy('vsn.created_at', 'ASC')->orderBy('vsn.id', 'ASC')->get()->getResultArray();

        if (empty($notes)) {
            return redirect()->to('/sales-orders/view/' . $salesOrderId)->with('error', 'No active vendor send notes found for this sales order.');
        }

        // Fetch all items for these send notes
        $noteIds = array_column($notes, 'id');
        $itemsQuery = $db->table('vendor_send_note_items vsi')
            ->select('vsi.*, p.name AS product_name, p.code AS product_code, p.sku AS product_sku, p.unit AS product_unit, p.description AS product_desc')
            ->join('products p', 'p.id = vsi.product_id', 'left');

        if ($db->fieldExists('variant_id', 'vendor_send_note_items')) {
            $itemsQuery->select('pv.art_number AS variant_art_number, pv.name AS variant_name')
                ->join('product_variants pv', 'pv.id = vsi.variant_id', 'left');
        }

        $rawItems = $itemsQuery->whereIn('vsi.send_note_id', $noteIds)
            ->orderBy('vsi.id', 'ASC')
            ->get()->getResultArray();

        $itemsByNote = [];
        foreach ($rawItems as $item) {
            $code = ! empty($item['variant_art_number']) ? $item['variant_art_number'] : (! empty($item['product_code']) ? $item['product_code'] : ($item['product_sku'] ?? ''));
            $item['resolved_code'] = $code;
            $itemsByNote[$item['send_note_id']][] = $item;
        }

        // Distinct vendors for toolbar filter, complete names, and tokens
        $distinctVendors = [];
        foreach ($notes as &$note) {
            $note['items'] = $itemsByNote[$note['id']] ?? [];

            // Short reference not exceeding 6 characters (e.g. #8DE1)
            $ref = (string) ($note['reference_no'] ?? '');
            if (str_contains($ref, '-')) {
                $parts = explode('-', $ref);
                $note['short_ref'] = '#' . strtoupper(substr(end($parts), 0, 5));
            } else {
                $note['short_ref'] = '#' . str_pad((string) $note['id'], 4, '0', STR_PAD_LEFT);
            }

            // Target Product: Code & complete name
            $tCode = ! empty($note['target_variant_art']) ? $note['target_variant_art'] : (! empty($note['target_product_code']) ? $note['target_product_code'] : ($note['target_product_sku'] ?? ''));
            $tBase = $note['target_product_name'] ?? 'Target Product';
            $tFullName = ! empty($note['target_variant_name']) ? ($tBase . ' (' . $note['target_variant_name'] . ')') : $tBase;
            $note['target_code'] = $tCode ?: '-';
            $note['target_full_name'] = $tFullName;

            // Dispatched Material Required: Code & complete name
            $item0 = $note['items'][0] ?? null;
            $mCode = $item0['resolved_code'] ?? ($item0['product_code'] ?? ($item0['product_sku'] ?? ''));
            $mBase = $item0['product_name'] ?? ($note['target_product_name'] ?? 'Material');
            $mFullName = ! empty($item0['variant_name']) ? ($mBase . ' (' . $item0['variant_name'] . ')') : $mBase;
            $note['mat_code'] = $mCode ?: '-';
            $note['mat_full_name'] = $mFullName;

            // Encrypted verification token for this lot
            $note['verify_token'] = generate_secure_outwork_token('lot', (int) $note['id'], $ref);
            $note['verify_url'] = site_url('vendor-receive/verify/' . $note['verify_token']);

            if (! empty($note['vendor_id'])) {
                $distinctVendors[$note['vendor_id']] = [
                    'id' => (int) $note['vendor_id'],
                    'name' => $note['vendor_name'] ?? 'Vendor #' . $note['vendor_id'],
                    'code' => $note['vendor_code'] ?? '',
                    'contact' => $note['vendor_contact'] ?? '',
                    'phone' => $note['vendor_phone'] ?? '',
                    'address' => $note['vendor_address'] ?? '',
                ];
            }
        }
        unset($note);

        $orderToken = generate_secure_outwork_token('order', (int) $order['id'], (string) ($order['order_number'] ?? ''));
        $orderVerifyUrl = site_url('vendor-receive/verify/' . $orderToken);

        $company = (new \App\Models\CompanySettingsModel())->orderBy('id', 'DESC')->first() ?: [];

        return view('vendor_receive/combined_slip', [
            'order' => $order,
            'notes' => $notes,
            'distinctVendors' => array_values($distinctVendors),
            'selectedVendorId' => $vendorFilterId,
            'selectedStatus' => $statusFilter,
            'company' => $company,
            'orderToken' => $orderToken,
            'orderVerifyUrl' => $orderVerifyUrl,
        ]);
    }

    /**
     * Printable pack/bag stickers for every lot dispatched in a sales order.
     */
    public function lotStickers(int $salesOrderId)
    {
        $this->requireAuth();
        $this->requirePermission('sales_orders.read');

        $db = \Config\Database::connect();

        $order = $db->table('sales_orders so')
            ->select('so.*')
            ->where('so.id', $salesOrderId)
            ->get()->getRowArray();

        if (! $order) {
            return redirect()->to('/sales-orders')->with('error', 'Sales order not found.');
        }

        $notes = $db->table('vendor_send_notes vsn')
            ->select('vsn.*, v.name AS vendor_name, v.vendor_code')
            ->select('ps.name AS step_name, so.order_number')
            ->select('fl.name AS from_location_name, tl.name AS to_location_name')
            ->select('p_target.name AS target_product_name, p_target.code AS target_product_code, p_target.sku AS target_product_sku')
            ->select('sol.description AS sol_description, sol.product_variant_id AS sol_variant_id')
            ->select('target_pv.name AS target_variant_name, target_pv.art_number AS target_variant_art')
            ->join('vendors v', 'v.id = vsn.vendor_id', 'left')
            ->join('preparation_steps ps', 'ps.id = vsn.step_id', 'left')
            ->join('sales_orders so', 'so.id = vsn.sales_order_id', 'left')
            ->join('products p_target', 'p_target.id = vsn.product_id', 'left')
            ->join('sales_order_lines sol', 'sol.id = vsn.sales_order_line_id', 'left')
            ->join('product_variants target_pv', 'target_pv.id = sol.product_variant_id', 'left')
            ->join('warehouse_locations fl', 'fl.id = vsn.from_location_id', 'left')
            ->join('warehouse_locations tl', 'tl.id = vsn.to_location_id', 'left')
            ->where('vsn.sales_order_id', $salesOrderId)
            ->where('vsn.status !=', 'cancelled')
            ->orderBy('vsn.created_at', 'ASC')
            ->orderBy('vsn.id', 'ASC')
            ->get()->getResultArray();

        if (empty($notes)) {
            return redirect()->to('/sales-orders/view/' . $salesOrderId)->with('error', 'No active lots found for stickers.');
        }

        $noteIds = array_column($notes, 'id');
        $itemsQuery = $db->table('vendor_send_note_items vsi')
            ->select('vsi.*, p.name AS product_name, p.code AS product_code, p.sku AS product_sku, p.unit AS product_unit')
            ->join('products p', 'p.id = vsi.product_id', 'left');

        if ($db->fieldExists('variant_id', 'vendor_send_note_items')) {
            $itemsQuery->select('pv.art_number AS variant_art_number, pv.name AS variant_name')
                ->join('product_variants pv', 'pv.id = vsi.variant_id', 'left');
        }

        $rawItems = $itemsQuery->whereIn('vsi.send_note_id', $noteIds)
            ->orderBy('vsi.id', 'ASC')
            ->get()->getResultArray();

        $itemsByNote = [];
        foreach ($rawItems as $item) {
            $code = ! empty($item['variant_art_number']) ? $item['variant_art_number'] : (! empty($item['product_code']) ? $item['product_code'] : ($item['product_sku'] ?? ''));
            $item['resolved_code'] = $code;
            $itemsByNote[$item['send_note_id']][] = $item;
        }

        foreach ($notes as &$note) {
            $note['items'] = $itemsByNote[$note['id']] ?? [];

            $ref = (string) ($note['reference_no'] ?? '');
            if (str_contains($ref, '-')) {
                $parts = explode('-', $ref);
                $note['short_ref'] = '#' . strtoupper(substr(end($parts), 0, 5));
            } else {
                $note['short_ref'] = '#' . str_pad((string) $note['id'], 4, '0', STR_PAD_LEFT);
            }

            // Target product code & complete name
            $tCode = ! empty($note['target_variant_art']) ? $note['target_variant_art'] : (! empty($note['target_product_code']) ? $note['target_product_code'] : ($note['target_product_sku'] ?? ''));
            $tBase = $note['target_product_name'] ?? 'Target Product';
            $tFullName = ! empty($note['target_variant_name']) ? ($tBase . ' (' . $note['target_variant_name'] . ')') : $tBase;
            $note['target_code'] = $tCode ?: '-';
            $note['target_full_name'] = $tFullName;

            // Dispatched material code & complete name
            $item0 = $note['items'][0] ?? null;
            $mCode = $item0['resolved_code'] ?? ($item0['product_code'] ?? ($item0['product_sku'] ?? ''));
            $mBase = $item0['product_name'] ?? ($note['target_product_name'] ?? 'Material');
            $mFullName = ! empty($item0['variant_name']) ? ($mBase . ' (' . $item0['variant_name'] . ')') : $mBase;
            $note['mat_code'] = $mCode ?: '-';
            $note['mat_full_name'] = $mFullName;

            // Encrypted verification token for this lot
            $note['verify_token'] = generate_secure_outwork_token('lot', (int) $note['id'], $ref);
            $note['verify_url'] = site_url('vendor-receive/verify/' . $note['verify_token']);
        }
        unset($note);

        $orderToken = generate_secure_outwork_token('order', (int) $order['id'], (string) ($order['order_number'] ?? ''));
        $orderVerifyUrl = site_url('vendor-receive/verify/' . $orderToken);

        $company = (new \App\Models\CompanySettingsModel())->orderBy('id', 'DESC')->first() ?: [];

        return view('vendor_receive/lot_stickers', [
            'order' => $order,
            'notes' => $notes,
            'company' => $company,
            'orderToken' => $orderToken,
            'orderVerifyUrl' => $orderVerifyUrl,
        ]);
    }

    /**
     * Public verification page for scanned QR codes / Barcode encrypted tokens.
     */
    public function verify(string $token)
    {
        helper(['text', 'number', 'barcode']);
        $data = decrypt_secure_outwork_token($token);

        $company = (new \App\Models\CompanySettingsModel())->orderBy('id', 'DESC')->first() ?: [];

        if (! $data || empty($data['t']) || empty($data['id'])) {
            return view('vendor_receive/verify_result', [
                'isValid' => false,
                'company' => $company,
                'message' => 'The scanned QR code or verification token is invalid, unrecognized, or tampered.',
            ]);
        }

        $db = \Config\Database::connect();
        $type = $data['t'];
        $id = (int) $data['id'];

        if ($type === 'order') {
            $order = $db->table('sales_orders so')
                ->select('so.*')
                ->where('so.id', $id)
                ->get()->getRowArray();

            if (! $order) {
                return view('vendor_receive/verify_result', [
                    'isValid' => false,
                    'company' => $company,
                    'message' => 'Sales order record matching this token could not be found.',
                ]);
            }

            $notes = $db->table('vendor_send_notes vsn')
                ->select('vsn.*, v.name AS vendor_name, ps.name AS step_name')
                ->join('vendors v', 'v.id = vsn.vendor_id', 'left')
                ->join('preparation_steps ps', 'ps.id = vsn.step_id', 'left')
                ->where('vsn.sales_order_id', $id)
                ->where('vsn.status !=', 'cancelled')
                ->orderBy('vsn.created_at', 'ASC')
                ->get()->getResultArray();

            return view('vendor_receive/verify_result', [
                'isValid' => true,
                'type' => 'order',
                'order' => $order,
                'notes' => $notes,
                'company' => $company,
                'verifiedAt' => $data['ts'] ?? time(),
            ]);
        }

        if ($type === 'lot') {
            $note = $db->table('vendor_send_notes vsn')
                ->select('vsn.*, v.name AS vendor_name, v.vendor_code, v.phone AS vendor_phone')
                ->select('ps.name AS step_name, so.order_number, so.id AS so_id')
                ->select('fl.name AS from_location_name, tl.name AS to_location_name')
                ->select('p_target.name AS target_product_name, p_target.code AS target_product_code, p_target.sku AS target_product_sku')
                ->select('sol.description AS sol_description')
                ->select('target_pv.name AS target_variant_name, target_pv.art_number AS target_variant_art')
                ->join('vendors v', 'v.id = vsn.vendor_id', 'left')
                ->join('preparation_steps ps', 'ps.id = vsn.step_id', 'left')
                ->join('sales_orders so', 'so.id = vsn.sales_order_id', 'left')
                ->join('products p_target', 'p_target.id = vsn.product_id', 'left')
                ->join('sales_order_lines sol', 'sol.id = vsn.sales_order_line_id', 'left')
                ->join('product_variants target_pv', 'target_pv.id = sol.product_variant_id', 'left')
                ->join('warehouse_locations fl', 'fl.id = vsn.from_location_id', 'left')
                ->join('warehouse_locations tl', 'tl.id = vsn.to_location_id', 'left')
                ->where('vsn.id', $id)
                ->get()->getRowArray();

            if (! $note) {
                return view('vendor_receive/verify_result', [
                    'isValid' => false,
                    'company' => $company,
                    'message' => 'Dispatched lot record matching this token could not be found.',
                ]);
            }

            $items = $db->table('vendor_send_note_items vsi')
                ->select('vsi.*, p.name AS product_name, p.code AS product_code, p.sku AS product_sku, p.unit AS product_unit')
                ->select('pv.art_number AS variant_art_number, pv.name AS variant_name')
                ->join('products p', 'p.id = vsi.product_id', 'left')
                ->join('product_variants pv', 'pv.id = vsi.variant_id', 'left')
                ->where('vsi.send_note_id', $id)
                ->get()->getResultArray();

            return view('vendor_receive/verify_result', [
                'isValid' => true,
                'type' => 'lot',
                'note' => $note,
                'items' => $items,
                'company' => $company,
                'verifiedAt' => $data['ts'] ?? time(),
            ]);
        }

        return view('vendor_receive/verify_result', [
            'isValid' => false,
            'company' => $company,
            'message' => 'Unknown token payload.',
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
            ->select('vrn.*, v.name AS vendor_name, v.vendor_code, v.phone AS vendor_phone, v.email AS vendor_email, v.address AS vendor_address, v.contact_person AS vendor_contact')
            ->select('vsn.reference_no AS send_reference_no, vsn.qty AS sent_qty, vsn.status AS send_status, vsn.created_at AS send_created_at')
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
            ->select('vri.qty_received, vri.qty_accepted, vri.qty_rejected, p.name AS product_name, p.code AS product_code, p.sku AS product_sku, p.unit AS product_unit, p.description AS product_desc')
            ->join('products p', 'p.id = vri.product_id', 'left')
            ->where('vri.receive_note_id', $receiveNoteId)
            ->orderBy('vri.id', 'ASC')
            ->get()->getResultArray();

        foreach ($lines as &$line) {
            $code = ! empty($line['product_code']) ? $line['product_code'] : ($line['product_sku'] ?? '');
            $line['resolved_code'] = $code;
        }
        unset($line);

        $rejections = $db->table('vendor_receive_rejections')
            ->where('receive_note_id', $receiveNoteId)
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();

        $company = (new \App\Models\CompanySettingsModel())->orderBy('id', 'DESC')->first() ?: [];

        $verifyToken = ! empty($note['send_note_id'])
            ? generate_secure_outwork_token('lot', (int) $note['send_note_id'], (string) ($note['send_reference_no'] ?? ''))
            : null;
        $verifyUrl = $verifyToken ? site_url('vendor-receive/verify/' . $verifyToken) : null;

        return view('vendor_receive/slip', [
            'kind' => 'receive',
            'title' => 'Vendor Receive Note',
            'note' => $note,
            'lines' => $lines,
            'rejections' => $rejections,
            'company' => $company,
            'verifyToken' => $verifyToken,
            'verifyUrl' => $verifyUrl,
        ]);
    }

    public function index()
    {
        $this->requireAuth();
        $this->requirePermission('sales_orders.edit');

        $db = \Config\Database::connect();

        $rows = $db->table('vendor_send_notes vsn')
            ->select('vsn.*, v.name AS vendor_name, p.name AS product_name, p.code AS product_code, p.sku AS product_sku, ps.name AS step_name')
            ->select('sol.product_variant_id, sol.description AS line_description')
            ->select('so_pv.name AS variant_name, so_pv.art_number AS variant_art_number')
            ->select('tl.name AS to_location_name, tw.name AS to_warehouse_name')
            ->select('(vsn.qty - COALESCE((SELECT SUM(vri.qty_received) FROM vendor_receive_notes vrn INNER JOIN vendor_receive_items vri ON vri.receive_note_id = vrn.id WHERE vrn.send_note_id = vsn.id), 0)) AS remaining_qty', false)
            ->join('vendors v', 'v.id = vsn.vendor_id', 'left')
            ->join('products p', 'p.id = vsn.product_id', 'left')
            ->join('preparation_steps ps', 'ps.id = vsn.step_id', 'left')
            ->join('sales_order_lines sol', 'sol.id = vsn.sales_order_line_id', 'left')
            ->join('product_variants so_pv', 'so_pv.id = sol.product_variant_id', 'left')
            ->join('warehouse_locations tl', 'tl.id = vsn.to_location_id', 'left')
            ->join('warehouses tw', 'tw.id = tl.warehouse_id', 'left')
            ->whereIn('vsn.status', ['sent', 'draft'])
            ->orderBy('vsn.id', 'DESC')
            ->get()
            ->getResultArray();

        $sendNoteIds = array_filter(array_column($rows, 'id'));
        $itemsByNote = [];
        if (! empty($sendNoteIds)) {
            $itemQuery = $db->table('vendor_send_note_items vsi')
                ->select('vsi.send_note_id, vsi.qty, p.name AS product_name, p.code AS product_code, p.sku AS product_sku')
                ->join('products p', 'p.id = vsi.product_id', 'left');
            if ($db->fieldExists('variant_id', 'vendor_send_note_items')) {
                $itemQuery->select('pv.name AS variant_name, pv.art_number AS variant_art_number')
                    ->join('product_variants pv', 'pv.id = vsi.variant_id', 'left');
            }
            $itemRows = $itemQuery->whereIn('vsi.send_note_id', $sendNoteIds)->get()->getResultArray();
            foreach ($itemRows as $ir) {
                $itemsByNote[$ir['send_note_id']][] = $ir;
            }
        }

        $sendNotes = [];
        foreach ($rows as $row) {
            if ((float) ($row['remaining_qty'] ?? 0) <= 0) {
                continue;
            }

            $variantName = ! empty($row['variant_name']) ? $row['variant_name'] : '';
            $code = ! empty($row['variant_art_number']) ? $row['variant_art_number'] : (! empty($row['product_code']) ? $row['product_code'] : ($row['product_sku'] ?? ''));

            if (empty($variantName) && ! empty($itemsByNote[$row['id']][0]['variant_name'])) {
                $variantName = $itemsByNote[$row['id']][0]['variant_name'];
            }
            if (empty($code) && ! empty($itemsByNote[$row['id']][0]['variant_art_number'])) {
                $code = $itemsByNote[$row['id']][0]['variant_art_number'];
            }

            $row['resolved_variant_name'] = $variantName;
            $row['resolved_code'] = $code;

            $sentParts = [];
            if (! empty($itemsByNote[$row['id']])) {
                foreach ($itemsByNote[$row['id']] as $it) {
                    $part = $it['product_name'] ?? '';
                    if (! empty($it['variant_name'])) {
                        $part .= ' — ' . $it['variant_name'];
                    }
                    if (! empty($it['variant_art_number'])) {
                        $part .= ' (' . $it['variant_art_number'] . ')';
                    } elseif (! empty($it['product_code'])) {
                        $part .= ' (' . $it['product_code'] . ')';
                    }
                    if ($part !== '') {
                        $sentParts[] = $part;
                    }
                }
            }
            $row['sent_item_summary'] = implode(', ', $sentParts);

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
            ->select('vsn.*, v.name AS vendor_name, p.name AS product_name, p.code AS product_code, p.sku AS product_sku, ps.name AS step_name, ps.step_order')
            ->select('sol.product_variant_id, sol.description AS line_description')
            ->select('so_pv.name AS variant_name, so_pv.art_number AS variant_art_number')
            ->select('tl.name AS to_location_name, tw.name AS to_warehouse_name')
            ->join('vendors v', 'v.id = vsn.vendor_id', 'left')
            ->join('products p', 'p.id = vsn.product_id', 'left')
            ->join('preparation_steps ps', 'ps.id = vsn.step_id', 'left')
            ->join('sales_order_lines sol', 'sol.id = vsn.sales_order_line_id', 'left')
            ->join('product_variants so_pv', 'so_pv.id = sol.product_variant_id', 'left')
            ->join('warehouse_locations tl', 'tl.id = vsn.to_location_id', 'left')
            ->join('warehouses tw', 'tw.id = tl.warehouse_id', 'left')
            ->where('vsn.id', $sendNoteId)
            ->get()
            ->getRowArray();

        if (! $sendNote) {
            return redirect()->back()->with('error', 'Vendor send note not found.');
        }

        // Load send note items (including optional unit_price)
        $sendItems = [];
        try {
            $itemQuery = $db->table('vendor_send_note_items vsi')
                ->select('vsi.*, p.name AS product_name, p.code AS product_code, p.sku AS product_sku')
                ->join('products p', 'p.id = vsi.product_id', 'left');
            if ($db->fieldExists('variant_id', 'vendor_send_note_items')) {
                $itemQuery->select('pv.name AS variant_name, pv.art_number AS variant_art_number')
                    ->join('product_variants pv', 'pv.id = vsi.variant_id', 'left');
            }
            $sendItems = $itemQuery->where('vsi.send_note_id', $sendNoteId)
                ->orderBy('vsi.id', 'ASC')
                ->get()
                ->getResultArray();
        } catch (\Throwable $_) {
            $sendItems = [];
        }

        $variantName = ! empty($sendNote['variant_name']) ? $sendNote['variant_name'] : '';
        $code = ! empty($sendNote['variant_art_number']) ? $sendNote['variant_art_number'] : (! empty($sendNote['product_code']) ? $sendNote['product_code'] : ($sendNote['product_sku'] ?? ''));
        if (empty($variantName) && ! empty($sendItems[0]['variant_name'])) {
            $variantName = $sendItems[0]['variant_name'];
        }
        if (empty($code) && ! empty($sendItems[0]['variant_art_number'])) {
            $code = $sendItems[0]['variant_art_number'];
        }
        $sendNote['resolved_variant_name'] = $variantName;
        $sendNote['resolved_code'] = $code;

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
        $physicalVariantId = $this->sendNoteModel->physicalVariantId($sendNoteId);
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
                'Received ' . number_format($qtyReceived, 2) . ' on ' . $referenceNo,
                (int) ($sendNote['sales_order_line_id'] ?? 0) ?: null,
                $sendNoteId
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
                    $userId,
                    $physicalVariantId
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
                        $userId,
                        $physicalVariantId
                    );

                    // 'rejected' is neither done nor in progress, so the step's
                    // outstanding quantity opens back up and the material can be
                    // refinished and sent out again from the sales order.
                    $this->processingRecordModel->insert([
                        'product_id' => $physicalProductId,
                        'variant_id' => $physicalVariantId,
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
                        $userId,
                        $physicalVariantId
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
