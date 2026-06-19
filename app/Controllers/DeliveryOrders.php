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

        // Get only in-transit/completed delivery orders
        $deliveryOrders = [];
        if ($db->tableExists('delivery_orders')) {
            $dos = $doModel->select('delivery_orders.*')
                ->join('sales_orders', 'sales_orders.id = delivery_orders.sales_order_id', 'left')
                ->groupStart()
                    ->whereIn('delivery_orders.status', ['shipped', 'delivered'])
                    ->orGroupStart()
                        ->where('delivery_orders.status', 'confirmed')
                        ->groupStart()
                            ->where('delivery_orders.shipped_at IS NOT NULL', null, false)
                            ->orWhere("COALESCE(delivery_orders.tracking_number, '') !=", '')
                        ->groupEnd()
                    ->groupEnd()
                ->groupEnd()
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
                ->groupStart()
                    ->where('delivery_orders.status', 'shipped')
                    ->orGroupStart()
                        ->where('delivery_orders.status', 'confirmed')
                        ->groupStart()
                            ->where('delivery_orders.shipped_at IS NOT NULL', null, false)
                            ->orWhere("COALESCE(delivery_orders.tracking_number, '') !=", '')
                        ->groupEnd()
                    ->groupEnd()
                ->groupEnd()
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
                ->orderBy("CASE status WHEN 'delivered' THEN 1 WHEN 'shipped' THEN 2 WHEN 'confirmed' THEN 3 WHEN 'draft' THEN 4 ELSE 5 END", '', false)
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
        if (in_array($status, ['confirmed', 'shipped', 'delivered'], true) && !empty($do['updated_at'])) {
            $statusLabel = 'Delivery Confirmed';
            if ($status === 'shipped') {
                $statusLabel = 'Shipped to Customer';
            } elseif ($status === 'delivered') {
                $statusLabel = 'Delivered to Customer';
            }
            $timeline[] = [
                'label' => $statusLabel,
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

        // Generate PO number — must check BOTH purchase_orders AND purchase_rfqs
        // to avoid collisions with RFQ-reserved numbers in the shared RI-PO- sequence.
        $prefix = 'RI-PO-';
        try {
            $last = 0;
            // Highest existing PO number
            $poRow = $db->table('purchase_orders')
                ->select('po_number')
                ->like('po_number', $prefix, 'after')
                ->orderBy('po_number', 'DESC')
                ->limit(1)
                ->get()->getRowArray();
            if ($poRow && preg_match('/RI-PO-(\d+)/', $poRow['po_number'], $m)) {
                $last = max($last, (int)$m[1]);
            }
            // Also scan purchase_rfqs — RFQs share the same RI-PO- sequence
            if ($db->tableExists('purchase_rfqs')) {
                $rfqRow = $db->table('purchase_rfqs')
                    ->select('rfq_number')
                    ->like('rfq_number', $prefix, 'after')
                    ->orderBy('rfq_number', 'DESC')
                    ->limit(1)
                    ->get()->getRowArray();
                if ($rfqRow && preg_match('/RI-PO-(\d+)/', $rfqRow['rfq_number'], $m)) {
                    $last = max($last, (int)$m[1]);
                }
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

        $updateData = array_filter([
            'tracking_number' => $tracking,
            'tracking_url'    => $trackingUrl,
        ], fn($v) => $v !== null);

        // Promote to shipped once tracking exists on a confirmed delivery order.
        if (($do['status'] ?? '') === 'confirmed' && $tracking !== null) {
            $updateData['status'] = 'shipped';
            if (empty($do['shipped_at'])) {
                $updateData['shipped_at'] = date('Y-m-d H:i:s');
            }
        }

        $doModel->update($doId, $updateData);

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

        if (!in_array($do['status'] ?? '', ['confirmed', 'shipped', 'delivered'], true)) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Cannot update status of a ' . ($do['status'] ?? 'unknown') . ' delivery order']);
        }

        $body   = $this->request->getJSON(true) ?: $this->request->getPost();
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

        // If delivered, also set the main status to 'delivered'
        if ($status === 'delivered') {
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

            public function printView($doId = null)
            {
                $db = \Config\Database::connect();
                $doModel = new \App\Models\DeliveryOrderModel();
                $do = $doModel->findByPublicIdOrId($doId);
                if (!$do) {
                    return redirect()->back()->with('error', 'Delivery order not found');
                }
                $doId = (int)$do['id'];

                $customer = [];
                try {
                    $customer = $db->table('customers')->where('id', (int)($do['customer_id'] ?? 0))->get()->getRowArray() ?: [];
                } catch (\Throwable $_) {}

                $lineModel = new \App\Models\DeliveryOrderLineModel();
                $lines = $lineModel->where('delivery_order_id', $doId)->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->findAll();

                try {
                    $productIds = array_values(array_filter(array_unique(array_map(function ($line) {
                        return isset($line['product_id']) ? (int) $line['product_id'] : null;
                    }, $lines))));
                    $variantIds = array_values(array_filter(array_unique(array_map(function ($line) {
                        if (isset($line['product_variant_id']) && $line['product_variant_id']) {
                            return (int) $line['product_variant_id'];
                        }
                        return null;
                    }, $lines))));

                    $prodMap = [];
                    $variantMap = [];

                    if (!empty($productIds)) {
                        $productModel = new \App\Models\ProductModel();
                        $products = $productModel->whereIn('id', $productIds)->findAll();
                        foreach ($products as $product) {
                            $prodMap[(int) $product['id']] = $product;
                        }
                    }

                    if (!empty($variantIds) && $db->tableExists('product_variants')) {
                        try {
                            $variants = $db->table('product_variants')
                                ->select('id, product_id, art_number, name, image')
                                ->whereIn('id', $variantIds)
                                ->get()
                                ->getResultArray();
                            foreach ($variants as $variant) {
                                $variantMap[(int) $variant['id']] = $variant;
                            }
                        } catch (\Throwable $_) {}
                    }

                    foreach ($lines as &$line) {
                        $productId = isset($line['product_id']) ? (int) $line['product_id'] : null;
                        $variantId = isset($line['product_variant_id']) ? (int) $line['product_variant_id'] : null;

                        if ($productId && isset($prodMap[$productId])) {
                            $product = $prodMap[$productId];
                            $line['product_name'] = $product['name'] ?? null;
                            $line['product_code'] = $product['code'] ?? ($product['sku'] ?? null);
                            $line['product_unit'] = $product['unit'] ?? null;
                            $line['product_image'] = $product['image'] ?? null;
                            $line['product_images'] = $product['images'] ?? null;
                        }

                        if ($variantId && isset($variantMap[$variantId])) {
                            $variant = $variantMap[$variantId];
                            $line['variant_code'] = $variant['art_number'] ?? null;
                            $line['variant_name'] = $variant['name'] ?? null;
                            $line['variant_image'] = $variant['image'] ?? null;
                        }
                    }
                    unset($line);
                } catch (\Throwable $_) {}

                $company = (new \App\Models\CompanySettingsModel())->orderBy('id', 'DESC')->first() ?: [];
                $printLines = [];
                foreach ($lines as $line) {
                    if (isset($line['display_type']) && $line['display_type'] === 'section') {
                        continue;
                    }
                    $qty = (float) ($line['quantity'] ?? ($line['qty'] ?? 0));
                    $price = (float) ($line['unit_price'] ?? 0);
                    $storedTotal = isset($line['line_total']) ? (float) $line['line_total'] : 0.0;
                    $total = $storedTotal > 0 ? $storedTotal : ($qty * $price);
                    $code = trim((string) ($line['variant_code'] ?? ($line['product_code'] ?? '')));
                    $desc = trim((string) ($line['variant_name'] ?? ($line['product_name'] ?? ($line['description'] ?? ''))));
                    $unit = trim((string) ($line['product_unit'] ?? ($line['unit'] ?? '')));

                    $imgSrc = '';
                    $imageCandidates = [];
                    foreach ([
                        $line['variant_image'] ?? '',
                        $line['product_image'] ?? '',
                    ] as $imgRaw) {
                        $imgRaw = trim((string) $imgRaw);
                        if ($imgRaw === '') {
                            continue;
                        }
                        $norm = ltrim($imgRaw, '/\\');
                        $imageCandidates[] = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $norm);
                        $imageCandidates[] = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'variants' . DIRECTORY_SEPARATOR . basename($norm);
                        $imageCandidates[] = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products' . DIRECTORY_SEPARATOR . basename($norm);
                    }
                    if (empty($imageCandidates) && !empty($line['product_images'])) {
                        $images = is_string($line['product_images']) ? json_decode($line['product_images'], true) : $line['product_images'];
                        if (is_array($images) && !empty($images[0])) {
                            $norm = ltrim((string) $images[0], '/\\');
                            $imageCandidates[] = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products' . DIRECTORY_SEPARATOR . basename($norm);
                        }
                    }
                    foreach (array_unique($imageCandidates) as $abs) {
                        if (!is_file($abs)) {
                            continue;
                        }
                        $raw = @file_get_contents($abs);
                        if ($raw === false) {
                            continue;
                        }
                        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
                        $mime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'][$ext] ?? 'image/jpeg';
                        $imgSrc = 'data:' . $mime . ';base64,' . base64_encode($raw);
                        break;
                    }

                    $printLines[] = compact('code', 'desc', 'imgSrc', 'qty', 'price', 'total', 'unit');
                }

                $currency = strtoupper(trim((string) ($do['currency'] ?? 'USD')));
                $symbols = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'PKR' => '₨', 'INR' => '₹'];
                $sym = $symbols[$currency] ?? $currency;
                $fmt = fn($value) => $sym . ' ' . number_format((float) $value, 2);

                $subtotal = (float) ($do['subtotal'] ?? 0);
                $total = (float) ($do['total'] ?? 0);
                $doNumber = esc($do['do_number'] ?? ('DO-' . $doId));
                $doDate = '';
                $rawDate = trim((string) ($do['delivery_date'] ?? ($do['created_at'] ?? '')));
                if ($rawDate && strpos($rawDate, '0000') === false) {
                    $ts = strtotime($rawDate);
                    if ($ts) {
                        $doDate = date('d-m-Y', $ts);
                    }
                }
                $customerName = esc($customer['name'] ?? 'Customer');
                $companyName = esc($company['name'] ?? '');

                ob_start();
                ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
        <meta charset="utf-8">
        <title>Delivery Order <?= $doNumber ?></title>
        <style>
          *{box-sizing:border-box;margin:0;padding:0}
          body{font-family:Arial,sans-serif;font-size:12px;color:#1e293b;background:#f8fafc;padding:24px}
          .grn-doc{max-width:1100px;margin:0 auto}
          .grn-hero{background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);border-radius:.75rem .75rem 0 0;padding:1.6rem 2rem 1.4rem;color:#fff;position:relative;overflow:hidden}
          .grn-hero::after{content:'DO';position:absolute;right:-1rem;top:50%;transform:translateY(-50%);font-size:7rem;font-weight:900;opacity:.04;pointer-events:none;user-select:none;line-height:1}
          .grn-doc-type{display:inline-flex;align-items:center;gap:.4rem;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);border-radius:2rem;padding:.22rem .8rem;font-size:.7rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#93c5fd;margin-bottom:.55rem}
          .grn-hero-num{font-size:1.85rem;font-weight:800;letter-spacing:-.01em;line-height:1.1;margin-bottom:.25rem}
          .grn-hero-sub{font-size:.82rem;color:rgba(255,255,255,.72)}
          .grn-hero-actions{position:absolute;top:1.05rem;right:1.1rem;display:flex;gap:.4rem;flex-wrap:wrap;justify-content:flex-end;max-width:56%}
          .grn-hero-btn{display:inline-flex;align-items:center;gap:.34rem;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.24);border-radius:.42rem;padding:.34rem .7rem;font-size:.75rem;font-weight:700;color:rgba(255,255,255,.88);text-decoration:none;transition:background .15s,border-color .15s;cursor:pointer}
          .grn-hero-btn:hover{background:rgba(255,255,255,.18);border-color:rgba(255,255,255,.42);color:#fff}
          .grn-facts{background:#fff;border:1px solid #dee2e6;border-top:none;display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr))}
          .grn-fact{padding:.75rem 1rem;border-right:1px solid #dee2e6}.grn-fact:last-child{border-right:none}
          .grn-fact-lbl{font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:#64748b;font-weight:700;margin-bottom:.18rem}
          .grn-fact-val{font-size:.95rem;font-weight:700;color:#1e293b}
          .grn-sec{background:#fff;border:1px solid #dee2e6;border-top:none}
          .grn-sec-hd{padding:.7rem 1.3rem;border-bottom:1px solid #dee2e6;display:flex;align-items:center;gap:.55rem;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#6c757d}
          .grn-sec-badge{margin-left:auto;background:#e0e7ff;color:#3730a3;border-radius:2rem;padding:.08rem .5rem;font-size:.68rem;font-weight:700}
          .grn-body{padding:0 1.1rem 1rem}.grn-tbl{width:100%;border-collapse:collapse}
          .grn-tbl thead th{background:linear-gradient(180deg,#f8fafc 0%,#eef2f7 100%);border-bottom:2px solid #dbe5f0;padding:.72rem .65rem;text-align:left;font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:#64748b}
          .grn-tbl tbody td{padding:.75rem .65rem;border-bottom:1px solid #eef2f7;vertical-align:middle;font-size:.84rem}.grn-tbl .r{text-align:right}
          .prod-code{display:inline-flex;align-items:center;padding:.15rem .45rem;border-radius:999px;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;font-size:.72rem;font-weight:700}
          .prod-thumb{width:42px;height:42px;object-fit:contain;border:1px solid #dbe5f0;border-radius:.35rem;background:#fff}
          .no-img{font-size:.68rem;color:#94a3b8;border:1px dashed #cbd5e1;padding:.18rem .35rem;border-radius:.25rem;display:inline-block}
          .desc-main{font-weight:700;color:#1e293b;line-height:1.45}
          .totals{padding:1rem 1.1rem 1.2rem;display:flex;justify-content:flex-end;background:#fff;border:1px solid #dee2e6;border-top:none;border-radius:0 0 .75rem .75rem}
          .totals table{width:280px;border-collapse:collapse}.totals td{padding:.33rem .2rem}.totals .lbl{color:#64748b;text-align:right;padding-right:.8rem}.totals .val{text-align:right}.totals .grand td{font-size:1.08rem;font-weight:700;border-top:2px solid #1e293b;padding-top:.55rem;color:#111827}
          @media print{*{color-adjust:exact!important;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}body{padding:12mm;background:#fff!important;color:#1e293b!important}.no-print,.grn-hero-actions{display:none!important}.grn-doc{max-width:1100px!important;margin:0 auto!important}.grn-hero{background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%)!important;border-radius:.75rem!important;color:#fff!important;border:1px solid #0a0f1a!important;page-break-inside:avoid!important}.grn-hero-num,.grn-hero-sub,.grn-doc-type{color:#fff!important}.grn-doc-type{background:rgba(255,255,255,.12)!important;border:1px solid rgba(255,255,255,.18)!important;color:#93c5fd!important}.grn-facts{background:#fff!important;border:1px solid #dee2e6!important;border-radius:0!important;page-break-inside:avoid!important}.grn-fact{border-right:1px solid #dee2e6!important;background:#fff!important}.grn-fact-lbl{color:#64748b!important}.grn-fact-val{color:#1e293b!important}.grn-sec{background:#fff!important;border:1px solid #dee2e6!important;border-radius:0!important}.grn-sec-hd{background:#f8fafc!important;color:#6c757d!important;border-bottom:1px solid #dee2e6!important}.grn-sec-badge{background:#e0e7ff!important;color:#3730a3!important;border-radius:2rem!important}.grn-body{background:#fff!important}.grn-tbl{width:100%!important;border-collapse:collapse!important;page-break-inside:avoid!important}.grn-tbl thead th{background:linear-gradient(180deg,#f8fafc 0%,#eef2f7 100%)!important;border-bottom:2px solid #dbe5f0!important;color:#64748b!important;text-align:left!important}.grn-tbl tbody td{border-bottom:1px solid #eef2f7!important;color:#1e293b!important;background:#fff!important}.grn-tbl tbody tr{background:#fff!important;page-break-inside:avoid!important}.prod-code{background:#eff6ff!important;border:1px solid #bfdbfe!important;color:#1d4ed8!important;border-radius:999px!important}.prod-thumb{border:1px solid #dbe5f0!important;background:#fff!important}.no-img{color:#94a3b8!important;border:1px dashed #cbd5e1!important;background:#fff!important}.desc-main{color:#1e293b!important;font-weight:700!important}.totals{background:#fff!important;border:1px solid #dee2e6!important;border-radius:.75rem!important;display:flex!important;justify-content:flex-end!important;page-break-inside:avoid!important}.totals table{border-collapse:collapse!important;width:280px!important}.totals td{color:#1e293b!important}.totals .lbl{color:#64748b!important}.totals .grand td{color:#111827!important;border-top:2px solid #1e293b!important;font-weight:700!important}table,thead,tbody,tr,td,th{page-break-inside:avoid!important;break-inside:avoid!important}}
          @media(max-width:768px){body{padding:12px}.grn-hero{padding:1rem 1rem .9rem}.grn-hero-num{font-size:1.3rem}.grn-hero-actions{position:static;max-width:100%;margin-top:.7rem;justify-content:flex-start}.grn-facts{grid-template-columns:1fr 1fr}.grn-fact{padding:.5rem .6rem}.grn-body{padding:0}.grn-tbl{display:block;overflow-x:auto}}
        </style>
        </head>
        <body>
        <div class="grn-doc">
          <div class="grn-hero">
            <div class="grn-doc-type">Delivery Order</div>
            <div class="grn-hero-num"><?= $doNumber ?></div>
            <div class="grn-hero-sub"><?= $companyName ?></div>
            <div class="grn-hero-actions no-print">
              <button type="button" class="grn-hero-btn" onclick="window.print()">Print</button>
              <button type="button" class="grn-hero-btn" onclick="window.close()">Close</button>
            </div>
          </div>

          <div class="grn-facts">
            <div class="grn-fact"><div class="grn-fact-lbl">Customer</div><div class="grn-fact-val"><?= $customerName ?></div></div>
            <div class="grn-fact"><div class="grn-fact-lbl">Delivery Date</div><div class="grn-fact-val"><?= esc($doDate ?: '-') ?></div></div>
            <div class="grn-fact"><div class="grn-fact-lbl">Currency</div><div class="grn-fact-val"><?= esc($currency) ?></div></div>
            <div class="grn-fact"><div class="grn-fact-lbl">Lines</div><div class="grn-fact-val"><?= number_format(count($printLines), 0) ?></div></div>
          </div>

          <div class="grn-sec">
            <div class="grn-sec-hd">Delivery Lines<span class="grn-sec-badge"><?= number_format(count($printLines), 0) ?></span></div>
            <div class="grn-body">
              <table class="grn-tbl">
                <thead>
                  <tr>
                    <th style="width:13%">Code</th>
                    <th style="width:8%">Image</th>
                    <th>Description</th>
                    <th style="width:8%">Unit</th>
                    <th class="r" style="width:8%">Qty</th>
                    <th class="r" style="width:12%">Unit Price</th>
                    <th class="r" style="width:12%">Line Total</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($printLines as $line): ?>
                  <tr>
                    <td><span class="prod-code"><?= esc($line['code'] !== '' ? $line['code'] : '-') ?></span></td>
                    <td><?php if ($line['imgSrc']): ?><img class="prod-thumb" src="<?= $line['imgSrc'] ?>" alt=""><?php else: ?><span class="no-img">No Img</span><?php endif ?></td>
                    <td><div class="desc-main"><?= esc($line['desc'] !== '' ? $line['desc'] : '-') ?></div></td>
                    <td><?= esc($line['unit'] !== '' ? $line['unit'] : '-') ?></td>
                    <td class="r"><?= number_format($line['qty'], 2) ?></td>
                    <td class="r"><?= esc($fmt($line['price'])) ?></td>
                    <td class="r"><?= esc($fmt($line['total'])) ?></td>
                  </tr>
                  <?php endforeach ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="totals">
            <table>
              <tr><td class="lbl">Subtotal</td><td class="val"><?= esc($fmt($subtotal > 0 ? $subtotal : $total)) ?></td></tr>
              <tr class="grand"><td class="lbl">Total</td><td class="val"><?= esc($fmt($total)) ?></td></tr>
            </table>
          </div>
        </div>
        </body>
        </html>
                <?php
                return $this->response->setBody(ob_get_clean())->setHeader('Content-Type', 'text/html; charset=utf-8');
            }
}

