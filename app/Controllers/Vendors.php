<?php

namespace App\Controllers;

use App\Models\VendorModel;
use App\Models\VendorGatepassModel;
use App\Models\ComponentModel;
use App\Models\ProcessModel;

class Vendors extends BaseController
{
    protected $vendorModel;

    public function __construct()
    {
        $this->vendorModel = new VendorModel();
    }

    /**
     * Display vendors list
     */
    public function index()
    {
        $this->requireAuth();
        $this->requirePermission('vendors.view');

        $searchTerm = $this->request->getGet('search');
        $typeFilter = $this->request->getGet('type');
        $statusFilter = $this->request->getGet('status');
        $perPage = (int) ($this->request->getGet('per_page') ?? 20);

        $vendors = $this->vendorModel->getVendorsWithFilters($searchTerm, $typeFilter, $statusFilter, $perPage);
        $vendorTypes = $this->vendorModel->getVendorTypes();

        // DEBUG: Check vendor count
        $totalVendors = $this->vendorModel->countAll();
        log_message('info', "Total vendors in database: " . $totalVendors);
        log_message('info', "Vendors returned by filter: " . count($vendors));

        // Convert pagination result to expected format
        $vendorsData = [
            'data' => $vendors,
            'pager' => $this->vendorModel->pager
        ];

        $data = $this->setPageData([
            'page_title' => 'Vendors Management',
            'vendors' => $vendorsData,
            'categories' => $vendorTypes, // Fix: use 'categories' to match the view
            'current_search' => $searchTerm,
            'current_category' => $typeFilter, // Fix: use 'current_category' to match the view
            'current_status' => $statusFilter,
            'per_page' => $perPage,
            'can_create' => $this->hasPermission('vendors.create'),
            'can_edit' => $this->hasPermission('vendors.edit'),
            'can_delete' => $this->hasPermission('vendors.delete')
        ]);

        return view('vendors/index', $data);
    }

    /**
     * Resolve vendor by public_id or numeric id. Throws 404 if not found.
     */
    private function resolveVendorOrFail($identifier): array
    {
        $vendor = $this->vendorModel->findByPublicIdOrId($identifier);
        if (!$vendor) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Vendor not found');
        }
        return $vendor;
    }

    /**
     * Redirect to canonical public_id URL if feature is enabled and identifier is not already the public_id.
     */
    private function redirectToCanonicalVendorUrl(array $vendor, $identifier, string $suffix = '')
    {
        $publicId = trim((string)($vendor['public_id'] ?? ''));
        if (!featureEnabled('enable_public_ids') || $publicId === '' || (string)$identifier === $publicId) {
            return null;
        }
        return redirect()->to(site_url('vendors/' . urlencode($publicId) . $suffix));
    }

    /**
     * Display single vendor details
     */
    public function show($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('vendors.view');

        $vendor = $this->resolveVendorOrFail($id);
        if ($redirect = $this->redirectToCanonicalVendorUrl($vendor, $id)) return $redirect;
        $numericId = (int)$vendor['id'];

        $vendor = $this->vendorModel->getVendorWithDetails($numericId);
        if (!$vendor) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Vendor not found');
        }

        // Get vendor processes
        $processModel = new ProcessModel();
        $vendorProcesses = $processModel->where('vendor_id', $numericId)->findAll();

        // Get vendor contacts from database
        $vendorContactModel = new \App\Models\VendorContactModel();
        $vendorContacts = $vendorContactModel->where('vendor_id', $numericId)
                                              ->orderBy('is_primary', 'DESC')
                                              ->orderBy('name', 'ASC')
                                              ->findAll();

        log_message('info', 'Vendors::show() - Vendor ID: ' . $numericId . ', Contacts Count: ' . count($vendorContacts ?? []));
        log_message('debug', 'Vendors::show() - Contacts: ' . json_encode($vendorContacts));

        $db = \Config\Database::connect();
        $vendorSummary = [
            'total_business' => 0.0,
            'paid_total' => 0.0,
            'pending_total' => 0.0,
            'bill_count' => 0,
            'po_count' => 0,
        ];
        $recentBills = [];
        $recentPayments = [];
        $recentPOs = [];
        try {
            if ($db->tableExists('vendor_bills')) {
                // Use real paid amounts from allocations (vendor_bills.balance is never updated after payment)
                $paidSubq = "COALESCE((SELECT SUM(COALESCE(NULLIF(vpa.amount_allocated,0), vpa.amount, 0))
                              FROM vendor_payment_allocations vpa
                              JOIN vendor_payments vp ON vp.id = vpa.payment_id AND vp.status = 'posted'
                              WHERE vpa.vendor_bill_id = vb.id), 0)";

                $s = $db->query(
                    "SELECT COUNT(*) AS bill_count,
                            COALESCE(SUM(vb.total_amount),0) AS total_business,
                            COALESCE(SUM(GREATEST(0, vb.total_amount - $paidSubq)),0) AS pending_total,
                            COALESCE(SUM($paidSubq),0) AS paid_total
                     FROM vendor_bills vb
                     WHERE vb.vendor_id = ? AND LOWER(COALESCE(vb.status,'')) <> 'cancelled'",
                    [$numericId]
                )->getRowArray();
                $vendorSummary['bill_count']     = (int)($s['bill_count']     ?? 0);
                $vendorSummary['total_business'] = (float)($s['total_business'] ?? 0);
                $vendorSummary['pending_total']  = (float)($s['pending_total']  ?? 0);
                $vendorSummary['paid_total']     = (float)($s['paid_total']     ?? 0);

                $recentBills = $db->query(
                    "SELECT vb.id, vb.bill_number, vb.bill_date, vb.total_amount, vb.status, vb.based_on,
                            $paidSubq AS paid_total,
                            GREATEST(0, vb.total_amount - $paidSubq) AS balance
                     FROM vendor_bills vb
                     WHERE vb.vendor_id = ? AND LOWER(COALESCE(vb.status,'')) <> 'cancelled'
                     ORDER BY COALESCE(vb.bill_date, vb.created_at) DESC, vb.id DESC LIMIT 5",
                    [$numericId]
                )->getResultArray();
            }

            if ($db->tableExists('vendor_payments')) {
                $recentPayments = $db->query(
                    "SELECT id,payment_date,amount,status,payment_method,memo FROM vendor_payments WHERE vendor_id = ? ORDER BY COALESCE(payment_date, created_at) DESC, id DESC LIMIT 5",
                    [$numericId]
                )->getResultArray();
            }

            if ($db->tableExists('purchase_orders')) {
                $poAgg = $db->query(
                    "SELECT COUNT(*) AS po_count FROM purchase_orders WHERE vendor_id = ?",
                    [$numericId]
                )->getRowArray();
                $vendorSummary['po_count'] = (int)($poAgg['po_count'] ?? 0);

                $recentPOs = $db->query(
                    "SELECT id,po_number,status,delivery_date,total,created_at FROM purchase_orders WHERE vendor_id = ? ORDER BY id DESC LIMIT 5",
                    [$numericId]
                )->getResultArray();
            }
        } catch (\Throwable $e) {
            log_message('error', 'Vendors::show summary load failed: ' . $e->getMessage());
        }

        $data = $this->setPageData([
            'page_title' => 'Vendor Details - ' . $vendor['name'],
            'vendor' => $vendor,
            'vendor_contacts' => $vendorContacts ?? [],
            'vendor_processes' => $vendorProcesses,
            'vendor_summary' => $vendorSummary,
            'recent_bills' => $recentBills,
            'recent_payments' => $recentPayments,
            'recent_pos' => $recentPOs,
            'can_edit' => $this->hasPermission('vendors.edit'),
            'can_delete' => $this->hasPermission('vendors.delete')
        ]);

        return view('vendors/show', $data);
    }

    /**
     * Display create vendor form
     */
    public function create()
    {
        $this->requireAuth();
        $this->requirePermission('vendors.create');

        $nextVendorCode = $this->vendorModel->peekNextVendorCode();
        $vendorPrefix = $this->vendorModel->getVendorCodePrefix();
        $nextVendorNumber = $this->vendorModel->peekNextVendorCodeNumber($vendorPrefix);
        $nextVendorCode = $vendorPrefix . '-' . $nextVendorNumber;

        $data = $this->setPageData([
            'page_title' => 'Create New Vendor',
            'vendor' => null,
            'next_vendor_code' => $nextVendorCode,
            'next_vendor_number' => $nextVendorNumber,
            'vendor_code_prefix' => $vendorPrefix,
        ]);

        return view('vendors/form', $data);
    }

    /**
     * Handle vendor creation
     */
    public function store()
    {
        $this->requireAuth();
        $this->requirePermission('vendors.create');

        $this->relaxNoAutoValueOnZero();

        try {
            $data = [
                'name' => $this->request->getPost('name'),
                'contact_person' => $this->request->getPost('contact_person'),
                'email' => $this->request->getPost('email'),
                'phone' => $this->request->getPost('phone'),
                'address' => $this->request->getPost('address'),
                'is_active' => $this->request->getPost('is_active') ? true : false
            ];

            $created = false;
            $lastError = '';
            for ($attempt = 1; $attempt <= 2; $attempt++) {
                try {
                    $data['vendor_code'] = $this->vendorModel->generateVendorCode();
                    if ($this->vendorModel->insert($data)) {
                        $created = true;
                        break;
                    }

                    $errors = $this->vendorModel->errors();
                    $lastError = strtolower(implode(' ', array_map('strval', (array)$errors)));
                    if ($attempt === 1 && (strpos($lastError, 'duplicate') !== false || strpos($lastError, 'unique') !== false || strpos($lastError, 'primary') !== false)) {
                        $this->repairVendorIdentity();
                        continue;
                    }
                    return redirect()->back()
                        ->withInput()
                        ->with('validation', $errors);
                } catch (\Throwable $e) {
                    $lastError = strtolower($e->getMessage());
                    if ($attempt === 1 && (strpos($lastError, 'duplicate') !== false || strpos($lastError, 'primary') !== false || strpos($lastError, 'vendor_code') !== false)) {
                        $this->repairVendorIdentity();
                        continue;
                    }
                    throw $e;
                }
            }

            if ($created) {
                return redirect()->to('/vendors')->with('success', 'Vendor created successfully.');
            }

            return redirect()->back()
                           ->withInput()
                           ->with('error', 'Failed to create vendor. ' . ($lastError ? ('Details: ' . $lastError) : 'Please try again.'));
        } catch (\Throwable $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to create vendor: ' . $e->getMessage());
        }
    }

    private function relaxNoAutoValueOnZero(): void
    {
        try {
            $db = \Config\Database::connect();
            $db->query("SET SESSION sql_mode = REPLACE(@@SESSION.sql_mode, 'NO_AUTO_VALUE_ON_ZERO', '')");
        } catch (\Throwable $_) {
            // best-effort
        }
    }

    private function repairVendorIdentity(): void
    {
        try {
            $db = \Config\Database::connect();
            $db->query("SET SESSION sql_mode = REPLACE(@@SESSION.sql_mode, 'NO_AUTO_VALUE_ON_ZERO', '')");
            $db->query('ALTER TABLE `vendors` MODIFY `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT');
            $row = $db->query('SELECT COALESCE(MAX(`id`), 0) + 1 AS next_id FROM `vendors`')->getRowArray();
            $nextId = (int)($row['next_id'] ?? 1);
            if ($nextId < 1) {
                $nextId = 1;
            }
            $db->query('ALTER TABLE `vendors` AUTO_INCREMENT = ' . $nextId);
        } catch (\Throwable $_) {
            // best-effort
        }
    }

    /**
     * Display edit vendor form
     */
    public function edit($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('vendors.edit');

        $vendor = $this->resolveVendorOrFail($id);
        if ($redirect = $this->redirectToCanonicalVendorUrl($vendor, $id, '/edit')) return $redirect;

        $data = $this->setPageData([
            'page_title' => 'Edit Vendor - ' . $vendor['name'],
            'vendor' => $vendor
        ]);

        return view('vendors/form', $data);
    }

    /**
     * Handle vendor update
     */
    public function update($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('vendors.edit');

        $vendor = $this->resolveVendorOrFail($id);
        $numericId = (int)$vendor['id'];

        $data = [
            'name' => $this->request->getPost('name'),
            'contact_person' => $this->request->getPost('contact_person'),
            'email' => $this->request->getPost('email'),
            'phone' => $this->request->getPost('phone'),
            'address' => $this->request->getPost('address'),
            'is_active' => $this->request->getPost('is_active') ? true : false
        ];

        if ($this->vendorModel->update($numericId, $data)) {
            return redirect()->to('/vendors')->with('success', 'Vendor updated successfully.');
        } else {
            return redirect()->back()
                           ->withInput()
                           ->with('validation', $this->vendorModel->errors());
        }
    }

    /**
     * Delete vendor
     */
    public function delete($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('vendors.delete');

        $vendor = $this->vendorModel->findByPublicIdOrId($id);
        if (!$vendor) {
            return $this->jsonResponse(['success' => false, 'message' => 'Vendor not found'], 404);
        }
        $numericId = (int)$vendor['id'];

        // Check if vendor has associated processes or gatepasses
        $processModel = new ProcessModel();
        $gatepassModel = new VendorGatepassModel();
        
        $hasProcesses = $processModel->where('vendor_id', $numericId)->countAllResults() > 0;
        $hasGatepasses = $gatepassModel->where('vendor_id', $numericId)->countAllResults() > 0;

        if ($hasProcesses || $hasGatepasses) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Cannot delete vendor with associated processes or gatepasses. Consider deactivating instead.'
            ], 400);
        }

        if ($this->vendorModel->delete($numericId)) {
            return $this->jsonResponse(['success' => true, 'message' => 'Vendor deleted successfully.']);
        } else {
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to delete vendor.'], 500);
        }
    }

    /**
     * JSON search endpoint for vendors (used by purchase UI dropdown autocomplete).
     * Query param: q (search term). Returns minimal vendor list.
     */
    public function search()
    {
        // Require authentication for API access
        $this->requireAuth();

        $q = trim((string) ($this->request->getGet('q') ?? ''));
        if ($q === '') {
            return $this->response->setJSON(['success' => true, 'data' => []]);
        }

        // If numeric query, attempt exact id match too
        $results = $this->vendorModel->searchVendors($q);
        if (is_numeric($q)) {
            $byId = $this->vendorModel->find((int)$q);
            if ($byId) {
                // ensure uniqueness (id may already be in results)
                $found = false;
                foreach ($results as $r) { if (isset($r['id']) && $r['id'] == $byId['id']) { $found = true; break; } }
                if (!$found) array_unshift($results, $byId);
            }
        }

        // Map to minimal output
        $out = [];
        foreach ($results as $v) {
            $out[] = [ 'id' => $v['id'], 'vendor_code' => $v['vendor_code'] ?? '', 'name' => $v['name'], 'contact_person' => $v['contact_person'] ?? '' ];
        }

        return $this->response->setJSON(['success' => true, 'data' => $out]);
    }

    /**
     * Toggle vendor status (active/inactive)
     */
    public function toggleStatus($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('vendors.edit');

        $vendor = $this->vendorModel->findByPublicIdOrId($id);
        if (!$vendor) {
            return $this->jsonResponse(['success' => false, 'message' => 'Vendor not found'], 404);
        }
        $numericId = (int)$vendor['id'];

        $newStatus = !$vendor['is_active'];
        $data = [
            'is_active' => $newStatus,
            'updated_by' => $this->currentUser['id']
        ];

        if ($this->vendorModel->update($numericId, $data)) {
            $statusText = $newStatus ? 'activated' : 'deactivated';
            return $this->jsonResponse([
                'success' => true,
                'message' => "Vendor {$statusText} successfully.",
                'new_status' => $newStatus
            ]);
        } else {
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to update vendor status.'], 500);
        }
    }

    /**
     * Vendor gatepasses management
     */
    public function gatepasses($vendorId = null)
    {
        $this->requireAuth();
        $this->requirePermission('gatepasses.view');

        $vendor = $this->vendorModel->find($vendorId);
        if (!$vendor) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Vendor not found');
        }

        $gatepassModel = new VendorGatepassModel();
        $gatepasses = $gatepassModel->getGatepassesForVendor($vendorId);

        $data = $this->setPageData([
            'page_title' => 'Vendor Gatepasses - ' . $vendor['name'],
            'vendor' => $vendor,
            'gatepasses' => $gatepasses,
            'can_create' => $this->hasPermission('gatepasses.create'),
            'can_edit' => $this->hasPermission('gatepasses.edit'),
            'can_approve' => $this->hasPermission('gatepasses.approve')
        ]);

        return view('vendors/gatepasses', $data);
    }

    /**
     * Create vendor gatepass
     */
    public function createGatepass($vendorId = null)
    {
        $this->requireAuth();
        $this->requirePermission('gatepasses.create');

        $vendor = $this->vendorModel->find($vendorId);
        if (!$vendor) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Vendor not found');
        }

        $componentModel = new ComponentModel();
        $components = $componentModel->where('is_active', true)->findAll();

        $data = $this->setPageData([
            'page_title' => 'Create Gatepass - ' . $vendor['name'],
            'vendor' => $vendor,
            'components' => $components,
            'gatepass' => null,
            'validation' => $this->validation
        ]);

        return view('vendors/gatepass_form', $data);
    }

    /**
     * Store vendor gatepass
     */
    public function storeGatepass($vendorId = null)
    {
        $this->requireAuth();
        $this->requirePermission('gatepasses.create');

        $vendor = $this->vendorModel->find($vendorId);
        if (!$vendor) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Vendor not found');
        }

        $gatepassModel = new VendorGatepassModel();
        
        $data = [
            'vendor_id' => $vendorId,
            'gatepass_number' => $this->generateGatepassNumber(),
            'type' => $this->request->getPost('type'),
            'purpose' => $this->request->getPost('purpose'),
            'vehicle_number' => $this->request->getPost('vehicle_number'),
            'driver_name' => $this->request->getPost('driver_name'),
            'driver_contact' => $this->request->getPost('driver_contact'),
            'entry_date' => $this->request->getPost('entry_date'),
            'expected_exit_date' => $this->request->getPost('expected_exit_date'),
            'items_carried' => $this->request->getPost('items_carried'),
            'remarks' => $this->request->getPost('remarks'),
            'status' => 'pending',
            'created_by' => $this->currentUser['id']
        ];

        // Handle JSON fields
        if ($this->request->getPost('items_carried')) {
            $data['items_carried'] = json_encode($this->parseJsonField($this->request->getPost('items_carried')));
        }

        if ($gatepassModel->save($data)) {
            return redirect()->to("/vendors/{$vendorId}/gatepasses")
                           ->with('success', 'Gatepass created successfully.');
        } else {
            return redirect()->back()
                           ->withInput()
                           ->with('validation', $gatepassModel->errors());
        }
    }

    /**
     * Approve/reject gatepass
     */
    public function approveGatepass($gatepassId = null)
    {
        $this->requireAuth();
        $this->requirePermission('gatepasses.approve');

        $gatepassModel = new VendorGatepassModel();
        $gatepass = $gatepassModel->find($gatepassId);

        if (!$gatepass) {
            return $this->jsonResponse(['success' => false, 'message' => 'Gatepass not found'], 404);
        }

        if ($gatepass['status'] !== 'pending') {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Can only approve/reject pending gatepasses'
            ], 400);
        }

        $action = $this->request->getPost('action'); // 'approve' or 'reject'
        $remarks = $this->request->getPost('remarks');

        $updateData = [
            'status' => $action === 'approve' ? 'approved' : 'rejected',
            'approved_by' => $this->currentUser['id'],
            'approved_at' => date('Y-m-d H:i:s'),
            'approval_remarks' => $remarks,
            'updated_by' => $this->currentUser['id']
        ];

        if ($gatepassModel->update($gatepassId, $updateData)) {
            $message = $action === 'approve' ? 'Gatepass approved successfully' : 'Gatepass rejected successfully';
            return $this->jsonResponse(['success' => true, 'message' => $message]);
        } else {
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to update gatepass'], 500);
        }
    }

    /**
     * Export vendors to CSV
     */
    public function exportCsv()
    {
        $this->requireAuth();
        $this->requirePermission('vendors.view');

        $vendors = $this->vendorModel->getVendorsWithDetails();
        
        $filename = 'vendors_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'Vendor Code', 'Name', 'Type', 'Contact Person', 'Email', 
            'Phone', 'City', 'State', 'Country', 'Rating', 'Status', 'Created Date'
        ]);
        
        // CSV data
        foreach ($vendors as $vendor) {
            fputcsv($output, [
                $vendor['vendor_code'],
                $vendor['name'],
                $vendor['type'],
                $vendor['contact_person'],
                $vendor['email'],
                $vendor['phone'],
                $vendor['city'],
                $vendor['state'],
                $vendor['country'],
                $vendor['rating'],
                $vendor['is_active'] ? 'Active' : 'Inactive',
                $vendor['created_at']
            ]);
        }
        
        fclose($output);
        exit;
    }

    /**
     * Get vendor data for AJAX requests
     */
    public function getData()
    {
        $this->requireAuth();
        $this->requirePermission('vendors.view');

        $action = $this->request->getGet('action');
        
        switch ($action) {
            case 'types':
                $types = $this->vendorModel->getVendorTypes();
                return $this->jsonResponse($types);
                
            case 'search':
                $term = $this->request->getGet('term');
                $vendors = $this->vendorModel->like('name', $term)
                                           ->orLike('vendor_code', $term)
                                           ->where('is_active', true)
                                           ->select('id, name, vendor_code, type')
                                           ->limit(10)
                                           ->findAll();
                return $this->jsonResponse($vendors);
                
            case 'details':
                $id = $this->request->getGet('id');
                $vendor = $this->vendorModel->getVendorWithDetails($id);
                return $this->jsonResponse($vendor);
                
            case 'statistics':
                $id = $this->request->getGet('id');
                $stats = $this->vendorModel->getVendorStatistics($id);
                return $this->jsonResponse($stats);
                
            default:
                return $this->jsonResponse(['error' => 'Invalid action'], 400);
        }
    }

    /**
     * Vendor performance report
     */
    public function performance($vendorId = null)
    {
        $this->requireAuth();
        $this->requirePermission('vendors.view');

        $vendor = $this->vendorModel->find($vendorId);
        if (!$vendor) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Vendor not found');
        }

        $fromDate = $this->request->getGet('from_date') ?? date('Y-m-01');
        $toDate = $this->request->getGet('to_date') ?? date('Y-m-d');

        $performance = $this->vendorModel->getVendorPerformance($vendorId, $fromDate, $toDate);

        $data = $this->setPageData([
            'page_title' => 'Vendor Performance - ' . $vendor['name'],
            'vendor' => $vendor,
            'performance' => $performance,
            'from_date' => $fromDate,
            'to_date' => $toDate
        ]);

        return view('vendors/performance', $data);
    }

    /**
     * Add a contact for a vendor (AJAX)
     */
    public function addContact($vendorId = null)
    {
        $this->requireAuth();
        $this->requirePermission('vendors.edit');

        $isAjax = $this->request->isAJAX();

        $vendor = $this->vendorModel->findByPublicIdOrId($vendorId);
        if (!$vendor) {
            return $this->jsonResponse(['success' => false, 'message' => 'Vendor not found'], 404);
        }
        $numericVendorId = (int)$vendor['id'];

        $data = [
            'vendor_id' => $numericVendorId,
            'name' => $this->request->getPost('name'),
            'phone' => $this->request->getPost('phone'),
            'cnic' => $this->request->getPost('cnic'),
            'email' => $this->request->getPost('email'),
            'designation' => $this->request->getPost('designation'),
            'is_primary' => $this->request->getPost('is_primary') ? 1 : 0,
        ];

        $vcModel = new \App\Models\VendorContactModel();
        // Log incoming data for debugging
        log_message('debug', 'Vendors::addContact input: ' . json_encode($data));
        try {
            $id = $vcModel->insert($data, true);
            if ($id === false || $id === null) {
                // insertion failed; capture model errors
                $errors = $vcModel->errors();
                log_message('error', 'Vendor contact insert failed: ' . json_encode($errors));
                if ($isAjax) {
                    return $this->jsonResponse(['success' => false, 'message' => 'Failed to add contact', 'errors' => $errors], 400);
                }
                return redirect()->back()->with('error', 'Failed to add contact');
            }

            $contact = $vcModel->find($id);
            log_message('debug', 'Vendors::addContact inserted id=' . $id);
            if ($isAjax) {
                return $this->jsonResponse(['success' => true, 'message' => 'Contact added', 'data' => $contact]);
            }
            // Non-AJAX fallback: redirect back with flash
            return redirect()->to('/vendors/' . $vendorId)->with('success', 'Contact added successfully');
        } catch (\Throwable $e) {
            log_message('error', 'Failed to add vendor contact exception: ' . $e->getMessage());
            if ($isAjax) {
                return $this->jsonResponse(['success' => false, 'message' => 'Failed to add contact', 'exception' => $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'Failed to add contact');
        }
    }

    /**
     * Update an existing vendor contact (AJAX)
     */
    public function updateContact($vendorId = null, $contactId = null)
    {
        log_message('debug', 'Vendors::updateContact CALLED: vendorId=' . $vendorId . ', contactId=' . $contactId);
        $this->requireAuth();
        $this->requirePermission('vendors.edit');

        $isAjax = $this->request->isAJAX();
        log_message('debug', 'updateContact isAJAX=' . ($isAjax ? 'true' : 'false'));

        $vendor = $this->vendorModel->findByPublicIdOrId($vendorId);
        if (!$vendor) {
            return $this->jsonResponse(['success' => false, 'message' => 'Vendor not found'], 404);
        }
        $numericVendorId = (int)$vendor['id'];

        $vcModel = new \App\Models\VendorContactModel();
        $contact = $vcModel->find($contactId);
        if (!$contact || (int)$contact['vendor_id'] !== $numericVendorId) {
            return $this->jsonResponse(['success' => false, 'message' => 'Contact not found for this vendor'], 404);
        }

        $data = [
            'name' => $this->request->getPost('name'),
            'phone' => $this->request->getPost('phone'),
            'cnic' => $this->request->getPost('cnic'),
            'email' => $this->request->getPost('email'),
            'designation' => $this->request->getPost('designation'),
            'is_primary' => $this->request->getPost('is_primary') ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Basic validation
        if (empty($data['name'])) {
            if ($isAjax) return $this->jsonResponse(['success' => false, 'message' => 'Name is required', 'errors' => ['name' => 'Name is required']], 400);
            return redirect()->back()->with('error', 'Name is required');
        }

        try {
            if ($vcModel->update($contactId, $data) === false) {
                $errors = $vcModel->errors();
                log_message('error', 'Vendor contact update failed: ' . json_encode($errors));
                if ($isAjax) return $this->jsonResponse(['success' => false, 'message' => 'Failed to update contact', 'errors' => $errors], 400);
                return redirect()->back()->with('error', 'Failed to update contact');
            }

            $updated = $vcModel->find($contactId);
            if ($isAjax) return $this->jsonResponse(['success' => true, 'message' => 'Contact updated', 'data' => $updated]);
            return redirect()->to('/vendors/' . entityRouteIdentifier($vendor))->with('success', 'Contact updated');
        } catch (\Throwable $e) {
            log_message('error', 'Failed to update vendor contact exception: ' . $e->getMessage());
            if ($isAjax) return $this->jsonResponse(['success' => false, 'message' => 'Failed to update contact', 'exception' => $e->getMessage()], 500);
            return redirect()->back()->with('error', 'Failed to update contact');
        }
    }

    /**
     * Delete a vendor contact (AJAX)
     */
    public function deleteContact($vendorId = null, $contactId = null)
    {
        log_message('debug', 'Vendors::deleteContact CALLED: vendorId=' . $vendorId . ', contactId=' . $contactId);
        $this->requireAuth();
        $this->requirePermission('vendors.edit');

        $isAjax = $this->request->isAJAX();
        log_message('debug', 'deleteContact isAJAX=' . ($isAjax ? 'true' : 'false'));

        $isAjax = $this->request->isAJAX();

        $vendor = $this->vendorModel->findByPublicIdOrId($vendorId);
        if (!$vendor) {
            return $this->jsonResponse(['success' => false, 'message' => 'Vendor not found'], 404);
        }
        $numericVendorId = (int)$vendor['id'];

        $vcModel = new \App\Models\VendorContactModel();
        $contact = $vcModel->find($contactId);
        if (!$contact || (int)$contact['vendor_id'] !== $numericVendorId) {
            return $this->jsonResponse(['success' => false, 'message' => 'Contact not found for this vendor'], 404);
        }

        try {
            if ($vcModel->where('id', $contactId)->delete()) {
                return $this->jsonResponse(['success' => true, 'message' => 'Contact deleted']);
            }
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to delete contact'], 500);
        } catch (\Throwable $e) {
            log_message('error', 'Failed to delete vendor contact exception: ' . $e->getMessage());
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to delete contact', 'exception' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate unique gatepass number
     */
    private function generateGatepassNumber(): string
    {
        $prefix = 'GP';
        $date = date('Ymd');
        
        $gatepassModel = new VendorGatepassModel();
        $lastGatepass = $gatepassModel->like('gatepass_number', $prefix . $date)
                                    ->orderBy('gatepass_number', 'DESC')
                                    ->first();
        
        if ($lastGatepass) {
            $lastNumber = substr($lastGatepass['gatepass_number'], -3);
            $nextNumber = str_pad((int)$lastNumber + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $nextNumber = '001';
        }
        
        return $prefix . $date . $nextNumber;
    }

    /**
     * Parse JSON field from form input
     */
    private function parseJsonField($input): array
    {
        if (is_string($input)) {
            // Try to parse as JSON first
            $decoded = json_decode($input, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
            
            // If not JSON, treat as list separated by newlines
            $lines = explode("\n", $input);
            $result = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line)) {
                    $result[] = $line;
                }
            }
            return $result;
        }
        
        return is_array($input) ? $input : [];
    }
}

