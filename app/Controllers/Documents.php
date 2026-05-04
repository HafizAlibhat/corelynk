<?php

namespace App\Controllers;

use App\Models\QuotationModel;
use App\Models\SalesOrderModel;
use App\Services\SearchService;

class Documents extends BaseController
{
    /**
     * Unified list of sales documents (Quotations + Sales Orders)
     */
    public function index()
    {
        $quotationModel = new QuotationModel();
        $salesOrderModel = new SalesOrderModel();
        $searchTerm = $this->request->getGet('q') ?? $this->request->getGet('search');

        // Fetch latest docs (keep it lightweight; can be paginated later)
        // Hide quotations that were already converted to a sales order
        $db = \Config\Database::connect();
        $qCols = [];
        try { $qCols = $db->getFieldNames('quotations'); } catch (\Throwable $_) { $qCols = []; }

        if (in_array('converted_to_sales_order_id', $qCols, true)) {
            $quotationModel->where('converted_to_sales_order_id IS NULL', null, false);
        }

        // Privacy filtering: exclude documents from users who have hidden their docs
        $currentUserId = (int) (session()->get('user_id') ?? 0);
        $isAdmin = service('policy')->isAdmin();
        $privateUserIds = (new \App\Libraries\RoleDataAccess())->getPrivateUserIds($currentUserId, $isAdmin);
        if (!empty($privateUserIds)) {
            try {
                if (in_array('created_by', $db->getFieldNames('quotations') ?: [], true)) {
                    $quotationModel->whereNotIn('created_by', $privateUserIds);
                }
            } catch (\Throwable $_) {}
            try {
                if (in_array('created_by', $db->getFieldNames('sales_orders') ?: [], true)) {
                    $salesOrderModel->whereNotIn('created_by', $privateUserIds);
                }
            } catch (\Throwable $_) {}
        }

        $quotes = $quotationModel->orderBy('id', 'desc')->findAll(200);
        $orders = $salesOrderModel->orderBy('id', 'desc')->findAll(200);

        // Preload customer names and a representative product name for each document
        $db = \Config\Database::connect();
        $customerIds = [];
        $orderIds = [];
        $quoteIds = [];
        foreach ($quotes as $q) { $quoteIds[] = (int)($q['id'] ?? 0); if (!empty($q['customer_id'])) $customerIds[] = (int)$q['customer_id']; }
        foreach ($orders as $o) { $orderIds[] = (int)($o['id'] ?? 0); if (!empty($o['customer_id'])) $customerIds[] = (int)$o['customer_id']; }
        $customerIds = array_values(array_unique(array_filter($customerIds)));

        $customerMap = [];
        if (!empty($customerIds)) {
            try {
                $rows = $db->table('customers')->select('id, name')->whereIn('id', $customerIds)->get()->getResultArray();
                foreach ($rows as $r) { $customerMap[(int)$r['id']] = trim((string)($r['name'] ?? '')); }
            } catch (\Throwable $_) { /* best-effort */ }
        }

        // Load product ids and product names for sales orders and quotations (collect lists per document)
        $orderProductMap = []; // first product id fallback
        $quoteProductMap = [];
        $orderProductIdsByDoc = [];
        $quoteProductIdsByDoc = [];
        $orderProductNamesByDoc = [];
        $quoteProductNamesByDoc = [];
        $productIds = [];
        try {
            if (!empty($orderIds)) {
                $lines = $db->table('sales_order_lines')->whereIn('sales_order_id', $orderIds)->orderBy('id','ASC')->get()->getResultArray();
                foreach ($lines as $ln) {
                    $soId = (int)($ln['sales_order_id'] ?? 0);
                    if ($soId <= 0) continue;
                    $pid = !empty($ln['product_id']) ? (int)$ln['product_id'] : 0;
                    if ($pid > 0) {
                        $orderProductIdsByDoc[$soId][] = $pid;
                        $productIds[] = $pid;
                        if (empty($orderProductMap[$soId])) $orderProductMap[$soId] = $pid;
                    } else {
                        $txt = trim((string)($ln['product_name'] ?? $ln['description'] ?? ''));
                        if ($txt !== '') $orderProductNamesByDoc[$soId][] = $txt;
                    }
                }
            }
            if (!empty($quoteIds)) {
                $qlines = $db->table('quotation_lines')->whereIn('quotation_id', $quoteIds)->orderBy('id','ASC')->get()->getResultArray();
                foreach ($qlines as $ln) {
                    $qId = (int)($ln['quotation_id'] ?? 0);
                    if ($qId <= 0) continue;
                    $pid = !empty($ln['product_id']) ? (int)$ln['product_id'] : 0;
                    if ($pid > 0) {
                        $quoteProductIdsByDoc[$qId][] = $pid;
                        $productIds[] = $pid;
                        if (empty($quoteProductMap[$qId])) $quoteProductMap[$qId] = $pid;
                    } else {
                        $txt = trim((string)($ln['product_name'] ?? $ln['description'] ?? ''));
                        if ($txt !== '') $quoteProductNamesByDoc[$qId][] = $txt;
                    }
                }
            }
        } catch (\Throwable $_) { /* ignore */ }

        $productMap = [];
        $productIds = array_values(array_unique(array_filter($productIds)));
        if (!empty($productIds)) {
            try {
                $prods = $db->table('products')->select('id, name')->whereIn('id', $productIds)->get()->getResultArray();
                foreach ($prods as $p) { $productMap[(int)$p['id']] = trim((string)($p['name'] ?? '')); }
            } catch (\Throwable $_) { /* best-effort */ }
        }

        // Build final product name lists per document
        $orderProducts = [];
        foreach ($orderIds as $oid) {
            $oid = (int)$oid;
            $names = [];
            if (!empty($orderProductIdsByDoc[$oid])) {
                foreach ($orderProductIdsByDoc[$oid] as $pid) {
                    $label = $productMap[(int)$pid] ?? '';
                    if ($label !== '') $names[] = $label;
                }
            }
            if (!empty($orderProductNamesByDoc[$oid])) {
                foreach ($orderProductNamesByDoc[$oid] as $txt) { if ($txt !== '') $names[] = $txt; }
            }
            $orderProducts[$oid] = array_values(array_unique(array_filter($names)));
        }

        $quoteProducts = [];
        foreach ($quoteIds as $qid) {
            $qid = (int)$qid;
            $names = [];
            if (!empty($quoteProductIdsByDoc[$qid])) {
                foreach ($quoteProductIdsByDoc[$qid] as $pid) {
                    $label = $productMap[(int)$pid] ?? '';
                    if ($label !== '') $names[] = $label;
                }
            }
            if (!empty($quoteProductNamesByDoc[$qid])) {
                foreach ($quoteProductNamesByDoc[$qid] as $txt) { if ($txt !== '') $names[] = $txt; }
            }
            $quoteProducts[$qid] = array_values(array_unique(array_filter($names)));
        }

        // Normalize into a single list
        $rows = [];

        foreach ($quotes as $q) {
            $qid = (int)($q['id'] ?? 0);
            $custLabel = $q['customer_name'] ?? ($customerMap[(int)($q['customer_id'] ?? 0)] ?? ($q['customer_id'] ?? ''));
            $plist = $quoteProducts[$qid] ?? [];
            $prodLabel = count($plist) ? $plist[0] : '';
            $rows[] = [
                'doc_type' => 'quotation',
                'id' => $qid,
                'public_id' => (string)($q['public_id'] ?? ''),
                'number' => (string)($q['quote_number'] ?? ''),
                'customer' => $custLabel,
                'product' => $prodLabel,
                'product_list' => $plist,
                'product_count' => count($plist),
                'date' => $q['issue_date'] ?? null,
                'status' => strtolower((string)($q['status'] ?? 'quoted')),
                'total' => (float)($q['total'] ?? 0),
                'currency' => strtoupper((string)($q['quote_currency'] ?? $q['currency'] ?? '')),
                'converted_sales_order_id' => isset($q['converted_to_sales_order_id']) ? (int)$q['converted_to_sales_order_id'] : null,
            ];
        }

        foreach ($orders as $o) {
            // sales_orders table may not have a status column; keep it readable if missing
            $status = $o['status'] ?? null;
            $oid = (int)($o['id'] ?? 0);
            $custLabel = $o['customer_name'] ?? ($customerMap[(int)($o['customer_id'] ?? 0)] ?? ($o['customer_id'] ?? ''));
            $plist = $orderProducts[$oid] ?? [];
            $prodLabel = count($plist) ? $plist[0] : '';
            $rows[] = [
                'doc_type' => 'sales_order',
                'id' => $oid,
                'public_id' => (string)($o['public_id'] ?? ''),
                'number' => (string)($o['order_number'] ?? ''),
                'customer' => $custLabel,
                'product' => $prodLabel,
                'product_list' => $plist,
                'product_count' => count($plist),
                'date' => $o['order_date'] ?? null,
                'status' => $status ? strtolower((string)$status) : 'confirmed',
                'total' => (float)($o['total'] ?? 0),
                'currency' => strtoupper((string)($o['currency'] ?? '')),
            ];
        }

        // Sort by date desc, fallback to id desc
        usort($rows, function ($a, $b) {
            $ad = $a['date'] ?? '';
            $bd = $b['date'] ?? '';
            if ($ad === $bd) return ($b['id'] ?? 0) <=> ($a['id'] ?? 0);
            return strcmp((string)$bd, (string)$ad);
        });

        if (!empty($searchTerm)) {
            $rows = SearchService::filterRows($rows, $searchTerm, [
                'number',
                'customer',
                'product',
                'status',
                'doc_type'
            ]);
        }

        return view('documents/index', [
            'documents' => $rows,
            'search' => $searchTerm,
        ]);
    }

    /**
     * Live search AJAX endpoint
     */
    public function search()
    {
        $searchTerm = $this->request->getGet('q');

        if (empty($searchTerm) || strlen($searchTerm) < 1) {
            return $this->response->setJSON(['results' => [], 'total' => 0]);
        }

        $quotationModel = new QuotationModel();
        $salesOrderModel = new SalesOrderModel();

        // Fetch all docs
        $db = \Config\Database::connect();
        $qCols = [];
        try { $qCols = $db->getFieldNames('quotations'); } catch (\Throwable $_) { $qCols = []; }

        if (in_array('converted_to_sales_order_id', $qCols, true)) {
            $quotationModel->where('converted_to_sales_order_id IS NULL', null, false);
        }

        // Privacy filtering: exclude documents from users who have hidden their docs
        $currentUserId = (int) (session()->get('user_id') ?? 0);
        $isAdmin = service('policy')->isAdmin();
        $privateUserIds = (new \App\Libraries\RoleDataAccess())->getPrivateUserIds($currentUserId, $isAdmin);
        if (!empty($privateUserIds)) {
            try {
                if (in_array('created_by', $db->getFieldNames('quotations') ?: [], true)) {
                    $quotationModel->whereNotIn('created_by', $privateUserIds);
                }
            } catch (\Throwable $_) {}
            try {
                if (in_array('created_by', $db->getFieldNames('sales_orders') ?: [], true)) {
                    $salesOrderModel->whereNotIn('created_by', $privateUserIds);
                }
            } catch (\Throwable $_) {}
        }

        $quotes = $quotationModel->orderBy('id', 'desc')->findAll(100);
        $orders = $salesOrderModel->orderBy('id', 'desc')->findAll(100);

        // Normalize into a single list
        $rows = [];

        foreach ($quotes as $q) {
            $rows[] = [
                'doc_type' => 'quotation',
                'id' => (int)($q['id'] ?? 0),
                'public_id' => (string)($q['public_id'] ?? ''),
                'number' => (string)($q['quote_number'] ?? ''),
                'customer_name' => $q['customer_name'] ?? '',
                'product' => '',
                'date' => $q['issue_date'] ?? null,
                'status' => strtolower((string)($q['status'] ?? 'quoted')),
                'total' => (float)($q['total'] ?? 0),
                'currency' => strtoupper((string)($q['quote_currency'] ?? $q['currency'] ?? '')),
            ];
        }

        foreach ($orders as $o) {
            $status = $o['status'] ?? null;
            $rows[] = [
                'doc_type' => 'sales_order',
                'id' => (int)($o['id'] ?? 0),
                'public_id' => (string)($o['public_id'] ?? ''),
                'number' => (string)($o['order_number'] ?? ''),
                'customer_name' => $o['customer_name'] ?? '',
                'product' => '',
                'date' => $o['order_date'] ?? null,
                'status' => $status ? strtolower((string)$status) : 'confirmed',
                'total' => (float)($o['total'] ?? 0),
                'currency' => strtoupper((string)($o['currency'] ?? '')),
            ];
        }

        // Filter by search term
        $results = SearchService::filterRows($rows, $searchTerm, [
            'number',
            'customer_name',
            'product',
            'status'
        ]);

        // Return total count and limited results (8 for preview)
        return $this->response->setJSON([
            'results' => array_slice($results, 0, 8),
            'total' => count($results),
            'searchTerm' => $searchTerm
        ]);
    }
}
