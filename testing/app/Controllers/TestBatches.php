<?php

namespace App\Controllers;

use App\Models\ProcessBatchModel;

class TestBatches extends BaseController
{
    public function index()
    {
        // Set session
        session()->set(['user_id' => 1, 'username' => 'admin', 'role' => 'admin']);
        
        try {
            $batchModel = new ProcessBatchModel();
            $batches = $batchModel->findAll();
            
            echo "<h1>Process Batches Data Test</h1>";
            echo "<p>Total batches found: " . count($batches) . "</p>";
            
            if (!empty($batches)) {
                echo "<h2>First Batch Data Structure:</h2>";
                echo "<pre>" . print_r($batches[0], true) . "</pre>";
                
                echo "<h2>All Batches:</h2>";
                echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
                echo "<tr>";
                echo "<th>ID</th><th>Batch Number</th><th>Status</th><th>Quantity</th><th>Completed</th><th>Work Order Item ID</th><th>Process ID</th>";
                echo "</tr>";
                
                foreach ($batches as $batch) {
                    echo "<tr>";
                    echo "<td>" . $batch['id'] . "</td>";
                    echo "<td>" . $batch['batch_number'] . "</td>";
                    echo "<td>" . $batch['status'] . "</td>";
                    echo "<td>" . $batch['quantity'] . "</td>";
                    echo "<td>" . ($batch['quantity_completed'] ?? 0) . "</td>";
                    echo "<td>" . $batch['work_order_item_id'] . "</td>";
                    echo "<td>" . $batch['process_id'] . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>No batches found!</p>";
            }
            
            echo "<hr><p><a href='/batches'>Back to Batches</a></p>";
            
        } catch (\Exception $e) {
            echo "<h1 style='color: red;'>Error: " . $e->getMessage() . "</h1>";
            echo "<pre>" . $e->getTraceAsString() . "</pre>";
        }
    }
}
