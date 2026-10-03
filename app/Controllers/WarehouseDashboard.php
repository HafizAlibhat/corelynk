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


    // Batch 2A: debugFulfillment(), testReadyToShip() and testDOCreation() were
    // removed here. They were routed as GET endpoints and echoed raw diagnostics,
    // and testDOCreation() mutated production data: it inserted a delivery order
    // 'TEST-DO-999' against sales order id 1, and then called
    // DeliveryOrderService::createDraftFromSalesOrder() on the hardcoded
    // production sales order RI-S0001 WITHOUT cleaning it up, creating a real
    // commercial document. A GET endpoint must never create a business record --
    // it is reachable by a link prefetcher or crawler.
    //
    // They were nominally gated behind ENVIRONMENT !== 'production', but this
    // deployment runs CI_ENVIRONMENT = development against the live business
    // database, so the gate provided no protection in practice.
    //
    // No surviving delivery order is attributable to them: all 24 rows have
    // lines and coherent sales-order links, and the only draft (DO-038) belongs
    // to sales order 29, not to the hardcoded RI-S0001.
}
