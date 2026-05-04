<?php

namespace App\Controllers;

use App\Models\GatePassModel;
use App\Models\VendorModel;
use App\Models\ProductModel;

class GatePasses extends BaseController
{
    protected GatePassModel $gatePassModel;
    protected VendorModel $vendorModel;

    public function __construct()
    {
        $this->gatePassModel = new GatePassModel();
        $this->vendorModel = new VendorModel();
    }

    public function index()
    {
        $this->requireAuth();

        $gatePasses = $this->gatePassModel
            ->select("gate_passes.*, vendors.name as vendor_name, vendors.contact_person, users.username as created_by_name")
            ->join('vendors', 'vendors.id = gate_passes.vendor_id', 'left')
            ->join('users', 'users.id = gate_passes.created_by', 'left')
            ->orderBy('gate_passes.created_at', 'DESC')
            ->findAll();

        $vendors = $this->vendorModel->select('id, name, contact_person')->orderBy('name', 'ASC')->findAll();

        $products = [];
        try {
            $productModel = new ProductModel();
            $products = $productModel->select('id, name, code')->orderBy('name', 'ASC')->findAll();
        } catch (\Throwable $e) {
            $products = [];
        }

        $stats = [
            'total_passes' => (new GatePassModel())->countAll(),
            'pending_in'   => (new GatePassModel())->where(['type' => 'incoming', 'status' => 'pending'])->countAllResults(),
            'pending_out'  => (new GatePassModel())->where(['type' => 'outgoing', 'status' => 'pending'])->countAllResults(),
            'today_passes' => (new GatePassModel())->where('DATE(created_at)', date('Y-m-d'))->countAllResults(),
        ];

        return view('gate_passes/index', [
            'title' => 'Gate Pass Management',
            'gatePasses' => $gatePasses,
            'vendors' => $vendors,
            'products' => $products,
            'stats' => $stats,
        ]);
    }

    public function create()
    {
        if (!$this->request->isAJAX()) {
            return redirect()->to('/gate_passes');
        }

        $json = $this->request->getJSON(true) ?: [];

        $type = $json['type'] ?? '';
        $recipientType = $json['recipient_type'] ?? 'vendor';
        $vendorId = isset($json['vendor_id']) ? (int) $json['vendor_id'] : 0;
        $recipientName = trim((string) ($json['recipient_name'] ?? ''));
        $itemsIn = $json['items'] ?? [];

        if (!in_array($type, ['incoming', 'outgoing'], true)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid type']);
        }
        if ($recipientType === 'vendor' && $vendorId <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => 'Select a vendor']);
        }
        if ($recipientType === 'internal' && $recipientName === '') {
            return $this->response->setJSON(['success' => false, 'message' => 'Enter internal location name']);
        }
        if (empty($itemsIn) || !is_array($itemsIn)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Add at least one item']);
        }

        $productModel = new ProductModel();
        $normalized = [];
        foreach ($itemsIn as $row) {
            if (!is_array($row)) {
                continue;
            }
            $qty = (float) ($row['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $unit = $row['unit'] ?? 'Pcs';
            $remarks = $row['remarks'] ?? null;
            $productId = (int) ($row['product_id'] ?? 0);
            $description = trim((string) ($row['description'] ?? ''));
            if ($productId > 0) {
                $prod = $productModel->select('id, name, code')->find($productId);
                if ($prod && $description === '') {
                    $description = ($prod['code'] ? ($prod['code'] . ' - ') : '') . $prod['name'];
                }
            }
            if ($description === '' && $productId <= 0) {
                continue;
            }
            $normalized[] = [
                'product_id' => $productId ?: null,
                'description' => $description,
                'quantity' => $qty,
                'unit' => $unit,
                'remarks' => $remarks,
            ];
        }
        if (empty($normalized)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Please add at least one valid item (description or product) with quantity']);
        }

        $data = [
            'type' => $type,
            'recipient_type' => $recipientType,
            'recipient_name' => $recipientType === 'internal' ? $recipientName : null,
            'vendor_id' => $recipientType === 'vendor' ? $vendorId : null,
            'purpose' => $json['purpose'] ?? null,
            'items' => json_encode($normalized),
            'status' => 'pending',
            'expected_date' => $json['expected_date'] ?? null,
            'notes' => $json['notes'] ?? null,
            'created_by' => session('user_id') ?: 1,
        ];

        try {
            $id = $this->gatePassModel->insert($data);
            $gatePass = $this->gatePassModel->find($id);
            return $this->response->setJSON(['success' => true, 'message' => 'Gate pass created successfully', 'gate_pass' => $gatePass]);
        } catch (\Throwable $e) {
            log_message('error', 'Error creating gate pass: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'Error creating gate pass: ' . $e->getMessage()]);
        }
    }

    public function updateStatus($id)
    {
        if (!$this->request->isAJAX()) {
            return redirect()->to('/gate_passes');
        }

        $json = $this->request->getJSON(true) ?: [];
        $status = $json['status'] ?? '';

        if (!in_array($status, ['approved', 'completed', 'rejected', 'cancelled'], true)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid status']);
        }

        $updateData = [
            'status' => $status,
        ];
        if ($status === 'completed') {
            $updateData['actual_date'] = date('Y-m-d H:i:s');
            $updateData['completed_by'] = session('user_id') ?: 1;
        }
        if (isset($json['remarks'])) {
            $updateData['remarks'] = $json['remarks'];
        }

        try {
            $this->gatePassModel->update($id, $updateData);
            $gatePass = $this->gatePassModel->find($id);
            return $this->response->setJSON(['success' => true, 'message' => 'Gate pass status updated', 'gate_pass' => $gatePass]);
        } catch (\Throwable $e) {
            log_message('error', 'Error updating gate pass status: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'Error updating status: ' . $e->getMessage()]);
        }
    }

    public function generatePDF($id)
    {
        $gatePass = $this->gatePassModel->select("
            gate_passes.*,
            vendors.name as vendor_name,
            vendors.address as vendor_address,
            vendors.contact_person,
            vendors.phone as vendor_phone,
            users.username as created_by_name
        ")
            ->join('vendors', 'vendors.id = gate_passes.vendor_id', 'left')
            ->join('users', 'users.id = gate_passes.created_by', 'left')
            ->find($id);

        if (!$gatePass) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Gate pass not found');
        }

        $items = json_decode($gatePass['items'] ?? '[]', true) ?: [];
        $html = $this->buildGatePassHTML($gatePass, $items);

        try {
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $filename = 'gate_pass_' . ($gatePass['gate_pass_number'] ?? $gatePass['id']) . '.pdf';
            $this->response->setHeader('Content-Type', 'application/pdf');
            $this->response->setHeader('Content-Disposition', 'inline; filename="' . $filename . '"');
            echo $dompdf->output();
            return;
        } catch (\Throwable $e) {
            $this->response->setHeader('Content-Type', 'text/html; charset=UTF-8');
            return $html;
        }
    }

    private function buildGatePassHTML(array $gatePass, array $items): string
    {
        $typeLabel = ucfirst($gatePass['type']) . ' Gate Pass';
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Gate Pass - ' . htmlspecialchars($gatePass['gate_pass_number'] ?? (string) $gatePass['id']) . '</title><style>body{font-family:Arial,sans-serif;margin:20px;line-height:1.4}.header{text-align:center;border-bottom:2px solid #333;padding-bottom:20px;margin-bottom:30px}.company-name{font-size:24px;font-weight:700;color:#333}.gate-pass-title{font-size:18px;color:#666;margin-top:10px}.info-section{margin-bottom:30px}.info-row{display:flex;margin-bottom:10px}.info-label{font-weight:700;width:150px}.info-value{flex:1}.items-table{width:100%;border-collapse:collapse;margin:20px 0}.items-table th,.items-table td{border:1px solid #ddd;padding:10px;text-align:left}.items-table th{background-color:#f8f9fa;font-weight:700}.status-badge{padding:5px 10px;border-radius:4px;font-weight:700;text-transform:uppercase}.signatures{margin-top:40px;display:flex;justify-content:space-between}.signature-box{text-align:center;width:200px}.signature-line{border-bottom:1px solid #333;height:40px;margin-bottom:10px}.footer{margin-top:40px;text-align:center;font-size:12px;color:#666;border-top:1px solid #ddd;padding-top:20px}.watermark{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-45deg);font-size:100px;color:#f0f0f0;z-index:-1}</style></head><body>';

        if (in_array($gatePass['status'] ?? '', ['cancelled', 'rejected'], true)) {
            $html .= '<div class="watermark">' . strtoupper($gatePass['status']) . '</div>';
        }

        $html .= '<div class="header"><div class="company-name">PRODUCTION MANAGEMENT SYSTEM</div><div class="gate-pass-title">' . $typeLabel . '</div></div>';

        $html .= '<div class="info-section>'
            . '<div class="info-row"><div class="info-label">Gate Pass No:</div><div class="info-value"><strong>' . htmlspecialchars($gatePass['gate_pass_number'] ?? (string) $gatePass['id']) . '</strong></div></div>'
            . '<div class="info-row"><div class="info-label">Date:</div><div class="info-value">' . date('d/m/Y H:i', strtotime($gatePass['created_at'])) . '</div></div>'
            . '<div class="info-row"><div class="info-label">Type:</div><div class="info-value">' . $typeLabel . '</div></div>'
            . '<div class="info-row"><div class="info-label">Status:</div><div class="info-value"><span class="status-badge status-' . htmlspecialchars($gatePass['status']) . '">' . ucfirst($gatePass['status']) . '</span></div></div>'
            . '</div>';

        $recipientType = $gatePass['recipient_type'] ?? 'vendor';
        $recipientTitle = $recipientType === 'internal' ? 'Recipient Details' : 'Vendor Details';
        $recipientName = $recipientType === 'internal' ? ($gatePass['recipient_name'] ?? 'N/A') : ($gatePass['vendor_name'] ?? 'N/A');

        $html .= '<div class="info-section">'
            . '<h3 style="margin-bottom:15px;color:#333;">' . $recipientTitle . '</h3>'
            . '<div class="info-row"><div class="info-label">Name:</div><div class="info-value">' . htmlspecialchars($recipientName) . '</div></div>';
        if ($recipientType !== 'internal') {
            $html .= '<div class="info-row"><div class="info-label">Contact Person:</div><div class="info-value">' . htmlspecialchars($gatePass['contact_person'] ?? 'N/A') . '</div></div>'
                . '<div class="info-row"><div class="info-label">Phone:</div><div class="info-value">' . htmlspecialchars($gatePass['vendor_phone'] ?? 'N/A') . '</div></div>'
                . '<div class="info-row"><div class="info-label">Address:</div><div class="info-value">' . htmlspecialchars($gatePass['vendor_address'] ?? 'N/A') . '</div></div>';
        }
        $html .= '</div>';

        $html .= '<div class="info-section"><h3 style="margin-bottom:15px;color:#333;">Purpose & Details</h3>'
            . '<div class="info-row"><div class="info-label">Purpose:</div><div class="info-value">' . htmlspecialchars($gatePass['purpose'] ?? '') . '</div></div>';
        if (!empty($gatePass['expected_date'])) {
            $html .= '<div class="info-row"><div class="info-label">Expected Date:</div><div class="info-value">' . date('d/m/Y H:i', strtotime($gatePass['expected_date'])) . '</div></div>';
        }
        if (!empty($gatePass['notes'])) {
            $html .= '<div class="info-row"><div class="info-label">Notes:</div><div class="info-value">' . htmlspecialchars($gatePass['notes']) . '</div></div>';
        }
        $html .= '</div>';

        if (!empty($items)) {
            $html .= '<h3 style="color:#333;">Items/Materials</h3><table class="items-table"><thead><tr><th>S.No</th><th>Item Description</th><th>Quantity</th><th>Unit</th><th>Remarks</th></tr></thead><tbody>';
            foreach ($items as $i => $it) {
                $html .= '<tr><td>' . ($i + 1) . '</td><td>' . htmlspecialchars($it['description'] ?? '') . '</td><td>' . htmlspecialchars((string) ($it['quantity'] ?? '')) . '</td><td>' . htmlspecialchars($it['unit'] ?? '') . '</td><td>' . htmlspecialchars($it['remarks'] ?? '') . '</td></tr>';
            }
            $html .= '</tbody></table>';
        }

        $html .= '<div class="signatures">'
            . '<div class="signature-box"><div class="signature-line"></div><div><strong>Prepared By</strong></div><div>' . htmlspecialchars($gatePass['created_by_name'] ?? 'System') . '</div><div>' . date('d/m/Y', strtotime($gatePass['created_at'])) . '</div></div>'
            . '<div class="signature-box"><div class="signature-line"></div><div><strong>Security Guard</strong></div><div>Name & Signature</div><div>Date: ___________</div></div>'
            . '<div class="signature-box"><div class="signature-line"></div><div><strong>Authorized By</strong></div><div>Manager Signature</div><div>Date: ___________</div></div>'
            . '</div>';

        $html .= '<div class="footer"><p><strong>Important Instructions:</strong></p><p>1. This gate pass is valid for the specified date and purpose only.</p><p>2. All items must be verified by security before entry/exit.</p><p>3. Original copy to be retained by security, duplicate with vendor.</p><p>4. Any discrepancy should be reported immediately to management.</p><br><p>Generated by Production Management System on ' . date('d/m/Y H:i:s') . '</p></div>';

        $html .= '</body></html>';
        return $html;
    }

    public function show($id)
    {
        $this->requireAuth();

        $gatePass = $this->gatePassModel->select("
            gate_passes.*,
            vendors.name as vendor_name,
            vendors.contact_person,
            vendors.phone as vendor_phone,
            users.username as created_by_name
        ")
            ->join('vendors', 'vendors.id = gate_passes.vendor_id', 'left')
            ->join('users', 'users.id = gate_passes.created_by', 'left')
            ->find($id);

        if (!$gatePass) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Gate pass not found');
        }

        $gatePass['items_decoded'] = json_decode($gatePass['items'] ?? '[]', true) ?: [];

        return view('gate_passes/show', [
            'title' => 'Gate Pass #' . ($gatePass['gate_pass_number'] ?? $gatePass['id']),
            'pass' => $gatePass,
        ]);
    }
}
    public function __construct()
    {
        $this->gatePassModel = new GatePassModel();
        $this->vendorModel = new VendorModel();
        $this->componentModel = new ComponentModel();
    }

    public function index()
    {
        if (!session()->has('user_id')) {
            return redirect()->to('/login');
        }

        $gatePasses = $this->gatePassModel->select('\n            gate_passes.*,\n            vendors.name as vendor_name,\n            vendors.contact_person,\n            users.username as created_by_name\n        ')
            ->join('vendors', 'vendors.id = gate_passes.vendor_id', 'left')
            ->join('users', 'users.id = gate_passes.created_by', 'left')
            ->orderBy('created_at', 'DESC')
            ->findAll();

        $vendors = $this->vendorModel->select('id, name, contact_person')->orderBy('name','ASC')->findAll();

        $productModel = new ProductModel();
        try {
            $products = $productModel->select('id, name, code')->orderBy('name','ASC')->findAll();
        } catch (\Throwable $e) {
            $products = [];
        }

        $stats = [
            'total_passes' => (new GatePassModel())->countAll(),
            'pending_in'   => (new GatePassModel())->where(['type' => 'incoming', 'status' => 'pending'])->countAllResults(),
            'pending_out'  => (new GatePassModel())->where(['type' => 'outgoing', 'status' => 'pending'])->countAllResults(),
            'today_passes' => (new GatePassModel())->where('DATE(created_at)', date('Y-m-d'))->countAllResults(),
        ];

        return view('gate_passes/index', [
            'title' => 'Gate Pass Management',
            'gatePasses' => $gatePasses,
            'vendors' => $vendors,
            'products' => $products,
            'stats' => $stats,
        ]);
    }

                    $stats = [
                        'total_passes' => (new GatePassModel())->countAll(),
                        'pending_in'   => (new GatePassModel())->where(['type' => 'incoming', 'status' => 'pending'])->countAllResults(),
                        'pending_out'  => (new GatePassModel())->where(['type' => 'outgoing', 'status' => 'pending'])->countAllResults(),
                        'today_passes' => (new GatePassModel())->where('DATE(created_at)', date('Y-m-d'))->countAllResults(),
                    ];

                    return view('gate_passes/index', [
                        'title' => 'Gate Pass Management',
                        'gatePasses' => $gatePasses,
                        'vendors' => $vendors,
                        'products' => $products,
                        'stats' => $stats,
                    ]);
                }

                public function create()
                {
                    if (!$this->request->isAJAX()) {
                        return redirect()->to('/gate_passes');
                    }

                    $json = $this->request->getJSON(true) ?: [];

                    $type = $json['type'] ?? '';
                    $recipientType = $json['recipient_type'] ?? 'vendor';
                    $vendorId = (int)($json['vendor_id'] ?? 0);
                    $recipientName = trim((string)($json['recipient_name'] ?? ''));
                    $itemsIn = $json['items'] ?? [];

                    if (!in_array($type, ['incoming','outgoing'], true)) {
                        return $this->response->setJSON(['success'=>false,'message'=>'Invalid type']);
                    }
                    if ($recipientType === 'vendor' && $vendorId <= 0) {
                        return $this->response->setJSON(['success'=>false,'message'=>'Select a vendor']);
                    }
                    if ($recipientType === 'internal' && $recipientName === '') {
                        return $this->response->setJSON(['success'=>false,'message'=>'Enter internal location name']);
                    }
                    if (empty($itemsIn) || !is_array($itemsIn)) {
                        return $this->response->setJSON(['success'=>false,'message'=>'Add at least one item']);
                    }

                    $productModel = new ProductModel();
                    $normalized = [];
                    foreach ($itemsIn as $row) {
                        if (!is_array($row)) { continue; }
                        $qty = (float)($row['quantity'] ?? 0);
                        if ($qty <= 0) { continue; }
                        $unit = $row['unit'] ?? 'Pcs';
                        $remarks = $row['remarks'] ?? null;
                        $productId = (int)($row['product_id'] ?? 0);
                        $description = trim((string)($row['description'] ?? ''));
                        if ($productId > 0) {
                            $prod = $productModel->select('id, name, code')->find($productId);
                            if ($prod) {
                                if ($description === '') {
                                    $description = ($prod['code'] ? ($prod['code'].' - ') : '') . $prod['name'];
                                }
                            }
                        }
                        if ($description === '' && $productId <= 0) { continue; }
                        $normalized[] = [
                            'product_id' => $productId ?: null,
                            'description' => $description,
                            'quantity' => $qty,
                            'unit' => $unit,
                            'remarks' => $remarks,
                        ];
                    }
                    if (empty($normalized)) {
                        return $this->response->setJSON(['success'=>false,'message'=>'Please add at least one valid item (description or product) with quantity']);
                    }

                    $gatePassNumber = 'GP-' . date('Ymd') . '-' . str_pad(((int)(new GatePassModel())->countAll()) + 1, 4, '0', STR_PAD_LEFT);

                    $data = [
                        'gate_pass_number' => $gatePassNumber,
                        'type' => $type,
                        'recipient_type' => $recipientType,
                        'recipient_name' => $recipientType === 'internal' ? $recipientName : null,
                        'vendor_id' => $recipientType === 'vendor' ? $vendorId : null,
                        'purpose' => $json['purpose'] ?? null,
                        'items' => json_encode($normalized),
                        'status' => 'pending',
                        'expected_date' => $json['expected_date'] ?? null,
                        'notes' => $json['notes'] ?? null,
                        'created_by' => session('user_id'),
                        'created_at' => date('Y-m-d H:i:s'),
                    ];

                    try {
                        $id = $this->gatePassModel->insert($data);
                        $gatePass = $this->gatePassModel->find($id);
                        return $this->response->setJSON(['success'=>true,'message'=>'Gate pass created successfully','gate_pass'=>$gatePass]);
                    } catch (\Throwable $e) {
                        return $this->response->setJSON(['success'=>false,'message'=>'Error creating gate pass: '.$e->getMessage()]);
                    }
                }

                public function updateStatus($id)
                {
                    if (!$this->request->isAJAX()) {
                        return redirect()->to('/gate_passes');
                    }

                    $json = $this->request->getJSON(true) ?: [];
                    $status = $json['status'] ?? '';

                    if (!in_array($status, ['approved','completed','rejected','cancelled'], true)) {
                        return $this->response->setJSON(['success'=>false,'message'=>'Invalid status']);
                    }

                    $updateData = [
                        'status' => $status,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ];
                    if ($status === 'completed') {
                        $updateData['actual_date'] = date('Y-m-d H:i:s');
                        $updateData['completed_by'] = session('user_id');
                    }
                    if (isset($json['remarks'])) {
                        $updateData['remarks'] = $json['remarks'];
                    }

                    try {
                        $this->gatePassModel->update($id, $updateData);
                        $gatePass = $this->gatePassModel->find($id);
                        return $this->response->setJSON(['success'=>true,'message'=>'Gate pass status updated','gate_pass'=>$gatePass]);
                    } catch (\Throwable $e) {
                        return $this->response->setJSON(['success'=>false,'message'=>'Error updating status: '.$e->getMessage()]);
                    }
                }

                public function generatePDF($id)
                {
                    $gatePass = $this->gatePassModel->select('\n            gate_passes.*,\n            vendors.name as vendor_name,\n            vendors.address as vendor_address,\n            vendors.contact_person,\n            vendors.phone as vendor_phone,\n            users.username as created_by_name\n        ')
                        ->join('vendors', 'vendors.id = gate_passes.vendor_id', 'left')
                        ->join('users', 'users.id = gate_passes.created_by', 'left')
                        ->find($id);

                    if (!$gatePass) {
                        throw new \CodeIgniter\Exceptions\PageNotFoundException('Gate pass not found');
                    }

                    $items = json_decode($gatePass['items'] ?? '[]', true) ?: [];
                    $html = $this->buildGatePassHTML($gatePass, $items);

                    try {
                        $dompdf = new \Dompdf\Dompdf();
                        $dompdf->loadHtml($html);
                        $dompdf->setPaper('A4', 'portrait');
                        $dompdf->render();

                        $filename = 'gate_pass_' . ($gatePass['gate_pass_number'] ?? $gatePass['id']) . '.pdf';
                        $this->response->setHeader('Content-Type', 'application/pdf');
                        $this->response->setHeader('Content-Disposition', 'inline; filename="' . $filename . '"');
                        echo $dompdf->output();
                        return;
                    } catch (\Throwable $e) {
                        $this->response->setHeader('Content-Type', 'text/html; charset=UTF-8');
                        return $html;
                    }
                }

                private function buildGatePassHTML($gatePass, $items)
                {
                    $typeLabel = ucfirst($gatePass['type']) . ' Gate Pass';
                    $html = '<!DOCTYPE html><html><head><title>Gate Pass - ' . $gatePass['gate_pass_number'] . '</title><style>body{font-family:Arial, sans-serif;margin:20px;line-height:1.4}.header{text-align:center;border-bottom:2px solid #333;padding-bottom:20px;margin-bottom:30px}.company-name{font-size:24px;font-weight:700;color:#333}.gate-pass-title{font-size:18px;color:#666;margin-top:10px}.info-section{margin-bottom:30px}.info-row{display:flex;margin-bottom:10px}.info-label{font-weight:700;width:150px}.info-value{flex:1}.items-table{width:100%;border-collapse:collapse;margin:20px 0}.items-table th,.items-table td{border:1px solid #ddd;padding:10px;text-align:left}.items-table th{background-color:#f8f9fa;font-weight:700}.status-badge{padding:5px 10px;border-radius:4px;font-weight:700;text-transform:uppercase}.signatures{margin-top:40px;display:flex;justify-content:space-between}.signature-box{text-align:center;width:200px}.signature-line{border-bottom:1px solid #333;height:40px;margin-bottom:10px}.footer{margin-top:40px;text-align:center;font-size:12px;color:#666;border-top:1px solid #ddd;padding-top:20px}.watermark{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-45deg);font-size:100px;color:#f0f0f0;z-index:-1}</style></head><body>';

                    if (($gatePass['status'] ?? '') === 'cancelled' || ($gatePass['status'] ?? '') === 'rejected') {
                        $html .= '<div class="watermark">' . strtoupper($gatePass['status']) . '</div>';
                    }

                    $html .= '<div class="header"><div class="company-name">PRODUCTION MANAGEMENT SYSTEM</div><div class="gate-pass-title">' . $typeLabel . '</div></div>';

                    $html .= '<div class="info-section">'
                        . '<div class="info-row"><div class="info-label">Gate Pass No:</div><div class="info-value"><strong>' . $gatePass['gate_pass_number'] . '</strong></div></div>'
                        . '<div class="info-row"><div class="info-label">Date:</div><div class="info-value">' . date('d/m/Y H:i', strtotime($gatePass['created_at'])) . '</div></div>'
                        . '<div class="info-row"><div class="info-label">Type:</div><div class="info-value">' . $typeLabel . '</div></div>'
                        . '<div class="info-row"><div class="info-label">Status:</div><div class="info-value"><span class="status-badge status-' . $gatePass['status'] . '">' . ucfirst($gatePass['status']) . '</span></div></div>'
                        . '</div>';

                    $recipientType = $gatePass['recipient_type'] ?? 'vendor';
                    $recipientTitle = $recipientType === 'internal' ? 'Recipient Details' : 'Vendor Details';
                    $recipientName = $recipientType === 'internal' ? ($gatePass['recipient_name'] ?? 'N/A') : ($gatePass['vendor_name'] ?? 'N/A');
                    $html .= '<div class="info-section">'
                        . '<h3 style="margin-bottom:15px;color:#333;">' . $recipientTitle . '</h3>'
                        . '<div class="info-row"><div class="info-label">Name:</div><div class="info-value">' . htmlspecialchars($recipientName) . '</div></div>';
                    if ($recipientType !== 'internal') {
                        $html .= '<div class="info-row"><div class="info-label">Contact Person:</div><div class="info-value">' . htmlspecialchars($gatePass['contact_person'] ?? 'N/A') . '</div></div>'
                            . '<div class="info-row"><div class="info-label">Phone:</div><div class="info-value">' . htmlspecialchars($gatePass['vendor_phone'] ?? 'N/A') . '</div></div>'
                            . '<div class="info-row"><div class="info-label">Address:</div><div class="info-value">' . htmlspecialchars($gatePass['vendor_address'] ?? 'N/A') . '</div></div>';
                    }
                    $html .= '</div>';

                    $html .= '<div class="info-section"><h3 style="margin-bottom:15px;color:#333;">Purpose & Details</h3>'
                        . '<div class="info-row"><div class="info-label">Purpose:</div><div class="info-value">' . htmlspecialchars($gatePass['purpose'] ?? '') . '</div></div>';
                    if (!empty($gatePass['expected_date'])) {
                        $html .= '<div class="info-row"><div class="info-label">Expected Date:</div><div class="info-value">' . date('d/m/Y H:i', strtotime($gatePass['expected_date'])) . '</div></div>';
                    }
                    if (!empty($gatePass['notes'])) {
                        $html .= '<div class="info-row"><div class="info-label">Notes:</div><div class="info-value">' . htmlspecialchars($gatePass['notes']) . '</div></div>';
                    }
                    $html .= '</div>';

                    if (!empty($items)) {
                        $html .= '<h3 style="color:#333;">Items/Materials</h3><table class="items-table"><thead><tr><th>S.No</th><th>Item Description</th><th>Quantity</th><th>Unit</th><th>Remarks</th></tr></thead><tbody>';
                        foreach ($items as $i => $it) {
                            $html .= '<tr><td>' . ($i+1) . '</td><td>' . htmlspecialchars($it['description'] ?? '') . '</td><td>' . htmlspecialchars($it['quantity'] ?? '') . '</td><td>' . htmlspecialchars($it['unit'] ?? '') . '</td><td>' . htmlspecialchars($it['remarks'] ?? '') . '</td></tr>';
                        }
                        $html .= '</tbody></table>';
                    }

                    $html .= '<div class="signatures">'
                        . '<div class="signature-box"><div class="signature-line"></div><div><strong>Prepared By</strong></div><div>' . htmlspecialchars($gatePass['created_by_name'] ?? 'System') . '</div><div>' . date('d/m/Y', strtotime($gatePass['created_at'])) . '</div></div>'
                        . '<div class="signature-box"><div class="signature-line"></div><div><strong>Security Guard</strong></div><div>Name & Signature</div><div>Date: ___________</div></div>'
                        . '<div class="signature-box"><div class="signature-line"></div><div><strong>Authorized By</strong></div><div>Manager Signature</div><div>Date: ___________</div></div>'
                        . '</div>';

                    $html .= '<div class="footer"><p><strong>Important Instructions:</strong></p><p>1. This gate pass is valid for the specified date and purpose only.</p><p>2. All items must be verified by security before entry/exit.</p><p>3. Original copy to be retained by security, duplicate with vendor.</p><p>4. Any discrepancy should be reported immediately to management.</p><br><p>Generated by Production Management System on ' . date('d/m/Y H:i:s') . '</p></div>';

                    $html .= '</body></html>';
                    return $html;
                }

                private function getStatusBadge($status)
                {
                    $badges = [
                        'pending' => '<span class="badge bg-warning">Pending</span>',
                        'approved' => '<span class="badge bg-success">Approved</span>',
                        'completed' => '<span class="badge bg-primary">Completed</span>',
                        'rejected' => '<span class="badge bg-danger">Rejected</span>',
                        'cancelled' => '<span class="badge bg-secondary">Cancelled</span>'
                    ];
                    return $badges[$status] ?? '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
                }

                public function show($id)
                {
                    if (!session()->has('user_id')) {
                        return redirect()->to('/login');
                    }

                    $gatePass = $this->gatePassModel->select('\n            gate_passes.*,\n            vendors.name as vendor_name,\n            vendors.contact_person,\n            vendors.phone as vendor_phone,\n            users.username as created_by_name\n        ')
                        ->join('vendors', 'vendors.id = gate_passes.vendor_id', 'left')
                        ->join('users', 'users.id = gate_passes.created_by', 'left')
                        ->find($id);

                    if (!$gatePass) {
                        throw new \CodeIgniter\Exceptions\PageNotFoundException('Gate pass not found');
                    }

                    $gatePass['items_decoded'] = json_decode($gatePass['items'] ?? '[]', true) ?: [];

                    return view('gate_passes/show', [
                        'title' => 'Gate Pass #' . ($gatePass['gate_pass_number'] ?? $gatePass['id']),
                        'pass' => $gatePass,
                    ]);
                }
            }
        // Recipient/Vendor
        $recipientType = $gatePass['recipient_type'] ?? 'vendor';
        $recipientTitle = $recipientType === 'internal' ? 'Recipient Details' : 'Vendor Details';
        $recipientName = $recipientType === 'internal' ? ($gatePass['recipient_name'] ?? 'N/A') : ($gatePass['vendor_name'] ?? 'N/A');
        $html .= '<div class="info-section">'
            . '<h3 style="margin-bottom:15px;color:#333;">' . $recipientTitle . '</h3>'
            . '<div class="info-row"><div class="info-label">Name:</div><div class="info-value">' . htmlspecialchars($recipientName) . '</div></div>';
        if ($recipientType !== 'internal') {
            $html .= '<div class="info-row"><div class="info-label">Contact Person:</div><div class="info-value">' . htmlspecialchars($gatePass['contact_person'] ?? 'N/A') . '</div></div>'
                . '<div class="info-row"><div class="info-label">Phone:</div><div class="info-value">' . htmlspecialchars($gatePass['vendor_phone'] ?? 'N/A') . '</div></div>'
                . '<div class="info-row"><div class="info-label">Address:</div><div class="info-value">' . htmlspecialchars($gatePass['vendor_address'] ?? 'N/A') . '</div></div>';
        }
        $html .= '</div>';

        // Purpose & details
        $html .= '<div class="info-section"><h3 style="margin-bottom:15px;color:#333;">Purpose & Details</h3>'
            . '<div class="info-row"><div class="info-label">Purpose:</div><div class="info-value">' . htmlspecialchars($gatePass['purpose'] ?? '') . '</div></div>';
        if (!empty($gatePass['expected_date'])) {
            $html .= '<div class="info-row"><div class="info-label">Expected Date:</div><div class="info-value">' . date('d/m/Y H:i', strtotime($gatePass['expected_date'])) . '</div></div>';
        }
        if (!empty($gatePass['notes'])) {
            $html .= '<div class="info-row"><div class="info-label">Notes:</div><div class="info-value">' . htmlspecialchars($gatePass['notes']) . '</div></div>';
        }
        $html .= '</div>';

        // Items table
        if (!empty($items)) {
            $html .= '<h3 style="color:#333;">Items/Materials</h3><table class="items-table"><thead><tr><th>S.No</th><th>Item Description</th><th>Quantity</th><th>Unit</th><th>Remarks</th></tr></thead><tbody>';
            foreach ($items as $i => $it) {
                $html .= '<tr><td>' . ($i+1) . '</td><td>' . htmlspecialchars($it['description'] ?? '') . '</td><td>' . htmlspecialchars($it['quantity'] ?? '') . '</td><td>' . htmlspecialchars($it['unit'] ?? '') . '</td><td>' . htmlspecialchars($it['remarks'] ?? '') . '</td></tr>';
            }
            $html .= '</tbody></table>';
        }

        // Signatures
        $html .= '<div class="signatures">'
            . '<div class="signature-box"><div class="signature-line"></div><div><strong>Prepared By</strong></div><div>' . htmlspecialchars($gatePass['created_by_name'] ?? 'System') . '</div><div>' . date('d/m/Y', strtotime($gatePass['created_at'])) . '</div></div>'
            . '<div class="signature-box"><div class="signature-line"></div><div><strong>Security Guard</strong></div><div>Name & Signature</div><div>Date: ___________</div></div>'
            . '<div class="signature-box"><div class="signature-line"></div><div><strong>Authorized By</strong></div><div>Manager Signature</div><div>Date: ___________</div></div>'
            . '</div>';

        // Footer
        $html .= '<div class="footer"><p><strong>Important Instructions:</strong></p><p>1. This gate pass is valid for the specified date and purpose only.</p><p>2. All items must be verified by security before entry/exit.</p><p>3. Original copy to be retained by security, duplicate with vendor.</p><p>4. Any discrepancy should be reported immediately to management.</p><br><p>Generated by Production Management System on ' . date('d/m/Y H:i:s') . '</p></div>';

        $html .= '</body></html>';
        return $html;
    }
            $updateData['actual_date'] = date('Y-m-d H:i:s');
            $updateData['completed_by'] = session('user_id');
        }

        if (isset($json['remarks'])) {
            $updateData['remarks'] = $json['remarks'];
        }

        try {
            $this->gatePassModel->update($id, $updateData);
            $gatePass = $this->gatePassModel->find($id);

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Gate pass status updated',
                'gate_pass' => $gatePass
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Error updating status: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Generate gate pass PDF
     */
    public function generatePDF($id)
    {
        $gatePass = $this->gatePassModel->select('
            gate_passes.*,
            vendors.name as vendor_name,
            vendors.address as vendor_address,
            vendors.contact_person,
            vendors.phone as vendor_phone,
            users.username as created_by_name
        ')
        ->join('vendors', 'vendors.id = gate_passes.vendor_id', 'left')
        ->join('users', 'users.id = gate_passes.created_by', 'left')
        ->find($id);

        if (!$gatePass) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Gate pass not found');
        }

        $items = json_decode($gatePass['items'], true);

        $html = $this->generateGatePassPDF($gatePass, $items);

        // Render real PDF using Dompdf
        try {
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $filename = 'gate_pass_' . ($gatePass['gate_pass_number'] ?? $gatePass['id']) . '.pdf';
            $this->response->setHeader('Content-Type', 'application/pdf');
            $this->response->setHeader('Content-Disposition', 'inline; filename="' . $filename . '"');
            echo $dompdf->output();
            return;
        } catch (\Throwable $e) {
            // Fallback: return HTML if PDF generation fails
            $this->response->setHeader('Content-Type', 'text/html; charset=UTF-8');
            return $html;
        }
    }

    /**
     * Generate gate pass PDF HTML
     */
    private function generateGatePassPDF($gatePass, $items)
    {
        $typeLabel = ucfirst($gatePass['type']) . ' Gate Pass';
        $statusBadge = $this->getStatusBadge($gatePass['status']);

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <title>Gate Pass - ' . $gatePass['gate_pass_number'] . '</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.4; }
                .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
                .company-name { font-size: 24px; font-weight: bold; color: #333; }
                .gate-pass-title { font-size: 18px; color: #666; margin-top: 10px; }
                .info-section { margin-bottom: 30px; }
                .info-row { display: flex; margin-bottom: 10px; }
                .info-label { font-weight: bold; width: 150px; }
                .info-value { flex: 1; }
                .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .items-table th, .items-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                .items-table th { background-color: #f8f9fa; font-weight: bold; }
                .status-badge { padding: 5px 10px; border-radius: 4px; font-weight: bold; text-transform: uppercase; }
                .status-pending { background-color: #ffc107; color: #000; }
                .status-approved { background-color: #28a745; color: #fff; }
                .status-completed { background-color: #007bff; color: #fff; }
                .status-rejected { background-color: #dc3545; color: #fff; }
                .signatures { margin-top: 40px; display: flex; justify-content: space-between; }
                .signature-box { text-align: center; width: 200px; }
                .signature-line { border-bottom: 1px solid #333; height: 40px; margin-bottom: 10px; }
                .footer { margin-top: 40px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #ddd; padding-top: 20px; }
                .watermark { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 100px; color: #f0f0f0; z-index: -1; }
            </style>
        </head>
        <body>';

        if ($gatePass['status'] === 'cancelled' || $gatePass['status'] === 'rejected') {
            $html .= '<div class="watermark">' . strtoupper($gatePass['status']) . '</div>';
        }

        $html .= '
            <div class="header">
                <div class="company-name">PRODUCTION MANAGEMENT SYSTEM</div>
                <div class="gate-pass-title">' . $typeLabel . '</div>
            </div>

            <div class="info-section">
                <div class="info-row">
                    <div class="info-label">Gate Pass No:</div>
                    <div class="info-value"><strong>' . $gatePass['gate_pass_number'] . '</strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Date:</div>
                    <div class="info-value">' . date('d/m/Y H:i', strtotime($gatePass['created_at'])) . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Type:</div>
                    <div class="info-value">' . $typeLabel . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Status:</div>
                    <div class="info-value"><span class="status-badge status-' . $gatePass['status'] . '">' . ucfirst($gatePass['status']) . '</span></div>
                </div>
            </div>

            <div class="info-section">
                <h3 style="margin-bottom: 15px; color: #333;">Vendor Details</h3>
                <div class="info-row">
                    <div class="info-label">Vendor Name:</div>
                    <div class="info-value">' . htmlspecialchars($gatePass['vendor_name']) . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Contact Person:</div>
                    <div class="info-value">' . htmlspecialchars($gatePass['contact_person'] ?? 'N/A') . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Phone:</div>
                    <div class="info-value">' . htmlspecialchars($gatePass['vendor_phone'] ?? 'N/A') . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Address:</div>
                    <div class="info-value">' . htmlspecialchars($gatePass['vendor_address'] ?? 'N/A') . '</div>
                </div>
            </div>

            <div class="info-section">
                <h3 style="margin-bottom: 15px; color: #333;">Purpose & Details</h3>
                <div class="info-row">
                    <div class="info-label">Purpose:</div>
                    <div class="info-value">' . htmlspecialchars($gatePass['purpose']) . '</div>
                </div>';

        if ($gatePass['expected_date']) {
            $html .= '
                <div class="info-row">
                    <div class="info-label">Expected Date:</div>
                    <div class="info-value">' . date('d/m/Y H:i', strtotime($gatePass['expected_date'])) . '</div>
                </div>';
        }

        if ($gatePass['notes']) {
            $html .= '
                <div class="info-row">
                    <div class="info-label">Notes:</div>
                    <div class="info-value">' . htmlspecialchars($gatePass['notes']) . '</div>
                </div>';
        }

        $html .= '</div>';

        // Items table
        if (!empty($items)) {
            $html .= '
            <h3 style="color: #333;">Items/Materials</h3>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>S.No</th>
                        <th>Item Description</th>
                        <th>Quantity</th>
                        <th>Unit</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>';

            foreach ($items as $index => $item) {
                $html .= '
                    <tr>
                        <td>' . ($index + 1) . '</td>
                        <td>' . htmlspecialchars($item['description'] ?? '') . '</td>
                        <td>' . htmlspecialchars($item['quantity'] ?? '') . '</td>
                        <td>' . htmlspecialchars($item['unit'] ?? '') . '</td>
                        <td>' . htmlspecialchars($item['remarks'] ?? '') . '</td>
                    </tr>';
            }

            $html .= '
                </tbody>
            </table>';
        }

        $html .= '
            <div class="signatures">
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div><strong>Prepared By</strong></div>
                    <div>' . htmlspecialchars($gatePass['created_by_name']) . '</div>
                    <div>' . date('d/m/Y', strtotime($gatePass['created_at'])) . '</div>
                </div>
                
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div><strong>Security Guard</strong></div>
                    <div>Name & Signature</div>
                    <div>Date: ___________</div>
                </div>
                
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div><strong>Authorized By</strong></div>
                    <div>Manager Signature</div>
                    <div>Date: ___________</div>
                </div>
            </div>

            <div class="footer">
                <p><strong>Important Instructions:</strong></p>
                <p>1. This gate pass is valid for the specified date and purpose only.</p>
                <p>2. All items must be verified by security before entry/exit.</p>
                <p>3. Original copy to be retained by security, duplicate with vendor.</p>
                <p>4. Any discrepancy should be reported immediately to management.</p>
                <br>
                <p>Generated by Production Management System on ' . date('d/m/Y H:i:s') . '</p>
            </div>
        </body>
        $recipientTitle = ($gatePass['recipient_type'] ?? 'vendor') === 'internal' ? 'Recipient Details' : 'Vendor Details';
        $recipientName = ($gatePass['recipient_type'] ?? 'vendor') === 'internal' ? ($gatePass['recipient_name'] ?? 'N/A') : ($gatePass['vendor_name'] ?? 'N/A');
        $html .= '
            <div class="info-section">
                <h3 style="margin-bottom: 15px; color: #333;">' . $recipientTitle . '</h3>
                <div class="info-row">
                    <div class="info-label">Name:</div>
            $html .= '
                if (($gatePass['status'] ?? '') === 'cancelled' || ($gatePass['status'] ?? '') === 'rejected') {
                    $html .= '<div class="watermark">' . strtoupper($gatePass['status']) . '</div>';
                }
                <div class="info-row">
                    <div class="info-label">Contact Person:</div>
                    <div class="info-value">' . htmlspecialchars($gatePass['contact_person'] ?? 'N/A') . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Phone:</div>
                    <div class="info-value">' . htmlspecialchars($gatePass['vendor_phone'] ?? 'N/A') . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Address:</div>
                    <div class="info-value">' . htmlspecialchars($gatePass['vendor_address'] ?? 'N/A') . '</div>
                </div>';
        }
        $html .= '
            </div>

    public function show($id)
    {
        if (!session()->has('user_id')) {
            return redirect()->to('/login');
        }

        $gatePass = $this->gatePassModel->select('
            gate_passes.*,
            vendors.name as vendor_name,
            vendors.contact_person,
            vendors.phone as vendor_phone,
            users.username as created_by_name
        ')
        ->join('vendors', 'vendors.id = gate_passes.vendor_id', 'left')
        ->join('users', 'users.id = gate_passes.created_by', 'left')
        ->find($id);

        if (!$gatePass) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Gate pass not found');
        }

        $gatePass['items_decoded'] = json_decode($gatePass['items'] ?? '[]', true) ?: [];

        return view('gate_passes/show', [
            'title' => 'Gate Pass #' . ($gatePass['gate_pass_number'] ?? $gatePass['id']),
            'pass' => $gatePass,
        ]);
    }
}
