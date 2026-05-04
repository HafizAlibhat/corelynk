<?php

namespace App\Controllers;

class DebugController extends BaseController
{
    // Unauthenticated read-only endpoint for debugging AJAX from browser
    public function processes($workOrderId = null, $itemId = null)
    {
        // Return sample JSON similar to WorkOrders::ajaxGetItemProcesses but without auth
        $productId = (int) $this->request->getGet('product_id');

        $productProcessModel = new \App\Models\ProductProcessModel();
        $processes = [];
        if ($productId > 0 && method_exists($productProcessModel, 'getProductProcessesWithDetails')) {
            $processes = $productProcessModel->getProductProcessesWithDetails($productId);
        }

        $db = \Config\Database::connect();
        $batches = $db->table('process_batches')
            ->where('work_order_item_id', $itemId)
            ->orderBy('created_at', 'DESC')
            ->get()
            ->getResultArray();

        return $this->response->setJSON(['success' => true, 'debug' => true, 'processes' => $processes, 'batches' => $batches]);
    }
}
