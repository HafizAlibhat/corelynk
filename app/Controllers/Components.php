<?php

namespace App\Controllers;

use App\Models\ComponentModel;
use App\Models\VendorModel;
use App\Models\ComponentUsageModel;

class Components extends BaseController
{
    protected $componentModel;

    public function __construct()
    {
        $this->componentModel = new ComponentModel();
    }

    /**
     * Display components list
     */
    public function index()
    {
        $this->requireAuth();
        $this->requirePermission('components.view');

        $searchTerm = $this->request->getGet('search');
        $stockFilter = $this->request->getGet('stock_status');
        $statusFilter = $this->request->getGet('status');
        $perPage = (int) ($this->request->getGet('per_page') ?? 20);

        $components = $this->componentModel->getComponentsWithFilters(
            $searchTerm, $stockFilter, $statusFilter, $perPage
        );

        $data = $this->setPageData([
            'page_title' => 'Components & Inventory',
            'components' => $components,
            'pager' => $this->componentModel->pager,
            'current_search' => $searchTerm,
            'current_stock_status' => $stockFilter,
            'current_status' => $statusFilter,
            'per_page' => $perPage,
            'can_create' => $this->hasPermission('components.create'),
            'can_edit' => $this->hasPermission('components.edit'),
            'can_delete' => $this->hasPermission('components.delete'),
            'can_adjust_stock' => $this->hasPermission('inventory.adjust')
        ]);

        return view('components/index', $data);
    }

    /**
     * Display single component details
     */
    public function show($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('components.view');

        $component = $this->componentModel->getComponentWithDetails($id);
        if (!$component) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Component not found');
        }

        // Get component usage history
        $componentUsageModel = new ComponentUsageModel();
        $usageHistory = $componentUsageModel->getUsageHistoryForComponent($id, 20);

        // Get stock movement history (would need stock transaction model)
        $stockMovements = []; // Placeholder for stock movements

        $data = $this->setPageData([
            'page_title' => 'Component Details - ' . $component['name'],
            'component' => $component,
            'usage_history' => $usageHistory,
            'stock_movements' => $stockMovements,
            'can_edit' => $this->hasPermission('components.edit'),
            'can_delete' => $this->hasPermission('components.delete'),
            'can_adjust_stock' => $this->hasPermission('inventory.adjust')
        ]);

        return view('components/show', $data);
    }

    /**
     * Display create component form
     */
    public function create()
    {
        $this->requireAuth();
        $this->requirePermission('components.create');

        $vendorModel = new VendorModel();
        $vendors = $vendorModel->where('is_active', true)->findAll();

        $data = $this->setPageData([
            'page_title' => 'Create New Component',
            'component' => null,
            'vendors' => $vendors,
            'validation' => $this->validation
        ]);

        return view('components/form', $data);
    }

    /**
     * Handle component creation
     */
    public function store()
    {
        $this->requireAuth();
        $this->requirePermission('components.create');

        $data = [
            'name' => $this->request->getPost('name'),
            'part_number' => $this->request->getPost('part_number'),
            'description' => $this->request->getPost('description'),
            'category' => $this->request->getPost('category'),
            'specifications' => $this->request->getPost('specifications'),
            'vendor_id' => $this->request->getPost('vendor_id'),
            'unit_cost' => $this->request->getPost('unit_cost'),
            'uom' => $this->request->getPost('uom'),
            'current_stock' => $this->request->getPost('current_stock') ?? 0,
            'minimum_stock' => $this->request->getPost('minimum_stock') ?? 0,
            'maximum_stock' => $this->request->getPost('maximum_stock') ?? 0,
            'reorder_point' => $this->request->getPost('reorder_point') ?? 0,
            'lead_time_days' => $this->request->getPost('lead_time_days') ?? 0,
            'storage_location' => $this->request->getPost('storage_location'),
            'is_active' => $this->request->getPost('is_active') ? true : false,
            'created_by' => $this->currentUser['id']
        ];

        // Handle JSON fields
        if ($this->request->getPost('specifications')) {
            $data['specifications'] = json_encode($this->parseJsonField($this->request->getPost('specifications')));
        }

        if ($this->componentModel->save($data)) {
            return redirect()->to('/components')->with('success', 'Component created successfully.');
        } else {
            return redirect()->back()
                           ->withInput()
                           ->with('validation', $this->componentModel->errors());
        }
    }

    /**
     * Display edit component form
     */
    public function edit($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('components.edit');

        $component = $this->componentModel->find($id);
        if (!$component) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Component not found');
        }

        $vendorModel = new VendorModel();
        $vendors = $vendorModel->where('is_active', true)->findAll();

        $data = $this->setPageData([
            'page_title' => 'Edit Component - ' . $component['name'],
            'component' => $component,
            'vendors' => $vendors,
            'validation' => $this->validation
        ]);

        return view('components/form', $data);
    }

    /**
     * Handle component update
     */
    public function update($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('components.edit');

        $component = $this->componentModel->find($id);
        if (!$component) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Component not found');
        }

        $data = [
            'name' => $this->request->getPost('name'),
            'part_number' => $this->request->getPost('part_number'),
            'description' => $this->request->getPost('description'),
            'category' => $this->request->getPost('category'),
            'specifications' => $this->request->getPost('specifications'),
            'vendor_id' => $this->request->getPost('vendor_id'),
            'unit_cost' => $this->request->getPost('unit_cost'),
            'uom' => $this->request->getPost('uom'),
            'minimum_stock' => $this->request->getPost('minimum_stock'),
            'maximum_stock' => $this->request->getPost('maximum_stock'),
            'reorder_point' => $this->request->getPost('reorder_point'),
            'lead_time_days' => $this->request->getPost('lead_time_days'),
            'storage_location' => $this->request->getPost('storage_location'),
            'is_active' => $this->request->getPost('is_active') ? true : false,
            'updated_by' => $this->currentUser['id']
        ];

        // Handle JSON fields
        if ($this->request->getPost('specifications')) {
            $data['specifications'] = json_encode($this->parseJsonField($this->request->getPost('specifications')));
        }

        // Don't update current_stock through this form - use stock adjustment
        unset($data['current_stock']);

        if ($this->componentModel->update($id, $data)) {
            return redirect()->to('/components')->with('success', 'Component updated successfully.');
        } else {
            return redirect()->back()
                           ->withInput()
                           ->with('validation', $this->componentModel->errors());
        }
    }

    /**
     * Delete component
     */
    public function delete($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('components.delete');

        $component = $this->componentModel->find($id);
        if (!$component) {
            return $this->jsonResponse(['success' => false, 'message' => 'Component not found'], 404);
        }

        // Check if component is used in any work orders or BOMs
        $componentUsageModel = new ComponentUsageModel();
        $hasUsage = $componentUsageModel->where('component_id', $id)->countAllResults() > 0;

        if ($hasUsage) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Cannot delete component with usage history. Consider deactivating instead.'
            ], 400);
        }

        if ($this->componentModel->delete($id)) {
            return $this->jsonResponse(['success' => true, 'message' => 'Component deleted successfully.']);
        } else {
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to delete component.'], 500);
        }
    }

    /**
     * Toggle component status (active/inactive)
     */
    public function toggleStatus($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('components.edit');

        $component = $this->componentModel->find($id);
        if (!$component) {
            return $this->jsonResponse(['success' => false, 'message' => 'Component not found'], 404);
        }

        $newStatus = !$component['is_active'];
        $data = [
            'is_active' => $newStatus,
            'updated_by' => $this->currentUser['id']
        ];

        if ($this->componentModel->update($id, $data)) {
            $statusText = $newStatus ? 'activated' : 'deactivated';
            return $this->jsonResponse([
                'success' => true,
                'message' => "Component {$statusText} successfully.",
                'new_status' => $newStatus
            ]);
        } else {
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to update component status.'], 500);
        }
    }

    /**
     * Display stock adjustment form
     */
    public function adjustStock($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('inventory.adjust');

        $component = $this->componentModel->find($id);
        if (!$component) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Component not found');
        }

        $data = $this->setPageData([
            'page_title' => 'Adjust Stock - ' . $component['name'],
            'component' => $component,
            'validation' => $this->validation
        ]);

        return view('components/adjust_stock', $data);
    }

    /**
     * Handle stock adjustment
     */
    public function updateStock($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('inventory.adjust');

        $component = $this->componentModel->find($id);
        if (!$component) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Component not found');
        }

        $adjustmentType = $this->request->getPost('adjustment_type'); // 'add', 'subtract', 'set'
        $quantity = (float) $this->request->getPost('quantity');
        $reason = $this->request->getPost('reason');

        if ($quantity <= 0) {
            return redirect()->back()
                           ->withInput()
                           ->with('error', 'Quantity must be greater than 0.');
        }

        $currentStock = $component['current_stock'];
        $newStock = $currentStock;

        switch ($adjustmentType) {
            case 'add':
                $newStock = $currentStock + $quantity;
                break;
            case 'subtract':
                $newStock = $currentStock - $quantity;
                if ($newStock < 0) {
                    return redirect()->back()
                                   ->withInput()
                                   ->with('error', 'Cannot subtract more than current stock.');
                }
                break;
            case 'set':
                $newStock = $quantity;
                break;
        }

        // Update component stock
        $updateData = [
            'current_stock' => $newStock,
            'updated_by' => $this->currentUser['id']
        ];

        if ($this->componentModel->update($id, $updateData)) {
            // Log stock transaction (would need stock transaction model)
            // $this->logStockTransaction($id, $adjustmentType, $quantity, $reason);

            return redirect()->to('/components/' . $id)
                           ->with('success', 'Stock adjusted successfully.');
        } else {
            return redirect()->back()
                           ->withInput()
                           ->with('error', 'Failed to adjust stock.');
        }
    }

    /**
     * Low stock alert
     */
    public function lowStock()
    {
        $this->requireAuth();
        $this->requirePermission('components.view');

        $lowStockComponents = $this->componentModel->getLowStockComponents();

        $data = $this->setPageData([
            'page_title' => 'Low Stock Components',
            'components' => $lowStockComponents,
            'can_adjust_stock' => $this->hasPermission('inventory.adjust')
        ]);

        return view('components/low_stock', $data);
    }

    /**
     * Stock valuation report
     */
    public function valuation()
    {
        $this->requireAuth();
        $this->requirePermission('components.view');

        $components = $this->componentModel->getComponentsWithStockValue();
        $totalValue = $this->componentModel->getTotalStockValue();

        // Group by category for analysis
        $categoryAnalysis = [];
        foreach ($components as $component) {
            $category = $component['category'] ?? 'Uncategorized';
            if (!isset($categoryAnalysis[$category])) {
                $categoryAnalysis[$category] = [
                    'count' => 0,
                    'total_value' => 0
                ];
            }
            $categoryAnalysis[$category]['count']++;
            $categoryAnalysis[$category]['total_value'] += $component['stock_value'];
        }

        $data = $this->setPageData([
            'page_title' => 'Stock Valuation Report',
            'components' => $components,
            'total_value' => $totalValue,
            'category_analysis' => $categoryAnalysis
        ]);

        return view('components/valuation', $data);
    }

    /**
     * Export components to CSV
     */
    public function exportCsv()
    {
        $this->requireAuth();
        $this->requirePermission('components.view');

        $components = $this->componentModel->getComponentsWithDetails();
        
        $filename = 'components_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'Part Number', 'Name', 'Category', 'Description', 'Vendor', 
            'Unit Cost', 'UOM', 'Current Stock', 'Minimum Stock', 
            'Maximum Stock', 'Reorder Point', 'Stock Value', 'Status'
        ]);
        
        // CSV data
        foreach ($components as $component) {
            $stockValue = $component['current_stock'] * $component['unit_cost'];
            fputcsv($output, [
                $component['part_number'],
                $component['name'],
                $component['category'],
                $component['description'],
                $component['vendor_name'] ?? '',
                $component['unit_cost'],
                $component['uom'],
                $component['current_stock'],
                $component['minimum_stock'],
                $component['maximum_stock'],
                $component['reorder_point'],
                number_format($stockValue, 2),
                $component['is_active'] ? 'Active' : 'Inactive'
            ]);
        }
        
        fclose($output);
        exit;
    }

    /**
     * Get component data for AJAX requests
     */
    public function getData()
    {
        $this->requireAuth();
        $this->requirePermission('components.view');

        $action = $this->request->getGet('action');
        
        switch ($action) {
            case 'categories':
                $categories = $this->componentModel->getComponentCategories();
                return $this->jsonResponse($categories);
                
            case 'search':
                $term = $this->request->getGet('term');
                $components = $this->componentModel->like('name', $term)
                                                 ->orLike('part_number', $term)
                                                 ->where('is_active', true)
                                                 ->select('id, name, part_number, current_stock, uom')
                                                 ->limit(10)
                                                 ->findAll();
                return $this->jsonResponse($components);
                
            case 'details':
                $id = $this->request->getGet('id');
                $component = $this->componentModel->getComponentWithDetails($id);
                return $this->jsonResponse($component);
                
            case 'stock_status':
                $components = $this->componentModel->getComponentsWithStockStatus();
                $statusCounts = [
                    'ok' => 0,
                    'warning' => 0,
                    'low_stock' => 0
                ];
                
                foreach ($components as $component) {
                    switch ($component['stock_status']) {
                        case 'Low Stock':
                            $statusCounts['low_stock']++;
                            break;
                        case 'Warning':
                            $statusCounts['warning']++;
                            break;
                        default:
                            $statusCounts['ok']++;
                            break;
                    }
                }
                
                return $this->jsonResponse($statusCounts);
                
            default:
                return $this->jsonResponse(['error' => 'Invalid action'], 400);
        }
    }

    /**
     * Bulk operations
     */
    public function bulkUpdate()
    {
        $this->requireAuth();
        $this->requirePermission('components.edit');

        $operation = $this->request->getPost('operation');
        $componentIds = $this->request->getPost('component_ids');

        if (empty($componentIds) || !is_array($componentIds)) {
            return $this->jsonResponse(['success' => false, 'message' => 'No components selected'], 400);
        }

        $successCount = 0;
        $errorCount = 0;

        foreach ($componentIds as $componentId) {
            $component = $this->componentModel->find($componentId);
            if (!$component) {
                $errorCount++;
                continue;
            }

            $updateData = ['updated_by' => $this->currentUser['id']];

            switch ($operation) {
                case 'activate':
                    $updateData['is_active'] = true;
                    break;
                case 'deactivate':
                    $updateData['is_active'] = false;
                    break;
                case 'reset_reserved':
                    $updateData['reserved_stock'] = 0;
                    break;
                default:
                    $errorCount++;
                    continue 2;
            }

            if ($this->componentModel->update($componentId, $updateData)) {
                $successCount++;
            } else {
                $errorCount++;
            }
        }

        $message = "Operation completed. {$successCount} components updated successfully";
        if ($errorCount > 0) {
            $message .= ", {$errorCount} failed";
        }

        return $this->jsonResponse([
            'success' => $successCount > 0,
            'message' => $message,
            'success_count' => $successCount,
            'error_count' => $errorCount
        ]);
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
            
            // If not JSON, treat as key-value pairs separated by newlines
            $lines = explode("\n", $input);
            $result = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                if (strpos($line, ':') !== false) {
                    list($key, $value) = explode(':', $line, 2);
                    $result[trim($key)] = trim($value);
                } else {
                    $result[] = $line;
                }
            }
            return $result;
        }
        
        return is_array($input) ? $input : [];
    }
}
