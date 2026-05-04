<?php

namespace App\Controllers;

use App\Models\ProcessBatchModel;

class BatchDebug extends BaseController
{
    public function index()
    {
        $batchModel = new ProcessBatchModel();
        
        // Simple query first
        $simpleBatches = $batchModel->findAll();
        
        // Complex query like in main controller
        $builder = $batchModel->select('
            process_batches.*,
            work_orders.wo_number as work_order_number,
            products.name as product_name,
            products.code as product_code,
            processes.name as process_name,
            COUNT(process_batch_logs.id) as log_count
        ')
        ->join('work_order_items', 'work_order_items.id = process_batches.work_order_item_id', 'left')
        ->join('work_orders', 'work_orders.id = work_order_items.work_order_id', 'left')
        ->join('products', 'products.id = work_order_items.product_id', 'left')
        ->join('processes', 'processes.id = process_batches.process_id', 'left')
        ->join('process_batch_logs', 'process_batch_logs.batch_id = process_batches.id', 'left')
        ->groupBy('process_batches.id')
        ->orderBy('process_batches.created_at', 'DESC');
        
        $complexBatches = $builder->findAll();
        
        echo "<h2>Batch Debug Results</h2>";
        echo "<h3>Simple Query: " . count($simpleBatches) . " results</h3>";
        
        if (!empty($simpleBatches)) {
            echo "<pre>";
            foreach ($simpleBatches as $batch) {
                echo "ID: " . $batch['id'] . ", Batch: " . $batch['batch_number'] . ", Status: " . $batch['status'] . "\n";
            }
            echo "</pre>";
        }
        
        echo "<h3>Complex Query: " . count($complexBatches) . " results</h3>";
        
        if (!empty($complexBatches)) {
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>ID</th><th>Batch</th><th>Product</th><th>Process</th><th>WO</th><th>Log Count</th></tr>";
            foreach ($complexBatches as $batch) {
                echo "<tr>";
                echo "<td>" . ($batch['id'] ?? 'NULL') . "</td>";
                echo "<td>" . ($batch['batch_number'] ?? 'NULL') . "</td>";
                echo "<td>" . ($batch['product_name'] ?? 'NULL') . "</td>";
                echo "<td>" . ($batch['process_name'] ?? 'NULL') . "</td>";
                echo "<td>" . ($batch['work_order_number'] ?? 'NULL') . "</td>";
                echo "<td>" . ($batch['log_count'] ?? 'NULL') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='color: red;'>Complex query returned no results!</p>";
        }
        
        // Show the generated SQL
        echo "<h3>Generated SQL:</h3>";
        echo "<pre>" . $builder->getCompiledSelect(false) . "</pre>";
    }
}
