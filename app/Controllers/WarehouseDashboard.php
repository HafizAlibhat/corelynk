<?php

namespace App\Controllers;

use App\Models\SalesOrderModel;
use App\Models\CustomerModel;
use App\Services\ReadyToShipService;
use App\Services\FulfillmentStatusService;

class WarehouseDashboard extends BaseController
{
    public function readyToShip()
    {
        $this->requireAuth();

        $salesOrderModel = new SalesOrderModel();
        $customerModel = new CustomerModel();
        $readyService = new ReadyToShipService();
        $fulfillmentService = new FulfillmentStatusService();

        $readyOrderIds = $readyService->getReadySalesOrders();
        $orders = [];

        if (!empty($readyOrderIds)) {
            $orders = $salesOrderModel->whereIn('id', $readyOrderIds)
                ->orderBy('id', 'DESC')
                ->findAll();
        }

        $customerMap = [];
        if (!empty($orders)) {
            $customerIds = array_values(array_unique(array_filter(array_map('intval', array_column($orders, 'customer_id')))));
            if (!empty($customerIds)) {
                $customers = $customerModel->whereIn('id', $customerIds)->findAll();
                foreach ($customers as $c) {
                    $customerMap[(int)$c['id']] = $c;
                }
            }
        }

        $rows = [];
        foreach ($orders as $order) {
            $orderId = (int)($order['id'] ?? 0);
            if ($orderId <= 0) {
                continue;
            }

            $readyData = $readyService->getLineReadiness($orderId);
            $readyQty = 0.0;
            foreach (($readyData['lines'] ?? []) as $rl) {
                $readyQty += (float)($rl['ready_now'] ?? 0);
            }

            if ($readyQty <= 0) {
                continue;
            }

            $fulfillment = $fulfillmentService->getSalesOrderFulfillment($orderId);
            $fulfillmentStatus = $fulfillment['orderStatus'] ?? 'UNKNOWN';

            $customerId = (int)($order['customer_id'] ?? 0);
            $customer = $customerMap[$customerId] ?? null;
            $customerName = $order['customer_name']
                ?? $customer['name']
                ?? $customer['company_name']
                ?? ($customerId > 0 ? ('Customer #' . $customerId) : '');

            // Check if a draft DO already exists for this SO
            $db = \Config\Database::connect();
            $draftDo = null;
            if ($db->tableExists('delivery_orders')) {
                $draftDo = $db->table('delivery_orders')
                    ->select('id, do_number, status')
                    ->where('sales_order_id', $orderId)
                    ->where('status', 'draft')
                    ->get()->getRowArray();
            }

            $rows[] = [
                'id'                => $orderId,
                'order_number'      => $order['order_number'] ?? ('SO-' . $orderId),
                'customer'          => $customerName,
                'ready_qty'         => $readyQty,
                'fulfillment_status'=> $fulfillmentStatus,
                'draft_do_id'       => $draftDo ? (int)$draftDo['id'] : null,
                'draft_do_number'   => $draftDo ? ($draftDo['do_number'] ?? '') : null,
            ];
        }

        $data = $this->setPageData([
            'page_title' => 'Warehouse - Ready to Ship',
            'orders' => $rows,
        ]);

        return view('warehouse/ready_to_ship', $data);
    }

    /**
     * Show incoming shipments from vendors (open/partially received POs)
     */
    public function incomingFromVendors()
    {
        $this->requireAuth();

        $db = \Config\Database::connect();

        // Build a schema-safe filter so warehouse only sees physical incoming stock,
        // not service purchases (e.g., shipping agent service invoices).
        $productFilters = ['p.id IS NOT NULL'];
        if ($db->fieldExists('product_type', 'products')) {
            $productFilters[] = "LOWER(COALESCE(p.product_type,'')) NOT IN ('service','shipping_service','shipping','digital')";
        }
        if ($db->fieldExists('detailed_type', 'products')) {
            $productFilters[] = "LOWER(COALESCE(p.detailed_type,'')) NOT IN ('service','shipping_service','shipping','digital')";
        }
        if ($db->fieldExists('is_stockable', 'products')) {
            $productFilters[] = 'COALESCE(p.is_stockable,1) = 1';
        }
        if ($db->fieldExists('track_inventory', 'products')) {
            $productFilters[] = 'COALESCE(p.track_inventory,1) = 1';
        }
        $stockProductWhere = implode(' AND ', $productFilters);

        // Select PO header + lines with product and vendor info
        $rows = [];
        try {
            if ($db->tableExists('purchase_orders')) {
                // Force use of delivery_date as ETA column
                $etaCol = 'delivery_date';

                // Build GRN aggregation subquery to compute received per PO line when pol.qty_received is missing
                $grnSub = $db->table('purchase_grn_lines')
                    ->select('po_line_id, SUM(qty_received) AS grn_received')
                    ->groupBy('po_line_id')
                    ->getCompiledSelect();

                // Prepare expected date select (use detected column or NULL)
                $etaSelect = "po.$etaCol as expected_date";

                $qb = $db->table('purchase_orders po')
                    ->select("po.id as po_id, po.po_number, po.status, {$etaSelect}, v.id as vendor_id, v.name as vendor_name, pol.id as line_id, pol.product_id, pol.qty as ordered_qty, COALESCE(pol.qty_received, grn.grn_received, 0) as received_qty, p.name as product_name, COALESCE(pv.name, '') as variant_name, COALESCE(pv.art_number, '') as variant_art_number")
                    ->join('purchase_order_lines pol', 'pol.po_id = po.id', 'inner')
                    ->join('vendors v', 'v.id = po.vendor_id', 'left')
                    ->join('products p', 'p.id = pol.product_id', 'left')
                    ->join('product_variants pv', 'pv.id = COALESCE(pol.variant_id, pol.product_variant_id)', 'left')
                    ->join('(' . $grnSub . ') grn', 'grn.po_line_id = pol.id', 'left', false)
                    ->where("(pol.qty - COALESCE(pol.qty_received, grn.grn_received,0)) > 0")
                    ->where($stockProductWhere, null, false)
                    // Show only actionable incoming POs; hide completed/received/cancelled lifecycle states.
                    ->where("LOWER(COALESCE(po.status, '')) NOT IN ('closed','received','completed','cancelled','canceled','rejected')", null, false)
                    ->orderBy('po.id', 'DESC');

                // Apply optional date filters from GET params (expected_date)
                $start = $this->request->getGet('start_date');
                $end = $this->request->getGet('end_date');
                if (!empty($start)) {
                    // expect YYYY-MM-DD
                    $qb->where("po.$etaCol >=", $start);
                }
                if (!empty($end)) {
                    $qb->where("po.$etaCol <=", $end);
                }

                $rows = $qb->get()->getResultArray();
                // If we have rows, collect related sales orders mapped to these PO lines
                $lineIds = array_values(array_filter(array_map(function($r){ return isset($r['line_id']) ? (int)$r['line_id'] : 0; }, $rows)));
                $poLineSoMap = [];
                if (!empty($lineIds)) {
                    try {
                        $mapQ = $db->table('sales_order_line_po_map sm')
                            ->select('sm.po_line_id, so.order_number')
                            ->join('sales_orders so', 'so.id = sm.sales_order_id', 'left')
                            ->whereIn('sm.po_line_id', $lineIds)
                            ->get();
                        $mapRows = $mapQ->getResultArray();
                        foreach ($mapRows as $mr) {
                            $pid = (int)($mr['po_line_id'] ?? 0);
                            if ($pid <= 0) continue;
                            $poLineSoMap[$pid][] = $mr['order_number'] ?? null;
                        }
                    } catch (\Throwable $e) {
                        // ignore mapping errors
                        $poLineSoMap = [];
                    }
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'WarehouseDashboard.incomingFromVendors error: ' . $e->getMessage());
            $rows = [];
        }

        // Group lines by PO
        $poList = [];
        $flatList = [];
        foreach ($rows as $r) {
            $poId = (int)($r['po_id'] ?? 0);
            if ($poId <= 0) continue;
            if (!isset($poList[$poId])) {
                $poList[$poId] = [
                    'po_id' => $poId,
                    'po_number' => $r['po_number'] ?? ('PO-' . $poId),
                    'vendor_name' => $r['vendor_name'] ?? '',
                    'status' => $r['status'] ?? '',
                    'expected_date' => $r['expected_date'] ?? null,
                    'lines' => [],
                ];
            }

            $poList[$poId]['lines'][] = [
                'line_id' => (int)($r['line_id'] ?? 0),
                'product_id' => (int)($r['product_id'] ?? 0),
                'product_name' => $r['product_name'] ?? ('Product #' . ($r['product_id'] ?? '')), 
                    'variant_name' => $r['variant_name'] ?? '',
                    'variant_art_number' => $r['variant_art_number'] ?? '',
                'ordered_qty' => (float)($r['ordered_qty'] ?? 0),
                'received_qty' => (float)($r['received_qty'] ?? 0),
                'pending_qty' => max(0, (float)($r['ordered_qty'] ?? 0) - (float)($r['received_qty'] ?? 0)),
            ];
            // also push into flat list for tabular view
            $flatList[] = [
                'po_id' => $poId,
                'po_number' => $poList[$poId]['po_number'],
                'vendor_name' => $poList[$poId]['vendor_name'],
                'expected_date' => $poList[$poId]['expected_date'],
                'line_id' => (int)($r['line_id'] ?? 0),
                'product_id' => (int)($r['product_id'] ?? 0),
                'product_name' => $r['product_name'] ?? ('Product #' . ($r['product_id'] ?? '')),
                'ordered_qty' => (float)($r['ordered_qty'] ?? 0),
                'received_qty' => (float)($r['received_qty'] ?? 0),
                'pending_qty' => max(0, (float)($r['ordered_qty'] ?? 0) - (float)($r['received_qty'] ?? 0)),
                'related_sos' => [],
            ];
        }


            // If nothing matched, try both possible join columns for PO lines and show debug if still empty
            if (empty($flatList)) {
                $rows = [];
                foreach ([['po_id','qty'], ['purchase_order_id','qty']] as $join) {
                    $col = $join[0];
                    $qtyCol = $join[1];
                    $qb = $db->table('purchase_orders po')
                        ->select("po.id as po_id, po.po_number, po.status, po.delivery_date as expected_date, v.id as vendor_id, v.name as vendor_name, pol.id as line_id, pol.product_id, pol.$qtyCol as ordered_qty, COALESCE(pol.qty_received, grn.grn_received, 0) as received_qty, p.name as product_name")
                        ->join("purchase_order_lines pol", "pol.$col = po.id", 'inner')
                        ->join('vendors v', 'v.id = po.vendor_id', 'left')
                        ->join('products p', 'p.id = pol.product_id', 'left')
                        ->join('(' . $grnSub . ') grn', 'grn.po_line_id = pol.id', 'left', false)
                        ->where("(pol.$qtyCol - COALESCE(pol.qty_received, grn.grn_received,0)) > 0")
                        ->where($stockProductWhere, null, false)
                        ->where("LOWER(COALESCE(po.status, '')) NOT IN ('closed','received','completed','cancelled','canceled','rejected')", null, false)
                        ->orderBy('po.id', 'DESC');
                    $test = $qb->get()->getResultArray();
                    if (!empty($test)) {
                        $rows = $test;
                        break;
                    }
                }
                // If still no rows, dump 5 raw PO lines for debugging
                if (empty($rows)) {
                    try {
                        $rawLines = $db->table('purchase_order_lines')->limit(5)->get()->getResultArray();
                    } catch (\Throwable $e) { $rawLines = []; }
                    $debugRawLines = $rawLines;
                } else {
                    $debugRawLines = [];
                }
            } else {
                $debugRawLines = [];
            }

            // Provide a sample list of candidate POs for diagnostics when no lines found
            $candidatePos = [];
            try {
                if (empty($flatList) && $db->tableExists('purchase_orders')) {
                    $etaColLocal = isset($etaCol) ? $etaCol : null;
                    $selectEta = $etaColLocal ? "po.$etaColLocal as expected_date" : "NULL as expected_date";
                    $candQ = $db->table('purchase_orders po')
                        ->distinct()
                        ->select("po.id, po.po_number, po.status, {$selectEta}, v.name as vendor_name")
                        ->join('vendors v', 'v.id = po.vendor_id', 'left')
                        ->join('purchase_order_lines pol', 'pol.po_id = po.id', 'inner')
                        ->join('products p', 'p.id = pol.product_id', 'left')
                        ->join('(' . $grnSub . ') grn', 'grn.po_line_id = pol.id', 'left', false)
                        ->where("(pol.qty - COALESCE(pol.qty_received, grn.grn_received,0)) > 0")
                        ->where($stockProductWhere, null, false)
                        ->where("LOWER(COALESCE(po.status, '')) NOT IN ('closed','received','completed','cancelled','canceled','rejected')", null, false)
                        ->orderBy('po.created_at', 'DESC')
                        ->limit(10)
                        ->get();
                    $candidatePos = $candQ->getResultArray();
                }
            } catch (\Throwable $e) {
                $candidatePos = [];
            }

            $data = $this->setPageData([
                'page_title' => 'Warehouse - Incoming Shipments',
                'poList' => $poList,
                'flatList' => $flatList,
                'debugRawLines' => $debugRawLines ?? [],
                'candidatePos' => $candidatePos,
            ]);

            return view('warehouse/incoming_shipments', $data);
    }

    public function debugFulfillment()
    {
        $this->requireAuth();
        
        $db = \Config\Database::connect();
        
        echo "<pre>";
        echo "=== DEBUGGING FULFILLMENT STATUS ===\n\n";
        
        // Get SO lines for RI-S0002 and RI-S0001
        $soLines = $db->query("
            SELECT so.order_number, sol.id as line_id, sol.product_id, sol.product_variant_id, 
                   sol.quantity, p.name, p.product_type
            FROM sales_order_lines sol
            LEFT JOIN sales_orders so ON so.id = sol.sales_order_id
            LEFT JOIN products p ON p.id = sol.product_id
            WHERE so.order_number IN ('RI-S0002', 'RI-S0001')
            ORDER BY so.order_number
        ")->getResultArray();
        
        echo "Sales Order Lines:\n";
        foreach ($soLines as $line) {
            echo "SO: {$line['order_number']}, Line ID: {$line['line_id']}\n";
            echo "  Product: {$line['name']} (ID: {$line['product_id']})\n";
            echo "  Type: {$line['product_type']}\n";
            echo "  Variant ID: " . ($line['product_variant_id'] ?: 'NULL') . "\n";
            echo "  Qty: {$line['quantity']}\n\n";
        }
        
        echo "\n=== STOCK BALANCES ===\n\n";
        $stock = $db->query("
            SELECT product_id, variant_id, SUM(quantity) as total_qty, 
                   COUNT(*) as locations
            FROM stock_balances
            GROUP BY product_id, variant_id
            ORDER BY product_id, variant_id
        ")->getResultArray();
        
        foreach ($stock as $s) {
            echo "Product ID: {$s['product_id']}, Variant ID: " . ($s['variant_id'] ?: 'NULL');
            echo ", Total Qty: {$s['total_qty']}, Locations: {$s['locations']}\n";
        }
        
        echo "\n=== TESTING FULFILLMENT SERVICE ===\n\n";
        
        $fulfillmentService = new FulfillmentStatusService();
        
        // Get SO IDs
        $sos = $db->query("SELECT id, order_number FROM sales_orders WHERE order_number IN ('RI-S0002', 'RI-S0001')")->getResultArray();
        
        foreach ($sos as $so) {
            echo "Testing SO: {$so['order_number']} (ID: {$so['id']})\n";
            
            try {
                // First, manually fetch SO lines to see what SHOULD be returned
                $soLinesInDb = $db->query("
                    SELECT sol.id, sol.product_id, sol.product_variant_id, sol.quantity, p.name, p.product_type
                    FROM sales_order_lines sol
                    LEFT JOIN products p ON p.id = sol.product_id
                    WHERE sol.sales_order_id = {$so['id']}
                ")->getResultArray();
                
                echo "  Database check - SO lines count: " . count($soLinesInDb) . "\n";
                if (!empty($soLinesInDb)) {
                    foreach ($soLinesInDb as $dbLine) {
                        echo "    - Line {$dbLine['id']}: Product {$dbLine['product_id']} ({$dbLine['product_type']}), Qty {$dbLine['quantity']}\n";
                    }
                }
                
                // Manually test what the service gets
                echo "  [DEBUG] Testing getAvailableStock for this SO's products:\n";
                
                $testLines = $db->query("
                    SELECT DISTINCT sol.product_id, p.product_type
                    FROM sales_order_lines sol
                    LEFT JOIN products p ON p.id = sol.product_id
                    WHERE sol.sales_order_id = {$so['id']}
                ")->getResultArray();
                
                foreach ($testLines as $tl) {
                    $refMethod = new \ReflectionMethod($fulfillmentService, 'getAvailableStock');
                    $refMethod->setAccessible(true);
                    
                    $stock = $refMethod->invoke($fulfillmentService, $tl['product_id'], null, $tl['product_type']);
                    echo "    - Product ID: {$tl['product_id']}, Type: {$tl['product_type']}, Stock: $stock\n";
                }
                
                // Call service
                $result = $fulfillmentService->getSalesOrderFulfillment($so['id']);
                echo "  Service result - Status: {$result['orderStatus']}\n";
                echo "  Service result - Lines count: " . count($result['lines']) . "\n";
                
                // Now let's manually verify if SO query is working
                echo "  [Manual verification]\n";
                $manualSoLines = $db->table('sales_order_lines sol')
                    ->select('sol.id as line_id, sol.product_id, sol.product_variant_id, sol.quantity, p.name as product_name, p.product_type')
                    ->join('products p', 'p.id = sol.product_id', 'left')
                    ->where('sol.sales_order_id', $so['id'])
                    ->get()
                    ->getResultArray();
                echo "  Manual SO query returned: " . count($manualSoLines) . " lines\n";
                if (!empty($manualSoLines)) {
                    foreach ($manualSoLines as $msl) {
                        echo "    - Line {$msl['line_id']}: Product {$msl['product_id']}, Type {$msl['product_type']}, Qty {$msl['quantity']}\n";
                    }
                }
                if (empty($result['lines'])) {
                    echo "  WARNING: No lines returned!\n";
                    // Debug: manually check what's in the SO
                    $manualLines = $db->query("
                        SELECT sol.id, sol.product_id, sol.product_variant_id, sol.quantity, p.product_type
                        FROM sales_order_lines sol
                        LEFT JOIN products p ON p.id = sol.product_id
                        WHERE sol.sales_order_id = {$so['id']}
                    ")->getResultArray();
                    echo "  Actual lines in DB: " . count($manualLines) . "\n";
                    foreach ($manualLines as $ml) {
                        echo "    - Product ID: {$ml['product_id']}, Type: {$ml['product_type']}, Variant: " . ($ml['product_variant_id'] ?: 'NULL') . "\n";
                    }
                } else {
                    foreach ($result['lines'] as $line) {
                        echo "    - {$line['product_name']}: Ordered={$line['ordered_qty']}, Received={$line['received_qty']}, Ready={$line['ready_to_ship_qty']}\n";
                    }
                }
                echo "\n";
            } catch (\Exception $e) {
                echo "  ERROR: " . $e->getMessage() . "\n\n";
            }
        }
        
        echo "</pre>";
        exit;
    }

    public function testReadyToShip()
    {
        $this->requireAuth();
        
        $db = \Config\Database::connect();
        $readyService = new \App\Services\ReadyToShipService();
        
        echo "<pre>";
        echo "=== TESTING READYTOSHIP SERVICE ===\n\n";
        
        // Get first SO that should be ready
        $so = $db->table('sales_orders')
            ->select('id, order_number')
            ->where('order_number', 'RI-S0001')
            ->get()
            ->getRowArray();
        
        if (!$so) {
            echo "SO RI-S0001 not found\n";
            exit;
        }
        
        $soId = (int)$so['id'];
        echo "Testing SO: {$so['order_number']} (ID: $soId)\n\n";
        
        try {
            $readyData = $readyService->getLineReadiness($soId);
            echo "getLineReadiness Result:\n";
            echo json_encode($readyData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        } catch (\Throwable $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
            echo "Stack: " . $e->getTraceAsString() . "\n";
        }
        
        echo "</pre>";
        exit;
    }
    
    public function testDOCreation()
    {
        $this->requireAuth();
        
        echo "<pre>";
        echo "=== TESTING DELIVERY ORDER CREATION ===\n\n";
        
        $db = \Config\Database::connect();
        
        // Test 1: Check tables exist
        echo "1. Checking tables...\n";
        $tables = ['delivery_orders', 'delivery_order_lines', 'sales_orders', 'sales_order_lines'];
        foreach ($tables as $table) {
            $exists = $db->tableExists($table);
            echo "  - $table: " . ($exists ? 'EXISTS' : 'MISSING') . "\n";
        }
        
        // Test 2: Try to insert a DO
        echo "\n2. Testing DO insert...\n";
        try {
            $doModel = new \App\Models\DeliveryOrderModel();
            $testData = [
                'sales_order_id' => 1,
                'do_number' => 'TEST-DO-999',
                'status' => 'draft',
            ];
            echo "  Inserting: " . json_encode($testData) . "\n";
            
            $doId = $doModel->insert($testData);
            if ($doId) {
                echo "  SUCCESS: Created DO ID " . $doId . "\n";
                // Clean up
                $doModel->delete($doId);
                echo "  Cleaned up test DO\n";
            } else {
                $errors = $doModel->errors();
                echo "  FAILED: " . json_encode($errors) . "\n";
            }
        } catch (\Throwable $e) {
            echo "  EXCEPTION: " . $e->getMessage() . "\n";
        }
        
        // Test 3: Check ReadyToShipService
        echo "\n3. Testing ReadyToShipService for SO RI-S0001...\n";
        try {
            $so = $db->table('sales_orders')->where('order_number', 'RI-S0001')->get()->getRowArray();
            if ($so) {
                $soId = (int)$so['id'];
                $readyService = new \App\Services\ReadyToShipService();
                $result = $readyService->getLineReadiness($soId);
                echo "  Lines returned: " . count($result['lines'] ?? []) . "\n";
                if (!empty($result['lines'])) {
                    foreach ($result['lines'] as $line) {
                        echo "    - Line {$line['sales_order_line_id']}: ready_now={$line['ready_now']}\n";
                    }
                }
            }
        } catch (\Throwable $e) {
            echo "  EXCEPTION: " . $e->getMessage() . "\n";
        }
        
        // Test 4: Try full DO creation
        echo "\n4. Testing full DO creation for SO RI-S0001...\n";
        try {
            $so = $db->table('sales_orders')->where('order_number', 'RI-S0001')->get()->getRowArray();
            if ($so) {
                $soId = (int)$so['id'];
                $doService = new \App\Services\DeliveryOrderService();
                $doId = $doService->createDraftFromSalesOrder($soId);
                if ($doId) {
                    echo "  SUCCESS: Created DO ID " . $doId . "\n";
                } else {
                    echo "  FAILED: Service returned NULL\n";
                }
            }
        } catch (\Throwable $e) {
            echo "  EXCEPTION: " . $e->getMessage() . "\n";
            echo "  Stack: " . $e->getTraceAsString() . "\n";
        }
        
        echo "</pre>";
        exit;
    }
}
