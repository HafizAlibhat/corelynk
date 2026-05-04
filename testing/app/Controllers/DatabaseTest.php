<?php

namespace App\Controllers;

class DatabaseTest extends BaseController
{
    public function index()
    {
        try {
            $db = \Config\Database::connect();
            
            echo "<h1>Database Connection Test</h1>";
            echo "<p>Database connection: <strong style='color: green;'>SUCCESS</strong></p>";
            
            // Test products table
            $products = $db->query("SELECT COUNT(*) as count FROM products")->getRow();
            echo "<p>Products table: <strong>{$products->count} records</strong></p>";
            
            // Test processes table
            $processes = $db->query("SELECT COUNT(*) as count FROM processes")->getRow();
            echo "<p>Processes table: <strong>{$processes->count} records</strong></p>";
            
            // Test work_orders table
            $workOrders = $db->query("SELECT COUNT(*) as count FROM work_orders")->getRow();
            echo "<p>Work Orders table: <strong>{$workOrders->count} records</strong></p>";
            
            // Test process_batches table
            $batches = $db->query("SELECT COUNT(*) as count FROM process_batches")->getRow();
            echo "<p>Process Batches table: <strong>{$batches->count} records</strong></p>";
            
            // Test the join query from Batches controller
            $query = "SELECT 
                process_batches.*,
                work_orders.wo_number as work_order_number,
                products.name as product_name,
                products.code as product_code,
                processes.name as process_name,
                processes.code as process_code
            FROM process_batches
            LEFT JOIN work_order_items ON work_order_items.id = process_batches.work_order_item_id
            LEFT JOIN work_orders ON work_orders.id = work_order_items.work_order_id
            LEFT JOIN products ON products.id = work_order_items.product_id
            LEFT JOIN processes ON processes.id = process_batches.process_id
            LIMIT 2";
            
            $result = $db->query($query)->getResultArray();
            echo "<h2>Sample Batch Data:</h2>";
            echo "<pre>" . print_r($result, true) . "</pre>";
            
        } catch (\Exception $e) {
            echo "<h1 style='color: red;'>Database Error</h1>";
            echo "<p>Error: " . $e->getMessage() . "</p>";
        }
    }
}
