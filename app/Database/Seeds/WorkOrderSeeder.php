<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class WorkOrderSeeder extends Seeder
{
    public function run()
    {
        $db = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');

    // (idempotent insertion happens below)

        // Desired sample work orders
        $desiredWos = [
            [
                'wo_number' => 'WO-20250815-001',
                'customer_name' => 'ACME Corp',
                'due_date' => date('Y-m-d', strtotime('+7 days')),
                'status' => 'planned',
                'priority' => 'normal',
                'notes' => 'Sample work order 1',
                'created_by' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'wo_number' => 'WO-20250815-002',
                'customer_name' => 'Beta Industries',
                'due_date' => date('Y-m-d', strtotime('+3 days')),
                'status' => 'in_progress',
                'priority' => 'high',
                'notes' => 'Sample work order 2',
                'created_by' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'wo_number' => 'WO-20250815-003',
                'customer_name' => 'Gamma LLC',
                'due_date' => date('Y-m-d', strtotime('+14 days')),
                'status' => 'planned',
                'priority' => 'low',
                'notes' => 'Sample work order 3',
                'created_by' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        // Insert missing work orders and collect IDs
        $woIds = [];
        foreach ($desiredWos as $wo) {
            $existing = $db->table('work_orders')->where('wo_number', $wo['wo_number'])->get()->getRowArray();
            if ($existing) {
                $woIds[] = $existing['id'];
                continue;
            }

            $db->table('work_orders')->insert($wo);
            $woIds[] = $db->insertID();
        }

        // Insert sample items for each work order using existing products if available
        // Ensure we have at least one product to reference
        $product = $db->table('products')->select('id')->limit(1)->get()->getRowArray();
        if (!$product) {
            $db->table('products')->insert([
                'name' => 'Sample Product',
                'code' => 'SP-001',
                'unit' => 'pcs',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now
            ]);
            $productId = $db->insertID();
        } else {
            $productId = $product['id'];
        }

        $items = [];
        foreach ($woIds as $i => $woId) {
            $items[] = [
                'work_order_id' => $woId,
                'product_id' => $productId ?? 1,
                'quantity_ordered' => 10 + ($i * 5),
                'quantity_completed' => $i === 1 ? 2 : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($items)) {
            $db->table('work_order_items')->insertBatch($items);
        }

        // Create a process batch and logs for the first work order item if not present
        if (!empty($woIds)) {
            $firstWoId = $woIds[0];
            $firstItem = $db->table('work_order_items')->where('work_order_id', $firstWoId)->get()->getRowArray();
            if ($firstItem) {
                $existingBatch = $db->table('process_batches')->where('work_order_item_id', $firstItem['id'])->get()->getRowArray();
                if (!$existingBatch) {
                    $db->table('process_batches')->insert([
                        'work_order_item_id' => $firstItem['id'],
                        'process_id' => 1,
                        'batch_code' => 'BATCH-001',
                        'planned_qty' => $firstItem['quantity_ordered'],
                        'started_at' => $now,
                        'status' => 'open',
                        'created_by' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $batchId = $db->insertID();

                    // Add logs only if none exist for this batch
                    $existingLogs = $db->table('process_batch_logs')->where('process_batch_id', $batchId)->countAllResults();
                    if ($existingLogs == 0) {
                        $db->table('process_batch_logs')->insertBatch([
                            [
                                'process_batch_id' => $batchId,
                                'log_date' => $now,
                                'accepted_qty' => 6.000,
                                'repaired_qty' => 1.000,
                                'rejected_qty' => 0.000,
                                'operator_id' => 1,
                                'notes' => 'Initial run',
                                'created_at' => $now,
                            ],
                            [
                                'process_batch_id' => $batchId,
                                'log_date' => $now,
                                'accepted_qty' => 3.000,
                                'repaired_qty' => 0.000,
                                'rejected_qty' => 1.000,
                                'operator_id' => 1,
                                'notes' => 'Second run',
                                'created_at' => $now,
                            ],
                        ]);
                    }
                }
            }
        }

    // Completed: idempotent batches/logs above

        echo "Seeded work orders and related data.\n";
    }
}
