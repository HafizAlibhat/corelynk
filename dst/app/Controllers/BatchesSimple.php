<?php

namespace App\Controllers;

use App\Models\ProcessBatchModel;

class Batches extends BaseController
{
    protected $batchModel;

    public function __construct()
    {
        $this->batchModel = new ProcessBatchModel();
    }

    /**
     * Display batch list - SIMPLIFIED VERSION
     */
    public function index()
    {
        // Set test user session
        if (!session()->has('user_id')) {
            session()->set([
                'user_id' => 1,
                'username' => 'admin',
                'email' => 'admin@example.com',
                'role' => 'admin',
                'first_name' => 'System',
                'last_name' => 'Administrator'
            ]);
        }

        try {
            // Get all batches - simple query
            $batches = $this->batchModel->findAll();
            
            // Calculate basic stats
            $stats = [
                'total' => count($batches),
                'pending' => 0,
                'in_progress' => 0,
                'completed' => 0,
                'quality_check' => 0,
                'hold' => 0,
                'rejected' => 0
            ];
            
            // Count by status
            foreach ($batches as $batch) {
                if (isset($stats[$batch['status']])) {
                    $stats[$batch['status']]++;
                }
            }

            $data = [
                'page_title' => 'Batch Management System',
                'current_page' => 'batches',
                'batches' => $batches,
                'stats' => $stats,
                'workOrders' => [], // Simple for now
                'pager' => null,
                'filters' => []
            ];

            return view('batches/index', $data);
            
        } catch (\Exception $e) {
            // Show debug info instead of error
            echo "<h1>Debug Info</h1>";
            echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
            echo "<pre>" . $e->getTraceAsString() . "</pre>";
            die();
        }
    }

    /**
     * Show single batch
     */
    public function show($id)
    {
        $batch = $this->batchModel->find($id);
        
        if (!$batch) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Batch not found');
        }

        $data = [
            'page_title' => 'Batch Details',
            'current_page' => 'batches',
            'batch' => $batch
        ];

        return view('batches/show', $data);
    }

    /**
     * Create batch form
     */
    public function create()
    {
        $data = [
            'page_title' => 'Create New Batch',
            'current_page' => 'batches'
        ];

        return view('batches/create', $data);
    }

    /**
     * Delete batch
     */
    public function delete($id)
    {
        try {
            $batch = $this->batchModel->find($id);
            if (!$batch) {
                return $this->response->setJSON(['success' => false, 'message' => 'Batch not found']);
            }

            $this->batchModel->delete($id);
            
            return $this->response->setJSON(['success' => true, 'message' => 'Batch deleted successfully']);
        } catch (\Exception $e) {
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
