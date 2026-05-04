<?php

namespace App\Controllers;

class DebugBatches extends BaseController
{
    public function index()
    {
        try {
            // Set test session
            session()->set([
                'user_id' => 1,
                'username' => 'admin',
                'role' => 'admin'
            ]);
            
            echo "<h1>Debug Batches Controller</h1>";
            
            // Test database connection
            $db = \Config\Database::connect();
            echo "<p>✅ Database connection OK</p>";
            
            // Test simple query
            $result = $db->query("SELECT COUNT(*) as count FROM process_batches")->getRow();
            echo "<p>✅ Process batches table: {$result->count} records</p>";
            
            // Test the actual query from Batches controller
            echo "<h2>Testing Batch Query:</h2>";
            
            $query = "SELECT 
                process_batches.*,
                work_orders.wo_number as work_order_number,
                products.name as product_name,
                products.code as product_code,
                processes.name as process_name,
                COUNT(process_batch_logs.id) as log_count
            FROM process_batches
            LEFT JOIN work_order_items ON work_order_items.id = process_batches.work_order_item_id
            LEFT JOIN work_orders ON work_orders.id = work_order_items.work_order_id
            LEFT JOIN products ON products.id = work_order_items.product_id
            LEFT JOIN processes ON processes.id = process_batches.process_id
            LEFT JOIN process_batch_logs ON process_batch_logs.batch_id = process_batches.id
            GROUP BY process_batches.id
            LIMIT 2";
            
            echo "<pre>" . htmlspecialchars($query) . "</pre>";
            
            $result = $db->query($query)->getResultArray();
            echo "<h3>Query Result:</h3>";
            echo "<pre>" . print_r($result, true) . "</pre>";
            
            // Test stats query
            echo "<h2>Testing Stats Queries:</h2>";
            
            $statsQuery1 = "SELECT SUM(quantity) as total_planned FROM process_batches";
            $result1 = $db->query($statsQuery1)->getRow();
            echo "<p>Total planned: " . ($result1->total_planned ?? 0) . "</p>";
            
            $statsQuery2 = "SELECT SUM(quantity_completed) as total_actual FROM process_batches";
            $result2 = $db->query($statsQuery2)->getRow();
            echo "<p>Total actual: " . ($result2->total_actual ?? 0) . "</p>";
            
        } catch (\Exception $e) {
            echo "<h1 style='color: red;'>ERROR:</h1>";
            echo "<p>" . $e->getMessage() . "</p>";
            echo "<pre>" . $e->getTraceAsString() . "</pre>";
        }
    }
}
