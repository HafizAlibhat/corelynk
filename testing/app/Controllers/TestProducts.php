<?php

namespace App\Controllers;

use App\Models\ProductModel;

class TestProducts extends BaseController
{
    protected $productModel;

    public function __construct()
    {
        $this->productModel = new ProductModel();
    }

    /**
     * Test version without authentication
     */
    public function addProcess($productId = null)
    {
        // NO AUTHENTICATION CHECK - for testing only
        
        header('Content-Type: application/json');
        
        try {
            echo "<!-- Debug: Method called with productId: $productId -->\n";
            
            $product = $this->productModel->find($productId);
            if (!$product) {
                echo json_encode(['success' => false, 'message' => 'Product not found.']);
                return;
            }

            $processIds = $this->request->getPost('process_ids');
            
            if (empty($processIds)) {
                echo json_encode(['success' => false, 'message' => 'Please select at least one process.']);
                return;
            }

            // Convert to array if it's a single ID or comma-separated string
            if (!is_array($processIds)) {
                if (strpos($processIds, ',') !== false) {
                    $processIds = explode(',', $processIds);
                } else {
                    $processIds = [$processIds];
                }
            }
            
            // Clean up the array
            $processIds = array_filter(array_map('trim', $processIds));
            
            if (empty($processIds)) {
                echo json_encode(['success' => false, 'message' => 'No valid process IDs provided.']);
                return;
            }

            // Use direct database approach
            $db = \Config\Database::connect();
            
            $successCount = 0;
            $errors = [];
            
            foreach ($processIds as $processId) {
                try {
                    // Check if this process exists
                    $processExists = $db->table('processes')
                                      ->where('id', $processId)
                                      ->where('is_active', 1)
                                      ->get()
                                      ->getRowArray();
                    
                    if (!$processExists) {
                        $errors[] = "Process ID $processId not found or inactive";
                        continue;
                    }
                    
                    // Check if already assigned
                    $existing = $db->table('product_processes')
                                 ->where('product_id', $productId)
                                 ->where('process_id', $processId)
                                 ->where('is_active', 1)
                                 ->get()
                                 ->getRowArray();
                    
                    if ($existing) {
                        $errors[] = "Process '{$processExists['name']}' is already assigned to this product";
                        continue;
                    }
                    
                    // Get max sequence order
                    $maxSequence = $db->table('product_processes')
                                    ->where('product_id', $productId)
                                    ->selectMax('sequence_order')
                                    ->get()
                                    ->getRowArray()['sequence_order'] ?? 0;
                    
                    // Insert the record
                    $insertData = [
                        'product_id' => $productId,
                        'process_id' => $processId,
                        'sequence_order' => $maxSequence + 1,
                        'is_active' => 1
                    ];
                    
                    $insertResult = $db->table('product_processes')->insert($insertData);
                    
                    if ($insertResult) {
                        $successCount++;
                    } else {
                        $errors[] = "Failed to insert process ID $processId";
                    }
                    
                } catch (\Exception $e) {
                    $errors[] = "Error with process ID $processId: " . $e->getMessage();
                }
            }
            
            if ($successCount > 0) {
                $message = "$successCount process(es) added successfully.";
                if (!empty($errors)) {
                    $message .= " Warnings: " . implode(', ', $errors);
                }
                echo json_encode(['success' => true, 'message' => $message]);
            } else {
                $message = "No processes were added.";
                if (!empty($errors)) {
                    $message .= " Errors: " . implode(', ', $errors);
                }
                echo json_encode(['success' => false, 'message' => $message]);
            }
            
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
        }
    }
}
?>
