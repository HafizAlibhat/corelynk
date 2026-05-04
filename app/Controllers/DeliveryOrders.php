<?php

namespace App\Controllers;

use App\Models\SalesOrderModel;
use App\Models\DeliveryOrderModel;
use App\Models\DeliveryOrderLineModel;
use App\Models\ProductModel;
use App\Models\ProductVariantModel;
use App\Models\SalesOrderLineModel;
use App\Services\DeliveryOrderService;
use App\Models\DeliveryOrderParcelImageModel;
use App\Models\DeliveryOrderTrackingDocModel;

class DeliveryOrders extends BaseController
{
    public function index()
    {
        $this->requireAuth();

        $doModel = new DeliveryOrderModel();
        $db = \Config\Database::connect();

        // Get all delivery orders with sales order details
        $deliveryOrders = [];
        if ($db->tableExists('delivery_orders')) {
            $dos = $doModel->select('delivery_orders.*')
                ->join('sales_orders', 'sales_orders.id = delivery_orders.sales_order_id', 'left')
                ->orderBy('delivery_orders.created_at', 'DESC')
                ->findAll(100);

            foreach ($dos as $do) {
                $soId = (int)($do['sales_order_id'] ?? 0);

                // Fetch sales order + customer in one query
                $salesOrder = null;
                if ($soId > 0 && $db->tableExists('sales_orders') && $db->tableExists('customers')) {
                    $salesOrder = $db->table('sales_orders')
                        ->select('sales_orders.id, sales_orders.order_number, sales_orders.customer_id, customers.customer_code, customers.name as customer_name')
                        ->join('customers', 'customers.id = sales_orders.customer_id', 'left')
                        ->where('sales_orders.id', $soId)
                        ->get()->getRowArray();
                }

                $lineCount = $db->table('delivery_order_lines')
                    ->where('delivery_order_id', (int)$do['id'])
                    ->countAllResults();

                $deliveryOrders[] = [
                    'do_id' => (int)$do['id'],
                    'do_number' => $do['do_number'] ?? '',
                    'status' => $do['status'] ?? 'draft',
                    'delivery_status' => $do['delivery_status'] ?? null,
                    'estimated_delivery_days' => (int)($do['estimated_delivery_days'] ?? 0),
                    'shipped_at' => $do['shipped_at'] ?? null,
                    'tracking_number' => $do['tracking_number'] ?? null,
                    'destination_country' => $do['destination_country'] ?? null,
                    'created_at' => $do['created_at'] ?? '',
                    'updated_at' => $do['updated_at'] ?? '',
                    'sales_order' => $salesOrder,
                    'line_count' => $lineCount,
                ];
            }
        }

        $data = $this->setPageData([
            'page_title' => 'Delivery Orders',
            'delivery_orders' => $deliveryOrders,
        ]);

        return view('delivery_orders/index', $data);
    }

    public function delete($doId = null)
    {
        $this->requireAuth();

        $doId = (int)$doId;
        if ($doId <= 0) {
            return redirect()->to('delivery-orders')->with('error', 'Invalid delivery order.');
        }

        $doModel = new DeliveryOrderModel();
        $do = $doModel->find($doId);

        if (!$do) {
            return redirect()->to('delivery-orders')->with('error', 'Delivery order not found.');
        }

        if (($do['status'] ?? '') !== 'draft') {
            return redirect()->to('delivery-orders')->with('error', 'Only draft delivery orders can be deleted.');
        }

        $db = \Config\Database::connect();
        try {
            $db->transStart();
            $db->table('delivery_order_lines')->where('delivery_order_id', $doId)->delete();
            $doModel->delete($doId);
            $db->transComplete();

            if ($db->transStatus() === false) {
                return redirect()->to('delivery-orders')->with('error', 'Delete failed — database error.');
            }

            return redirect()->to('delivery-orders')->with('success', esc($do['do_number']) . ' deleted successfully.');
        } catch (\Throwable $e) {
            log_message('error', 'DeliveryOrders.delete: ' . $e->getMessage());
            return redirect()->to('delivery-orders')->with('error', 'Delete failed.');
        }
    }

    public function shipped()
    {
        $this->requireAuth();

        $doModel = new DeliveryOrderModel();
        $db = \Config\Database::connect();

        // Get only confirmed/shipped delivery orders
        $deliveryOrders = [];
        if ($db->tableExists('delivery_orders')) {
            $dos = $doModel->select('delivery_orders.*')
                ->join('sales_orders', 'sales_orders.id = delivery_orders.sales_order_id', 'left')
                ->whereIn('delivery_orders.status', ['confirmed', 'delivered'])
                ->orderBy('delivery_orders.updated_at', 'DESC')
                ->findAll(100);

            foreach ($dos as $do) {
                $soId = (int)($do['sales_order_id'] ?? 0);
                
                // Load sales order with customer data
                $salesOrder = null;
                if ($soId > 0) {
                    $salesOrder = $db->table('sales_orders')
                        ->select('sales_orders.*, customers.customer_code, customers.name as customer_name')
                        ->join('customers', 'customers.id = sales_orders.customer_id', 'left')
                        ->where('sales_orders.id', $soId)
                        ->get()
                        ->getRowArray();
                }

                $lineCount = $db->table('delivery_order_lines')
                    ->where('delivery_order_id', (int)$do['id'])
                    ->countAllResults();

                $deliveryOrders[] = [
                    'do_id' => (int)$do['id'],
                    'public_id' => $do['public_id'] ?? null,
                    'do_number' => $do['do_number'] ?? '',
                    'status' => $do['status'] ?? 'draft',
                    'delivery_status' => $do['delivery_status'] ?? null,
                    'estimated_delivery_days' => (int)($do['estimated_delivery_days'] ?? 0),
                    'shipped_at' => $do['shipped_at'] ?? null,
                    'tracking_number' => $do['tracking_number'] ?? null,
                    'destination_country' => $do['destination_country'] ?? null,
                    'created_at' => $do['created_at'] ?? '',
                    'updated_at' => $do['updated_at'] ?? '',
                    'sales_order' => $salesOrder,
                    'line_count' => $lineCount,
                ];
            }
        }

        $data = $this->setPageData([
            'page_title' => 'Shipped Orders',
            'delivery_orders' => $deliveryOrders,
        ]);

        return view('delivery_orders/shipped', $data);
    }

    public function pendingFollowup()
    {
        $this->requireAuth();

        $doModel = new DeliveryOrderModel();
        $db = \Config\Database::connect();

        $deliveryOrders = [];
        if ($db->tableExists('delivery_orders')) {
            $dos = $doModel->select('delivery_orders.*')
                ->where('delivery_orders.status', 'confirmed')
                ->groupStart()
                    ->where('delivery_orders.delivery_status IS NULL', null, false)
                    ->orWhere('delivery_orders.delivery_status !=', 'delivered')
                ->groupEnd()
                ->orderBy('COALESCE(delivery_orders.shipped_at, delivery_orders.updated_at)', 'ASC', false)
                ->findAll(200);

            foreach ($dos as $do) {
                $soId = (int)($do['sales_order_id'] ?? 0);

                $salesOrder = null;
                if ($soId > 0) {
                    $salesOrder = $db->table('sales_orders')
                        ->select('sales_orders.*, customers.customer_code, customers.name as customer_name')
                        ->join('customers', 'customers.id = sales_orders.customer_id', 'left')
                        ->where('sales_orders.id', $soId)
                        ->get()
                        ->getRowArray();
                }

                $lineCount = $db->table('delivery_order_lines')
                    ->where('delivery_order_id', (int)$do['id'])
                    ->countAllResults();

                $deliveryOrders[] = [
                    'do_id' => (int)$do['id'],
                    'public_id' => $do['public_id'] ?? null,
                    'do_number' => $do['do_number'] ?? '',
                    'status' => $do['status'] ?? 'draft',
                    'delivery_status' => $do['delivery_status'] ?? null,
                    'estimated_delivery_days' => (int)($do['estimated_delivery_days'] ?? 0),
                    'shipped_at' => $do['shipped_at'] ?? null,
                    'tracking_number' => $do['tracking_number'] ?? null,
                    'tracking_url' => $do['tracking_url'] ?? null,
                    'destination_country' => $do['destination_country'] ?? null,
                    'created_at' => $do['created_at'] ?? '',
                    'updated_at' => $do['updated_at'] ?? '',
                    'sales_order' => $salesOrder,
                    'line_count' => $lineCount,
                ];
            }
        }

        $data = $this->setPageData([
            'page_title' => 'Pending Shipment Follow-up',
            'delivery_orders' => $deliveryOrders,
        ]);

        return view('delivery_orders/pending_followup', $data);
    }

    private function normalizeTrackingUrl(?string $url): ?string
    {
        $url = trim((string)$url);
        if ($url === '') {
            return null;
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = ltrim($url, "/ \\t\\n\\r\\0\\x0B");
            $url = 'https://' . $url;
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    public function createFromSalesOrder($soId = null)
    {
        $this->requireAuth();

        $soId = (int)$soId;
        if (!$soId) {
            return redirect()->back()->with('error', 'Invalid sales order ID');
        }

        $soModel = new SalesOrderModel();
        $salesOrder = $soModel->find($soId);
        if (!$salesOrder) {
            return redirect()->back()->with('error', 'Sales order not found');
        }

        // Prevent duplicate drafts: if a draft DO already exists for this SO, redirect to it
        $db = \Config\Database::connect();
        if ($db->tableExists('delivery_orders')) {
            $existingDraft = $db->table('delivery_orders')
                ->where('sales_order_id', $soId)
                ->where('status', 'draft')
                ->get()->getRowArray();
            if ($existingDraft) {
                return redirect()->to('/delivery-orders/view/' . $existingDraft['id'])
                    ->with('info', 'A draft delivery order (' . esc($existingDraft['do_number']) . ') already exists for this sales order.');
            }
        }

        try {
            $doService = new DeliveryOrderService();
            $doId = $doService->createDraftFromSalesOrder($soId);

            if (!$doId) {
                return redirect()->back()->with('error', 'Failed to create delivery order. Please refresh and try again.');
            }

            return redirect()->to('/delivery-orders/view/' . $doId)->with('success', 'Delivery order draft created');
        } catch (\Throwable $e) {
            log_message('error', 'DeliveryOrders.createFromSalesOrder error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error creating delivery order: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: Return order progress partial HTML for a sales order modal.
     */
    public function orderProgress($soId = null)
    {
        $this->requireAuth();

        $soId = (int)$soId;
        if (!$soId) {
            return $this->response->setStatusCode(400)->setBody('<p class="text-danger">Invalid order ID.</p>');
        }

        $soModel = new SalesOrderModel();
        $salesOrder = $soModel->find($soId);
        if (!$salesOrder) {
            return $this->response->setStatusCode(404)->setBody('<p class="text-danger">Sales order not found.</p>');
        }

        $db = \Config\Database::connect();
        $doObj = null;
        if ($db->tableExists('delivery_orders')) {
            // Prefer confirmed/delivered over draft
            $doObj = $db->table('delivery_orders')
                ->where('sales_order_id', $soId)
                ->orderBy("CASE status WHEN 'delivered' THEN 1 WHEN 'confirmed' THEN 2 WHEN 'draft' THEN 3 ELSE 4 END", '', false)
                ->get()->getRowArray();
        }

        $timeline = $this->buildDeliveryTimeline($doObj ?? [], $salesOrder);

        return view('delivery_orders/_progress_partial', [
            'timeline'        => $timeline,
            'sales_order'     => $salesOrder,
            'delivery_order'  => $doObj,
        ]);
    }

    public function view($doId = null)
    {
        $this->requireAuth();

        $doModel = new DeliveryOrderModel();
        $doRecord = $doModel->findByPublicIdOrId($doId);
        if (!$doRecord) {
            return redirect()->back()->with('error', 'Delivery order not found');
        }
        $doId = (int)$doRecord['id'];
        $do = $doModel->getWithLines($doId);
        if (!$do) {
            return redirect()->back()->with('error', 'Delivery order not found');
        }

        $soModel = new SalesOrderModel();
        $salesOrder = $soModel->find((int)($do['sales_order_id'] ?? 0));
        if (!$salesOrder) {
            $salesOrder = null;
        }

        $productModel = new ProductModel();
        $productMap = [];
        $soLineMap = [];
        $variantMap = [];

        if (!empty($do['lines'])) {
            $productIds = array_values(array_unique(array_filter(array_map('intval', array_column($do['lines'], 'product_id')))));
            if (!empty($productIds)) {
                $products = $productModel->whereIn('id', $productIds)->findAll();
                foreach ($products as $p) {
                    $img = base_url('assets/images/no-image.png');
                    if (!empty($p['image'])) {
                        $img = base_url('/uploads/products/' . ltrim($p['image'], '/'));
                    } elseif (!empty($p['images'])) {
                        $imgs = is_string($p['images']) ? json_decode($p['images'], true) : $p['images'];
                        if (is_array($imgs) && !empty($imgs[0])) {
                            $img = base_url('/uploads/products/' . ltrim($imgs[0], '/'));
                        }
                    }
                    $productMap[(int)$p['id']] = [
                        'code' => $p['code'] ?? ($p['sku'] ?? ''),
                        'name' => $p['name'] ?? '',
                        'image_url' => $img,
                    ];
                }
            }

            $soLineIds = array_values(array_unique(array_filter(array_map('intval', array_column($do['lines'], 'sales_order_line_id')))));
            if (!empty($soLineIds)) {
                $soLineModel = new SalesOrderLineModel();
                $soLines = $soLineModel->whereIn('id', $soLineIds)->findAll();
                foreach ($soLines as $sl) {
                    $soLineMap[(int)$sl['id']] = $sl;
                }

                $variantIds = [];
                foreach ($soLines as $sl) {
                    if (!empty($sl['product_variant_id'])) {
                        $variantIds[] = (int)$sl['product_variant_id'];
                    }
                }
                $variantIds = array_values(array_unique(array_filter($variantIds)));
                if (!empty($variantIds)) {
                    $variantModel = new ProductVariantModel();
                    $variants = $variantModel->whereIn('id', $variantIds)->findAll();
                    foreach ($variants as $v) {
                        $variantMap[(int)$v['id']] = $v;
                    }
                }
            }
        }

        foreach ($do['lines'] as &$line) {
            $prodId = (int)($line['product_id'] ?? 0);
            $soLineId = (int)($line['sales_order_line_id'] ?? 0);
            $soLine = $soLineMap[$soLineId] ?? null;
            $variantId = $soLine['product_variant_id'] ?? null;
            $variant = $variantId ? ($variantMap[(int)$variantId] ?? null) : null;

            $productCode = $productMap[$prodId]['code'] ?? '';
            $productName = $productMap[$prodId]['name'] ?? '';
            $productImg = $productMap[$prodId]['image_url'] ?? base_url('assets/images/no-image.png');

            $variantCode = $variant['art_number'] ?? '';
            $variantName = $variant['name'] ?? '';
            $variantImg = !empty($variant['image'])
                ? base_url('/uploads/variants/' . ltrim($variant['image'], '/'))
                : '';

            $resolvedCode = $variantCode !== '' ? $variantCode : $productCode;
            if ($resolvedCode === '' && $prodId > 0) {
                $resolvedCode = (string)$prodId;
            }
            $line['product_code'] = $resolvedCode;
            $line['product_name'] = $variantName !== '' ? $variantName : $productName;
            $line['product_image_url'] = $variantImg !== '' ? $variantImg : $productImg;
            $line['description'] = $soLine['description'] ?? '';
        }
        unset($line);

        $timeline = $this->buildDeliveryTimeline($do, $salesOrder);

        // Load vendors and shipping services for the confirmation panel
        $db2 = \Config\Database::connect();
        $vendors = [];
        try { $vendors = $db2->table('vendors')->where('is_active', 1)->orderBy('name')->get()->getResultArray(); } catch (\Throwable $_) {}
        $shippingServices = [];
        try { $shippingServices = (new \App\Models\ShippingServiceModel())->where('active', 1)->orderBy('carrier')->orderBy('service_name')->findAll(); } catch (\Throwable $_) {}
        $countries = [];
        try { $countries = $db2->table('countries')->orderBy('name')->get()->getResultArray(); } catch (\Throwable $_) {}

        // Load vendor info if already shipped
        $shippingVendor  = null;
        $shippingService = null;
        if (!empty($do['shipping_vendor_id'])) {
            try { $shippingVendor  = $db2->table('vendors')->where('id', (int)$do['shipping_vendor_id'])->get()->getRowArray(); } catch (\Throwable $_) {}
        }
        if (!empty($do['shipping_service_id'])) {
            try { $shippingService = (new \App\Models\ShippingServiceModel())->find((int)$do['shipping_service_id']); } catch (\Throwable $_) {}
        }

        // Try to pre-fill weight from linked quotation_shipping
        $suggestedWeight = null;
        try {
            if (!empty($salesOrder['quotation_id'])) {
                $qs = $db2->table('quotation_shipping')
                    ->where('quotation_id', (int)$salesOrder['quotation_id'])
                    ->orderBy('id', 'DESC')
                    ->limit(1)
                    ->get()->getRowArray();
                if (!empty($qs['shipment_weight']) && (float)$qs['shipment_weight'] > 0) {
                    $suggestedWeight = (float)$qs['shipment_weight'];
                } elseif (!empty($qs['product_weight']) && (float)$qs['product_weight'] > 0) {
                    $suggestedWeight = (float)($qs['product_weight']) + (float)($qs['packing_weight'] ?? 0) + (float)($qs['box_weight'] ?? 0);
                }
            }
        } catch (\Throwable $_) {}

        $parcelImageModel = new DeliveryOrderParcelImageModel();
        $parcelImages = $parcelImageModel->getForDo($doId);

        $trackingDocModel = new DeliveryOrderTrackingDocModel();
        $trackingDocs = $trackingDocModel->getForDo($doId);

        $data = $this->setPageData([
            'page_title'       => 'Delivery Order - ' . esc($do['do_number'] ?? ''),
            'delivery_order'   => $do,
            'sales_order'      => $salesOrder,
            'timeline'         => $timeline,
            'vendors'          => $vendors,
            'shippingServices' => $shippingServices,
            'shippingVendor'   => $shippingVendor,
            'shippingService'  => $shippingService,
            'suggestedWeight'  => $suggestedWeight,
            'countries'        => $countries,
            'parcelImages'     => $parcelImages,
            'trackingDocs'     => $trackingDocs,
        ]);

        return view('delivery_orders/view', $data);
    }

    protected function buildDeliveryTimeline(array $do, ?array $salesOrder): array
    {
        $timeline = [];
        $db = \Config\Database::connect();
        $meta = ['source' => 'unknown']; // 'procurement' or 'in_stock'

        $soId = (int)($salesOrder['id'] ?? 0);
        if (!empty($salesOrder['created_at'])) {
            $timeline[] = [
                'label' => 'Sales Order Created',
                'time' => $salesOrder['created_at'],
                'detail' => $salesOrder['order_number'] ?? null,
            ];
        }

        $poIds = [];

        // Strategy 1: Use sales_order_line_po_map junction table
        if ($soId > 0 && $db->tableExists('sales_order_line_po_map')) {
            $poRows = $db->table('sales_order_line_po_map')
                ->distinct()
                ->select('purchase_order_id')
                ->where('sales_order_id', $soId)
                ->get()
                ->getResultArray();
            foreach ($poRows as $r) {
                if (!empty($r['purchase_order_id'])) {
                    $poIds[] = (int)$r['purchase_order_id'];
                }
            }
        }

        // Strategy 2: Match POs by product_id through PO lines
        if (empty($poIds) && $soId > 0 && $db->tableExists('sales_order_lines') && $db->tableExists('purchase_order_lines')) {
            $soProducts = $db->table('sales_order_lines')
                ->distinct()
                ->select('product_id')
                ->where('sales_order_id', $soId)
                ->get()
                ->getResultArray();
            $productIds = array_values(array_filter(array_map(fn($r) => (int)($r['product_id'] ?? 0), $soProducts)));

            if (!empty($productIds)) {
                $poRows = $db->table('purchase_order_lines')
                    ->distinct()
                    ->select('po_id')
                    ->whereIn('product_id', $productIds)
                    ->get()
                    ->getResultArray();
                foreach ($poRows as $r) {
                    if (!empty($r['po_id'])) {
                        $poIds[] = (int)$r['po_id'];
                    }
                }
                $poIds = array_unique($poIds);
            }
        }

        // ========== QUERY RFQ DATA ==========
        if (!empty($poIds) && $db->tableExists('purchase_orders') && $db->tableExists('purchase_rfqs')) {
            $rfqInfo = $db->table('purchase_rfqs')
                ->select('MIN(purchase_rfqs.created_at) as rfq_date')
                ->join('purchase_orders', 'purchase_orders.rfq_id = purchase_rfqs.id', 'inner')
                ->whereIn('purchase_orders.id', $poIds)
                ->get()
                ->getRowArray();
            
            if (!empty($rfqInfo['rfq_date'])) {
                $timeline[] = [
                    'label' => 'RFQ Created',
                    'time' => $rfqInfo['rfq_date'],
                    'detail' => 'Request for Quotation',
                ];
            }
        }

        if (!empty($poIds) && $db->tableExists('purchase_orders')) {
            $meta['source'] = 'procurement';

            $poInfo = $db->table('purchase_orders')
                ->select("MIN(COALESCE(NULLIF(order_date, '0000-00-00'), created_at)) as po_date")
                ->whereIn('id', $poIds)
                ->get()
                ->getRowArray();

            if (!empty($poInfo['po_date'])) {
                $timeline[] = [
                    'label' => 'Purchase Order Created',
                    'time' => $poInfo['po_date'],
                    'detail' => 'Local Market Source',
                ];
            }

            if ($db->tableExists('purchase_grns')) {
                $grnInfo = $db->table('purchase_grns')
                    ->select("MIN(COALESCE(received_at, created_at)) as grn_date")
                    ->whereIn('po_id', $poIds)
                    ->get()
                    ->getRowArray();

                if (!empty($grnInfo['grn_date'])) {
                    $timeline[] = [
                        'label' => 'Received at Warehouse',
                        'time' => $grnInfo['grn_date'],
                        'detail' => 'GRN Completed',
                    ];
                }
            }
        } else {
            $meta['source'] = 'in_stock';

            // In-stock route: find when stock was made available
            if ($soId > 0 && $db->tableExists('sales_order_lines') && $db->tableExists('stock_movements')) {
                $soProducts2 = $db->table('sales_order_lines')
                    ->distinct()
                    ->select('product_id, product_variant_id')
                    ->where('sales_order_id', $soId)
                    ->get()
                    ->getResultArray();

                $productIds = array_values(array_filter(array_map(fn($r) => (int)($r['product_id'] ?? 0), $soProducts2)));
                $variantIds = array_values(array_filter(array_map(fn($r) => (int)($r['product_variant_id'] ?? 0), $soProducts2)));

                if (!empty($productIds)) {
                    // Find the earliest stock entry for these products
                    $smBuilder = $db->table('stock_movements')
                        ->select('MIN(created_at) as first_stock, MAX(created_at) as last_stock')
                        ->whereIn('product_id', $productIds)
                        ->where('qty_change >', 0);

                    if (!empty($variantIds)) {
                        $smBuilder->whereIn('variant_id', $variantIds);
                    }

                    $stockInfo = $smBuilder->get()->getRowArray();

                    if (!empty($stockInfo['last_stock'])) {
                        // Determine source label from movement types
                        $mvTypes = $db->table('stock_movements')
                            ->distinct()->select('movement_type')
                            ->whereIn('product_id', $productIds)
                            ->where('qty_change >', 0)
                            ->get()->getResultArray();
                        $typeList = array_column($mvTypes, 'movement_type');

                        $sourceLabel = 'From Existing Stock';
                        if (in_array('opening_stock', $typeList)) {
                            $sourceLabel = 'Opening Stock / Adjustment';
                        } elseif (in_array('adjustment', $typeList)) {
                            $sourceLabel = 'Stock Adjustment';
                        } elseif (in_array('grn', $typeList)) {
                            $sourceLabel = 'Previous GRN Receipt';
                        }

                        $timeline[] = [
                            'label' => 'Stock Available',
                            'time' => $stockInfo['last_stock'],
                            'detail' => $sourceLabel,
                        ];
                    }
                }
            }
        }

        if (!empty($do['created_at'])) {
            $timeline[] = [
                'label' => 'Delivery Order Created',
                'time' => $do['created_at'],
                'detail' => $do['do_number'] ?? null,
            ];
        }

        $status = $do['status'] ?? 'draft';
        if (in_array($status, ['confirmed', 'delivered'], true) && !empty($do['updated_at'])) {
            $timeline[] = [
                'label' => $status === 'delivered' ? 'Delivered to Customer' : 'Delivery Confirmed',
                'time' => $do['updated_at'],
                'detail' => strtoupper($status),
            ];
        }

        // Sort by time and compute durations
        usort($timeline, function($a, $b) {
            return strtotime($a['time'] ?? '') <=> strtotime($b['time'] ?? '');
        });

        $prev = null;
        foreach ($timeline as $idx => $event) {
            $timeline[$idx]['duration_from_prev'] = null;
            if (!empty($event['time']) && !empty($prev)) {
                $timeline[$idx]['duration_from_prev'] = $this->formatDuration($prev, $event['time']);
            }
            $prev = $event['time'] ?? $prev;
        }

        // Attach meta
        $timeline['_meta'] = $meta;

        return $timeline;
    }

    protected function formatDuration(string $from, string $to): string
    {
        $fromTs = strtotime($from);
        $toTs = strtotime($to);
        if (!$fromTs || !$toTs || $toTs <= $fromTs) {
            return '';
        }

        $diff = $toTs - $fromTs;
        $days = intdiv($diff, 86400);
        $hours = intdiv($diff % 86400, 3600);
        $minutes = intdiv($diff % 3600, 60);

        $parts = [];
        if ($days > 0) $parts[] = $days . 'd';
        if ($hours > 0) $parts[] = $hours . 'h';
        if ($minutes > 0 && $days === 0) $parts[] = $minutes . 'm';

        return implode(' ', $parts);
    }

    public function updateQty($doLineId = null)
    {
        $this->requireAuth();

        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'AJAX only']);
        }

        $doLineId = (int)$doLineId;
        $qtyToShip = (float)($this->request->getJSON()->qty_to_ship ?? 0);

        if (!$doLineId) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Invalid line ID']);
        }

        try {
            $doService = new DeliveryOrderService();
            $validation = $doService->validateQtyToShip($doLineId, $qtyToShip);
            if (!$validation['success']) {
                return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => $validation['message']]);
            }

            if (!$doService->updateQtyToShip($doLineId, $qtyToShip)) {
                return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Failed to update quantity']);
            }

            return $this->response->setJSON(['success' => true, 'message' => 'Quantity updated']);
        } catch (\Throwable $e) {
            log_message('error', 'DeliveryOrders.updateQty error: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Server error']);
        }
    }

    public function confirm($doId = null)
    {
        $this->requireAuth();

        $doModel = new DeliveryOrderModel();
        $doRecord = $doModel->findByPublicIdOrId($doId);
        if (!$doRecord) {
            return redirect()->back()->with('error', 'Delivery order not found');
        }
        $doId = (int)$doRecord['id'];
        $do = $doModel->getWithLines($doId);
        if (!$do) {
            return redirect()->back()->with('error', 'Delivery order not found');
        }

        if (($do['status'] ?? '') !== 'draft') {
            return redirect()->to('/delivery-orders/view/' . $doId)->with('error', 'This delivery order is already confirmed.');
        }

        // Collect shipping details from POST
        $vendorId   = (int)($this->request->getPost('shipping_vendor_id') ?? 0) ?: null;
        $serviceId  = (int)($this->request->getPost('shipping_service_id') ?? 0) ?: null;
        $weightKg   = strlen(trim((string)($this->request->getPost('final_weight_kg') ?? ''))) ? (float)$this->request->getPost('final_weight_kg') : null;
        $costPkr    = strlen(trim((string)($this->request->getPost('shipping_cost_pkr') ?? ''))) ? (float)$this->request->getPost('shipping_cost_pkr') : null;
        $tracking   = trim($this->request->getPost('tracking_number') ?? '') ?: null;
        $trackingUrlRaw = trim($this->request->getPost('tracking_url') ?? '');
        $trackingUrl = $this->normalizeTrackingUrl($trackingUrlRaw);
        if ($trackingUrlRaw !== '' && $trackingUrl === null) {
            return redirect()->back()->with('error', 'Invalid tracking URL. Use a valid link like https://ups.com/...');
        }
        $destCountry = trim($this->request->getPost('destination_country') ?? '') ?: null;
        $notes      = trim($this->request->getPost('shipping_notes') ?? '') ?: null;
        $estDays    = (int)($this->request->getPost('estimated_delivery_days') ?? 0) ?: null;

        try {
            $doService = new DeliveryOrderService();
            $result = $doService->confirm($doId);

            if (!$result['success']) {
                return redirect()->back()->with('error', $result['message']);
            }

            // Save shipping details
            $doModel->update($doId, array_filter([
                'shipping_vendor_id'      => $vendorId,
                'shipping_service_id'     => $serviceId,
                'final_weight_kg'         => $weightKg,
                'shipping_cost_pkr'       => $costPkr,
                'tracking_number'         => $tracking,
                'tracking_url'            => $trackingUrl,
                'destination_country'     => $destCountry,
                'shipping_notes'          => $notes,
                'shipped_at'              => date('Y-m-d H:i:s'),
                'estimated_delivery_days' => $estDays,
            ], fn($v) => $v !== null));

            // Handle multiple parcel image uploads
            $uploadDir = FCPATH . 'uploads/parcels';
            if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }
            $parcelFiles = $this->request->getFileMultiple('parcel_images');
            if ($parcelFiles) {
                $allowed = ['image/jpeg', 'image/png', 'image/webp'];
                $imgModel = new DeliveryOrderParcelImageModel();
                foreach ($parcelFiles as $pf) {
                    if (!$pf || !$pf->isValid() || $pf->hasMoved()) continue;
                    if ($pf->getSize() > 5 * 1024 * 1024) continue;
                    if (!in_array($pf->getMimeType(), $allowed)) continue;
                    $newName = 'parcel_do' . $doId . '_' . time() . mt_rand(100, 999) . '.' . $pf->getExtension();
                    $pf->move($uploadDir, $newName);
                    $imgModel->insert(['delivery_order_id' => $doId, 'image_path' => 'uploads/parcels/' . $newName, 'created_at' => date('Y-m-d H:i:s')]);
                }
            }

            // ── Auto-create Shipping PO + Bill ──
            $autoConfirmPo = $this->request->getPost('auto_confirm_po') ? true : false;
            $autoCreateBill = $this->request->getPost('auto_create_bill') ? true : false;

            if ($vendorId && $costPkr && $costPkr > 0) {
                try {
                    $shippingPoId = $this->createShippingPO($doId, $vendorId, $serviceId, $costPkr, $autoConfirmPo);
                    if ($shippingPoId) {
                        $doModel->update($doId, ['shipping_po_id' => $shippingPoId]);

                        if ($autoConfirmPo && $autoCreateBill) {
                            $shippingBillId = $this->createShippingBill($shippingPoId, $vendorId, $costPkr, $doId);
                            if ($shippingBillId) {
                                $doModel->update($doId, ['shipping_bill_id' => $shippingBillId]);
                            }
                        }
                    }
                } catch (\Throwable $poErr) {
                    log_message('error', 'Shipping PO/Bill creation error: ' . $poErr->getMessage());
                    // Don't fail the whole confirm — PO/Bill can be created manually later
                }
            }

            return redirect()->to('/delivery-orders/view/' . $doId)->with('success', 'Shipment confirmed successfully.');
        } catch (\Throwable $e) {
            log_message('error', 'DeliveryOrders.confirm error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Confirmation failed: ' . $e->getMessage());
        }
    }

    /**
     * Create a shipping PO for a delivery order.
     */
    private function createShippingPO(int $doId, int $vendorId, ?int $serviceId, float $costPkr, bool $autoConfirm): ?int
    {
        $db = \Config\Database::connect();

        // Generate PO number
        $prefix = 'RI-PO-';
        try {
            $row = $db->table('purchase_orders')
                ->select('po_number')
                ->like('po_number', $prefix, 'after')
                ->orderBy('id', 'DESC')
                ->limit(1)
                ->get()->getRowArray();
            $last = 0;
            if ($row && preg_match('/RI-PO-(\d+)/', $row['po_number'], $m)) {
                $last = (int)$m[1];
            }
            $poNumber = $prefix . str_pad((string)($last + 1), 4, '0', STR_PAD_LEFT);
        } catch (\Throwable $_) {
            $poNumber = $prefix . date('ymdHis');
        }

        // Build description from service; also get product_id if available
        $description = 'Shipping Service';
        $serviceProductId = null;
        if ($serviceId) {
            $svc = $db->table('shipping_services')->where('id', $serviceId)->get()->getRowArray();
            if ($svc) {
                $description = trim(($svc['carrier'] ?? '') . ' - ' . ($svc['service_name'] ?? ''));
                $serviceProductId = !empty($svc['product_id']) ? (int)$svc['product_id'] : null;
            }
        }

        $poModel = new \App\Models\PurchaseOrderModel();
        $poId = $poModel->insert([
            'po_number'     => $poNumber,
            'rfq_id'        => null,
            'vendor_id'     => $vendorId,
            'order_date'    => date('Y-m-d'),
            'delivery_date' => date('Y-m-d'),
            'status'        => $autoConfirm ? 'confirmed' : 'draft',
            'currency_code' => 'PKR',
            'subtotal'      => $costPkr,
            'tax_total'     => 0,
            'total'         => $costPkr,
            'created_by'    => session()->get('user_id') ?? null,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ], true);

        if (!$poId) return null;

        // Create single PO line for shipping service
        $lineModel = new \App\Models\PurchaseOrderLineModel();
        $lineModel->insert([
            'po_id'      => $poId,
            'product_id' => $serviceProductId,
            'variant_id' => null,
            'description'=> 'Shipping: ' . $description . ' (DO #' . $doId . ')',
            'qty'        => 1,
            'unit_price' => $costPkr,
            'line_total' => $costPkr,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        log_message('info', "Shipping PO created: po_id={$poId}, po_number={$poNumber}, do_id={$doId}, vendor={$vendorId}, amount={$costPkr}");
        return (int)$poId;
    }

    /**
     * Create a vendor bill against a shipping PO and post journal entry.
     */
    private function createShippingBill(int $poId, int $vendorId, float $costPkr, int $doId): ?int
    {
        $db = \Config\Database::connect();

        // Find PO line
        $poLine = $db->table('purchase_order_lines')->where('po_id', $poId)->get()->getRowArray();

        $billModel = new \App\Models\VendorBillModel();

        // Find Shipping Expense account
        $expenseAcct = $db->table('accounts')->where('code', '6100')->get()->getRowArray();
        $expenseAccountId = $expenseAcct ? (int)$expenseAcct['id'] : null;

        // Find Accounts Payable account
        $apAcct = $db->table('accounts')
            ->groupStart()
                ->where('code', '2000')
                ->orWhere('code', '2100')
                ->orLike('name', 'accounts payable', 'both')
            ->groupEnd()
            ->get()->getRowArray();
        $apAccountId = $apAcct ? (int)$apAcct['id'] : null;

        $billId = $billModel->insert([
            'vendor_id'          => $vendorId,
            'po_id'              => $poId,
            'bill_date'          => date('Y-m-d'),
            'total_amount'       => $costPkr,
            'balance'            => $costPkr,
            'status'             => 'confirmed',
            'based_on'           => 'po_qty',
            'currency_code'      => 'PKR',
            'notes'              => 'Shipping bill for DO #' . $doId,
            'created_by'         => session()->get('user_id') ?? null,
            'created_at'         => date('Y-m-d H:i:s'),
        ], true);

        if (!$billId) return null;

        // Create bill line
        if ($poLine) {
            $billLineModel = new \App\Models\VendorBillLineModel();
            $billLineModel->insert([
                'vendor_bill_id' => $billId,
                'po_line_id'     => $poLine['id'],
                'product_id'     => $poLine['product_id'] ?? null,
                'qty'            => 1,
                'unit_price'     => $costPkr,
                'line_total'     => $costPkr,
                'created_at'     => date('Y-m-d H:i:s'),
            ]);
        }

        // Post journal entry (Debit: Shipping Expense, Credit: AP)
        if ($expenseAccountId && $apAccountId) {
            try {
                $jeModel = new \App\Models\Accounting\JournalEntryModel();
                $jlModel = new \App\Models\Accounting\JournalLineModel();

                $entryId = $jeModel->insert([
                    'entry_date'    => date('Y-m-d'),
                    'memo'          => 'Shipping expense for DO #' . $doId . ' (Bill #' . $billId . ')',
                    'currency_code' => 'PKR',
                    'total_debits'  => $costPkr,
                    'total_credits' => $costPkr,
                    'source_type'   => 'vendor_bill',
                    'source_id'     => $billId,
                ], true);

                if ($entryId) {
                    // Debit Shipping Expense
                    $jlModel->insert([
                        'entry_id'    => $entryId,
                        'account_id'  => $expenseAccountId,
                        'description' => 'Shipping expense - DO #' . $doId,
                        'debit'       => $costPkr,
                        'credit'      => 0,
                    ]);
                    // Credit Accounts Payable
                    $jlModel->insert([
                        'entry_id'    => $entryId,
                        'account_id'  => $apAccountId,
                        'description' => 'Shipping payable - DO #' . $doId,
                        'debit'       => 0,
                        'credit'      => $costPkr,
                    ]);

                    // Link journal entry to bill
                    $billModel->update($billId, ['posted_entry_id' => $entryId]);
                }
            } catch (\Throwable $je) {
                log_message('error', 'Shipping journal entry error: ' . $je->getMessage());
            }
        }

        log_message('info', "Shipping bill created: bill_id={$billId}, po_id={$poId}, do_id={$doId}, amount={$costPkr}");
        return (int)$billId;
    }

    /**
     * POST delivery-orders/add-tracking/{doId}
     * AJAX — saves tracking number and URL on an already-confirmed DO.
     */
    public function addTracking($doId = null)
    {
        $this->requireAuth();

        $doModel = new DeliveryOrderModel();
        $do = $doModel->findByPublicIdOrId($doId);
        if (!$do) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Not found']);
        }
        $doId = (int)$do['id'];

        $body    = $this->request->getJSON(true) ?: $this->request->getPost();
        $tracking    = trim($body['tracking_number'] ?? '') ?: null;
        $trackingUrlRaw = trim($body['tracking_url'] ?? '');
        $trackingUrl = $this->normalizeTrackingUrl($trackingUrlRaw);
        if ($trackingUrlRaw !== '' && $trackingUrl === null) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Invalid tracking URL']);
        }

        $doModel->update($doId, array_filter([
            'tracking_number' => $tracking,
            'tracking_url'    => $trackingUrl,
        ], fn($v) => $v !== null));

        return $this->response->setJSON(['success' => true, 'message' => 'Tracking saved']);
    }

    /**
     * POST delivery-orders/update-delivery-status/{doId}
     * AJAX — updates the delivery status of a confirmed DO.
     */
    public function updateDeliveryStatus($doId = null)
    {
        $this->requireAuth();

        $doModel = new DeliveryOrderModel();
        $do = $doModel->findByPublicIdOrId($doId);
        if (!$do) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Not found']);
        }
        $doId = (int)$do['id'];

        if (!in_array($do['status'] ?? '', ['confirmed', 'delivered'], true)) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Cannot update status of a ' . ($do['status'] ?? 'unknown') . ' delivery order']);
        }

        // Support both JSON payloads and multipart form-data safely.
        $body = $this->request->getPost() ?: [];
        $contentType = strtolower((string)$this->request->getHeaderLine('Content-Type'));
        if (strpos($contentType, 'application/json') !== false) {
            try {
                $jsonBody = $this->request->getJSON(true);
                if (is_array($jsonBody)) {
                    $body = array_merge($body, $jsonBody);
                }
            } catch (\Throwable $e) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'Invalid JSON payload',
                ]);
            }
        }
        $status = trim($body['delivery_status'] ?? '');
        $notes  = trim($body['delivery_notes'] ?? '');

        $allowed = ['delivered', 'lost', 'customer_refused', 'damaged_in_transit', 'returned_to_sender', 'delayed', 'partial_delivery'];
        if (!in_array($status, $allowed, true)) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Invalid delivery status']);
        }

        $updateData = [
            'delivery_status'       => $status,
            'delivery_notes'        => $notes ?: null,
            'delivery_confirmed_at' => date('Y-m-d H:i:s'),
        ];

        // Handle delivered_at date (only for 'delivered' status)
        if ($status === 'delivered') {
            $deliveredAt = trim($body['delivered_at'] ?? '');
            if ($deliveredAt !== '') {
                // Validate date format YYYY-MM-DD and not in the future
                $ts = strtotime($deliveredAt);
                if ($ts === false || $ts > strtotime(date('Y-m-d') . ' 23:59:59')) {
                    return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Invalid delivery date. Date cannot be in the future.']);
                }
                $updateData['delivered_at'] = date('Y-m-d', $ts);
            } else {
                // Default to today if not provided
                $updateData['delivered_at'] = date('Y-m-d');
            }

            // Handle screenshot upload
            $screenshotFile = $this->request->getFile('delivery_screenshot');
            if ($screenshotFile && $screenshotFile->isValid() && !$screenshotFile->hasMoved()) {
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($screenshotFile->getMimeType(), $allowedTypes)) {
                    return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Screenshot must be an image file (JPG, PNG, GIF, WEBP)']);
                }
                if ($screenshotFile->getSize() > 5 * 1024 * 1024) {
                    return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Screenshot file size must be under 5MB']);
                }
                $uploadDir = FCPATH . 'uploads/delivery-screenshots';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $newName = 'ds_' . $doId . '_' . time() . '.' . $screenshotFile->getClientExtension();
                $screenshotFile->move($uploadDir, $newName);
                $updateData['delivery_screenshot'] = 'uploads/delivery-screenshots/' . $newName;
            }

            // Set the main status to 'delivered'
            $updateData['status'] = 'delivered';

            // Update SO status to delivered
            $soId = (int)($do['sales_order_id'] ?? 0);
            if ($soId > 0) {
                $db = \Config\Database::connect();
                $db->table('sales_orders')->where('id', $soId)->update([
                    'status'     => 'delivered',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        $doModel->update($doId, $updateData);

        return $this->response->setJSON(['success' => true, 'message' => 'Delivery status updated to: ' . ucfirst(str_replace('_', ' ', $status))]);
    }

    /**
     * POST delivery-orders/update-estimated-days/{doId}
     * AJAX — updates estimated delivery days on a confirmed DO.
     */
    public function updateEstimatedDays($doId = null)
    {
        $this->requireAuth();

        $doModel = new DeliveryOrderModel();
        $do = $doModel->findByPublicIdOrId($doId);
        if (!$do) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Not found']);
        }
        $doId = (int)$do['id'];

        $body = $this->request->getJSON(true) ?: $this->request->getPost();
        $days = (int)($body['estimated_delivery_days'] ?? 0);

        if ($days < 1 || $days > 365) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Days must be between 1 and 365']);
        }

        $doModel->update($doId, ['estimated_delivery_days' => $days]);

        return $this->response->setJSON(['success' => true, 'message' => 'Estimated delivery days updated']);
    }

    /**
     * POST delivery-orders/upload-parcel-image/{doId}
     * AJAX — uploads or replaces the parcel image on a confirmed DO.
     */
    /**
     * POST delivery-orders/upload-parcel-image/{doId}
     * AJAX — uploads one or more parcel images.
     */
    public function uploadParcelImage($doId = null)
    {
        $this->requireAuth();

        $doModel = new DeliveryOrderModel();
        $doRecord = $doModel->findByPublicIdOrId($doId);
        if (!$doRecord) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Not found']);
        }
        $doId = (int)$doRecord['id'];

        $files = $this->request->getFileMultiple('parcel_images') ?: [];
        if (empty($files)) {
            $single = $this->request->getFile('parcel_images');
            if ($single) $files = [$single];
        }

        $allowed   = ['image/jpeg', 'image/png', 'image/webp'];
        $uploadDir = FCPATH . 'uploads/parcels';
        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }

        $imgModel = new DeliveryOrderParcelImageModel();
        $uploaded = [];
        $errors   = [];

        foreach ($files as $file) {
            if (!$file || !$file->isValid() || $file->hasMoved()) continue;
            if ($file->getSize() > 5 * 1024 * 1024) { $errors[] = $file->getClientName() . ': over 5 MB'; continue; }
            if (!in_array($file->getMimeType(), $allowed)) { $errors[] = $file->getClientName() . ': invalid type'; continue; }
            $newName = 'parcel_do' . $doId . '_' . time() . mt_rand(100, 999) . '.' . $file->getExtension();
            $file->move($uploadDir, $newName);
            $path = 'uploads/parcels/' . $newName;
            $imgModel->insert(['delivery_order_id' => $doId, 'image_path' => $path, 'created_at' => date('Y-m-d H:i:s')]);
            $uploaded[] = ['id' => (int)$imgModel->getInsertID(), 'url' => base_url($path)];
        }

        if (empty($uploaded)) {
            $msg = empty($errors) ? 'No valid images received' : implode('; ', $errors);
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => $msg]);
        }

        return $this->response->setJSON([
            'success'  => true,
            'message'  => count($uploaded) . ' image(s) uploaded',
            'uploaded' => $uploaded,
            'errors'   => $errors,
        ]);
    }

    /**
     * POST delivery-orders/delete-parcel-image/{imageId}
     * AJAX — deletes a single parcel image by its row ID.
     */
    public function deleteParcelImage($imageId = null)
    {
        $this->requireAuth();

        $imageId = (int)$imageId;
        if (!$imageId) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Invalid ID']);
        }

        $imgModel = new DeliveryOrderParcelImageModel();
        $img = $imgModel->find($imageId);
        if (!$img) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Image not found']);
        }

        $filePath = FCPATH . ltrim($img['image_path'], '/');
        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        $imgModel->delete($imageId);

        return $this->response->setJSON(['success' => true, 'message' => 'Image deleted']);
    }

    /**
     * POST delivery-orders/upload-tracking-doc/{doId}
     * AJAX — uploads one or more tracking documents (images or PDFs).
     */
    public function uploadTrackingDoc($doId = null)
    {
        $this->requireAuth();

        $doModel = new DeliveryOrderModel();
        $doRecord = $doModel->findByPublicIdOrId($doId);
        if (!$doRecord) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Not found']);
        }
        $doId = (int)$doRecord['id'];

        $files = $this->request->getFileMultiple('tracking_docs') ?: [];
        if (empty($files)) {
            $single = $this->request->getFile('tracking_docs');
            if ($single) $files = [$single];
        }

        $allowed   = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        $uploadDir = FCPATH . 'uploads/tracking-docs';
        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }

        $docModel = new DeliveryOrderTrackingDocModel();
        $uploaded = [];
        $errors   = [];

        foreach ($files as $file) {
            if (!$file || !$file->isValid() || $file->hasMoved()) continue;
            if ($file->getSize() > 10 * 1024 * 1024) { $errors[] = $file->getClientName() . ': over 10 MB'; continue; }
            if (!in_array($file->getMimeType(), $allowed)) { $errors[] = $file->getClientName() . ': invalid type (images and PDFs only)'; continue; }
            $origName = $file->getClientName();
            $newName  = 'trk_do' . $doId . '_' . time() . mt_rand(100, 999) . '.' . $file->getExtension();
            $file->move($uploadDir, $newName);
            $path = 'uploads/tracking-docs/' . $newName;
            $docModel->insert([
                'delivery_order_id' => $doId,
                'file_path'         => $path,
                'original_name'     => $origName,
                'created_at'        => date('Y-m-d H:i:s'),
            ]);
            $uploaded[] = [
                'id'            => (int)$docModel->getInsertID(),
                'url'           => base_url($path),
                'original_name' => $origName,
                'is_image'      => str_starts_with($file->getMimeType() ?: '', 'image/'),
            ];
        }

        if (empty($uploaded)) {
            $msg = empty($errors) ? 'No valid files received' : implode('; ', $errors);
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => $msg]);
        }

        return $this->response->setJSON([
            'success'  => true,
            'message'  => count($uploaded) . ' file(s) uploaded',
            'uploaded' => $uploaded,
            'errors'   => $errors,
        ]);
    }

    /**
     * POST delivery-orders/delete-tracking-doc/{docId}
     * AJAX — deletes a single tracking document.
     */
    public function deleteTrackingDoc($docId = null)
    {
        $this->requireAuth();

        $docId = (int)$docId;
        if (!$docId) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Invalid ID']);
        }

        $docModel = new DeliveryOrderTrackingDocModel();
        $doc = $docModel->find($docId);
        if (!$doc) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Document not found']);
        }

        $filePath = FCPATH . ltrim($doc['file_path'], '/');
        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        $docModel->delete($docId);

        return $this->response->setJSON(['success' => true, 'message' => 'Document deleted']);
    }

    /**
     * POST delivery-orders/quick-vendor
     * AJAX — creates a vendor on-the-fly and returns the new ID + name.
     */
    public function quickVendor()
    {
        $this->requireAuth();

        $body = $this->request->getJSON(true) ?: $this->request->getPost();
        $name = trim($body['name'] ?? '');
        if ($name === '') {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Vendor name is required']);
        }

        try {
            $db = \Config\Database::connect();
            // Generate a simple vendor code
            $count = (int)$db->table('vendors')->countAllResults() + 1;
            $code  = 'VEN-' . str_pad($count, 3, '0', STR_PAD_LEFT);
            // Ensure unique
            while ($db->table('vendors')->where('vendor_code', $code)->countAllResults() > 0) {
                $count++;
                $code = 'VEN-' . str_pad($count, 3, '0', STR_PAD_LEFT);
            }
            $db->table('vendors')->insert([
                'vendor_code'    => $code,
                'name'           => $name,
                'contact_person' => trim($body['contact_person'] ?? '') ?: null,
                'phone'          => trim($body['phone'] ?? '') ?: null,
                'is_active'      => 1,
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);
            $newId = $db->insertID();
            return $this->response->setJSON(['success' => true, 'id' => $newId, 'name' => $name, 'vendor_code' => $code]);
        } catch (\Throwable $e) {
            log_message('error', 'DeliveryOrders.quickVendor: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Failed to create vendor']);
        }
    }

    /**
     * POST delivery-orders/quick-service
     * AJAX — creates a shipping service on-the-fly and returns ID + details.
     */
    public function quickService()
    {
        $this->requireAuth();

        $body = $this->request->getJSON(true) ?: $this->request->getPost();
        $carrier      = trim($body['carrier'] ?? '');
        $serviceName  = trim($body['service_name'] ?? '');
        $vendorId     = (int)($body['vendor_id'] ?? 0) ?: null;
        $costPkr      = strlen(trim((string)($body['cost_pkr'] ?? ''))) ? (float)$body['cost_pkr'] : 0;
        $baseRatePkr  = (float)($body['base_rate_pkr'] ?? 0);
        $ratePerKgPkr = (float)($body['rate_per_kg_pkr'] ?? 0);

        if ($carrier === '' || $serviceName === '') {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Carrier and service name are required']);
        }

        try {
            $model = new \App\Models\ShippingServiceModel();
            $model->insert([
                'carrier'         => $carrier,
                'vendor_id'       => $vendorId,
                'service_name'    => $serviceName,
                'min_weight'      => 0,
                'base_rate'       => 0,
                'rate_per_kg'     => 0,
                'cost_pkr'        => $costPkr,
                'base_rate_pkr'   => $baseRatePkr,
                'rate_per_kg_pkr' => $ratePerKgPkr,
                'currency'        => 'PKR',
                'active'          => 1,
                'created_at'      => date('Y-m-d H:i:s'),
            ]);
            $newId = $model->getInsertID();

            // Auto-create a linked product of type 'service'
            $productId = null;
            try {
                $db = \Config\Database::connect();
                // Generate sequential SRV-XXXXX code
                $lastSrv = $db->table('products')
                    ->select('code')
                    ->like('code', 'SRV-', 'after')
                    ->orderBy('id', 'DESC')
                    ->limit(1)->get()->getRowArray();
                $nextNum = 1;
                if ($lastSrv && preg_match('/SRV-(\d+)/', $lastSrv['code'], $m)) {
                    $nextNum = (int)$m[1] + 1;
                }
                $productCode = 'SRV-' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);
                $productBase = $costPkr ?: ($baseRatePkr ?: 0);

                // Use raw DB insert to bypass model validation quirks
                $db->table('products')->insert([
                    'name'          => $carrier . ' — ' . $serviceName,
                    'code'          => $productCode,
                    'product_type'  => 'simple',
                    'detailed_type' => 'service',
                    'vendor_id'     => $vendorId ?: null,
                    'vendor_price'  => $productBase ?: null,
                    'unit'          => 'SHP',
                    'is_active'     => 1,
                ]);
                $productId = $db->insertID();

                if ($productId) {
                    $db->table('shipping_services')
                        ->where('id', $newId)
                        ->update(['product_id' => $productId]);
                }
            } catch (\Throwable $pe) {
                log_message('warning', 'quickService: product auto-create failed: ' . $pe->getMessage());
            }

            return $this->response->setJSON([
                'success'         => true,
                'id'              => $newId,
                'carrier'         => $carrier,
                'service_name'    => $serviceName,
                'cost_pkr'        => $costPkr,
                'base_rate_pkr'   => $baseRatePkr,
                'rate_per_kg_pkr' => $ratePerKgPkr,
                'vendor_id'       => $vendorId,
                'product_id'      => $productId,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'DeliveryOrders.quickService: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Failed to create service']);
        }
    }

    /**
     * POST delivery-orders/create-shipping-po/:doId
     * Manual creation of Shipping PO + Bill for an already-confirmed DO.
     */
    public function createShippingPoManual($doId = null)
    {
        $this->requireAuth();

        $doModel = new \App\Models\DeliveryOrderModel();
        $do = $doModel->findByPublicIdOrId($doId);
        if (!$do) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Delivery order not found']);
        }
        $doId = (int)$do['id'];
        if (empty($do['shipping_vendor_id'])) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'No shipping vendor set on this delivery order']);
        }
        if (!empty($do['shipping_po_id'])) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Shipping PO already exists for this delivery order']);
        }

        $vendorId  = (int)$do['shipping_vendor_id'];
        $serviceId = !empty($do['shipping_service_id']) ? (int)$do['shipping_service_id'] : null;
        $costPkr   = (float)($do['shipping_cost_pkr'] ?? 0);

        try {
            $poId = $this->createShippingPO($doId, $vendorId, $serviceId, $costPkr, true);
            if (!$poId) {
                return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Failed to create Shipping PO']);
            }
            $doModel->update($doId, ['shipping_po_id' => $poId]);

            $billId = $this->createShippingBill($poId, $vendorId, $costPkr, $doId);
            if ($billId) {
                $doModel->update($doId, ['shipping_bill_id' => $billId]);
            }

            return $this->response->setJSON(['success' => true, 'po_id' => $poId, 'bill_id' => $billId]);
        } catch (\Throwable $e) {
            log_message('error', 'DeliveryOrders.createShippingPoManual: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }
}

