<?php

namespace App\Controllers;

use App\Models\QuotationModel;
use App\Models\SalesOrderModel;
use App\Services\NotificationService;
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
        $notificationService = new NotificationService();
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
        $createdByIds = [];
        foreach ($quotes as $q) { $quoteIds[] = (int)($q['id'] ?? 0); if (!empty($q['customer_id'])) $customerIds[] = (int)$q['customer_id']; }
        foreach ($orders as $o) {
            $orderIds[] = (int)($o['id'] ?? 0);
            if (!empty($o['customer_id'])) $customerIds[] = (int)$o['customer_id'];
            if (!empty($o['created_by'])) $createdByIds[] = (int)$o['created_by'];
        }
        $customerIds = array_values(array_unique(array_filter($customerIds)));
        $createdByIds = array_values(array_unique(array_filter($createdByIds)));

        $customerMap = [];
        if (!empty($customerIds)) {
            try {
                $rows = $db->table('customers')->select('id, name')->whereIn('id', $customerIds)->get()->getResultArray();
                foreach ($rows as $r) { $customerMap[(int)$r['id']] = trim((string)($r['name'] ?? '')); }
            } catch (\Throwable $_) { /* best-effort */ }
        }

        $customerCountryFromCustomerTable = [];
        if (!empty($customerIds)) {
            try {
                $customerCols = $db->getFieldNames('customers') ?: [];
                if (in_array('country_name', $customerCols, true)) {
                    $cRows = $db->table('customers')->select('id, country_name')->whereIn('id', $customerIds)->get()->getResultArray();
                    foreach ($cRows as $cr) {
                        $cid = (int)($cr['id'] ?? 0);
                        if ($cid <= 0) {
                            continue;
                        }
                        $country = trim((string)($cr['country_name'] ?? ''));
                        if ($country !== '') {
                            $customerCountryFromCustomerTable[$cid] = $country;
                        }
                    }
                }
            } catch (\Throwable $_) { /* best-effort */ }
        }

        $customerCountryMap = [];
        if (!empty($customerIds)) {
            try {
                if ($db->tableExists('customer_addresses')) {
                    $addressRows = $db->table('customer_addresses')
                        ->select('customer_id, country_name, country_id, is_default, is_shipping, id')
                        ->whereIn('customer_id', $customerIds)
                        ->orderBy('is_default', 'DESC')
                        ->orderBy('is_shipping', 'DESC')
                        ->orderBy('id', 'ASC')
                        ->get()
                        ->getResultArray();

                    $countryIds = [];
                    foreach ($addressRows as $ar) {
                        $cid = (int)($ar['customer_id'] ?? 0);
                        if ($cid <= 0 || !empty($customerCountryMap[$cid])) {
                            continue;
                        }
                        $countryName = trim((string)($ar['country_name'] ?? ''));
                        if ($countryName !== '') {
                            $customerCountryMap[$cid] = $countryName;
                            continue;
                        }
                        $countryId = (int)($ar['country_id'] ?? 0);
                        if ($countryId > 0) {
                            $countryIds[$countryId] = true;
                        }
                    }

                    if (!empty($countryIds) && $db->tableExists('countries')) {
                        $countryRows = $db->table('countries')
                            ->select('id, name')
                            ->whereIn('id', array_keys($countryIds))
                            ->get()
                            ->getResultArray();
                        $countryNameById = [];
                        foreach ($countryRows as $cr) {
                            $countryNameById[(int)$cr['id']] = trim((string)($cr['name'] ?? ''));
                        }
                        foreach ($addressRows as $ar) {
                            $cid = (int)($ar['customer_id'] ?? 0);
                            if ($cid <= 0 || !empty($customerCountryMap[$cid])) {
                                continue;
                            }
                            $countryId = (int)($ar['country_id'] ?? 0);
                            $resolved = $countryNameById[$countryId] ?? '';
                            if ($resolved !== '') {
                                $customerCountryMap[$cid] = $resolved;
                            }
                        }
                    }

                    foreach ($customerIds as $cid) {
                        $cid = (int)$cid;
                        if ($cid > 0 && empty($customerCountryMap[$cid]) && !empty($customerCountryFromCustomerTable[$cid])) {
                            $customerCountryMap[$cid] = (string)$customerCountryFromCustomerTable[$cid];
                        }
                    }
                }
            } catch (\Throwable $_) { /* best-effort */ }
        }

        $creatorMap = [];
        if (!empty($createdByIds)) {
            try {
                $userRows = $db->table('users')
                    ->select('id, username, first_name, last_name')
                    ->whereIn('id', $createdByIds)
                    ->get()
                    ->getResultArray();
                foreach ($userRows as $ur) {
                    $uid = (int)($ur['id'] ?? 0);
                    if ($uid <= 0) {
                        continue;
                    }
                    $full = trim((string)(($ur['first_name'] ?? '') . ' ' . ($ur['last_name'] ?? '')));
                    $creatorMap[$uid] = $full !== '' ? $full : trim((string)($ur['username'] ?? ('User #' . $uid)));
                }
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
                    $lineDetail = trim((string)($ln['description'] ?? ''));
                    if ($lineDetail === '') {
                        $lineDetail = trim((string)($ln['product_name'] ?? ''));
                    }
                    if ($pid > 0) {
                        $orderProductIdsByDoc[$soId][] = $pid;
                        $productIds[] = $pid;
                        if (empty($orderProductMap[$soId])) $orderProductMap[$soId] = $pid;
                    }
                    if ($lineDetail !== '') {
                        $orderProductNamesByDoc[$soId][] = $lineDetail;
                    }
                }
            }
            if (!empty($quoteIds)) {
                $qlines = $db->table('quotation_lines')->whereIn('quotation_id', $quoteIds)->orderBy('id','ASC')->get()->getResultArray();
                foreach ($qlines as $ln) {
                    $qId = (int)($ln['quotation_id'] ?? 0);
                    if ($qId <= 0) continue;
                    $pid = !empty($ln['product_id']) ? (int)$ln['product_id'] : 0;
                    $lineDetail = trim((string)($ln['description'] ?? ''));
                    if ($lineDetail === '') {
                        $lineDetail = trim((string)($ln['product_name'] ?? ''));
                    }
                    if ($pid > 0) {
                        $quoteProductIdsByDoc[$qId][] = $pid;
                        $productIds[] = $pid;
                        if (empty($quoteProductMap[$qId])) $quoteProductMap[$qId] = $pid;
                    }
                    if ($lineDetail !== '') {
                        $quoteProductNamesByDoc[$qId][] = $lineDetail;
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
            if (!empty($orderProductNamesByDoc[$oid])) {
                foreach ($orderProductNamesByDoc[$oid] as $txt) { if ($txt !== '') $names[] = $txt; }
            }
            // Only fall back to master product names when line-level labels are unavailable.
            if (empty($names) && !empty($orderProductIdsByDoc[$oid])) {
                foreach ($orderProductIdsByDoc[$oid] as $pid) {
                    $label = $productMap[(int)$pid] ?? '';
                    if ($label !== '') $names[] = $label;
                }
            }
            $orderProducts[$oid] = array_values(array_unique(array_filter($names)));
        }

        $quoteProducts = [];
        foreach ($quoteIds as $qid) {
            $qid = (int)$qid;
            $names = [];
            if (!empty($quoteProductNamesByDoc[$qid])) {
                foreach ($quoteProductNamesByDoc[$qid] as $txt) { if ($txt !== '') $names[] = $txt; }
            }
            // Only fall back to master product names when line-level labels are unavailable.
            if (empty($names) && !empty($quoteProductIdsByDoc[$qid])) {
                foreach ($quoteProductIdsByDoc[$qid] as $pid) {
                    $label = $productMap[(int)$pid] ?? '';
                    if ($label !== '') $names[] = $label;
                }
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
            $customerId = (int)($o['customer_id'] ?? 0);
            $creatorId = (int)($o['created_by'] ?? 0);
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
                'created_by_name' => (string)($creatorMap[$creatorId] ?? ''),
                'customer_country' => (string)($customerCountryMap[$customerId] ?? ''),
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

        $latestVisibleOrderIds = array_values(array_map(static function ($o) {
            return (int) ($o['id'] ?? 0);
        }, array_slice(array_values(array_filter($orders, static function ($o) {
            $status = strtolower((string)($o['status'] ?? 'confirmed'));
            return !in_array($status, ['shipped', 'completed', 'cancelled'], true);
        })), 0, 10)));

        $newOrderIds = $this->computeNewOrderIds($currentUserId, $latestVisibleOrderIds);
        $unreadOrderAlerts = min(10, count($newOrderIds));

        return view('documents/index', [
            'documents' => $rows,
            'search' => $searchTerm,
            'unreadOrderAlerts' => $unreadOrderAlerts,
            'newOrderIds' => $newOrderIds,
        ]);
    }

    /**
     * Mark all order alerts as seen for current user.
     */
    public function acknowledgeOrdersAlarm()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'Invalid request type.',
                'csrfToken' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }

        $userId = (int) (session()->get('user_id') ?? 0);
        if ($userId <= 0) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
                'csrfToken' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }

        $notificationService = new NotificationService();
        $affected = $notificationService->markAllAsRead($userId);
        $latestVisibleOrderIds = $this->getLatestVisibleSalesOrderIds($userId, 10);
        $seenMaxOrderId = !empty($latestVisibleOrderIds) ? max(array_map('intval', $latestVisibleOrderIds)) : 0;
        session()->set('documents_orders_alarm_seen_at', date('Y-m-d H:i:s'));
        session()->set('documents_orders_alarm_seen_max_id', (int) $seenMaxOrderId);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Order alerts acknowledged.',
            'marked' => $affected,
            'unreadOrderAlerts' => 0,
            'csrfToken' => csrf_token(),
            'csrfHash' => csrf_hash(),
        ]);
    }

    /**
     * Return current unread order alert count for current user.
     */
    public function orderAlertState()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'Invalid request type.',
            ]);
        }

        $userId = (int) (session()->get('user_id') ?? 0);
        if ($userId <= 0) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $latestVisibleOrderIds = $this->getLatestVisibleSalesOrderIds($userId, 10);
        $newOrderIds = $this->computeNewOrderIds($userId, $latestVisibleOrderIds);
        $unreadOrderAlerts = min(10, count($newOrderIds));

        return $this->response->setJSON([
            'success' => true,
            'unreadOrderAlerts' => $unreadOrderAlerts,
            'newOrderIds' => $newOrderIds,
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

    /**
     * Compute unread order alerts with notification read-state + session fallback.
     */
    protected function computeNewOrderIds(int $userId, array $candidateOrderIds): array
    {
        $userId = (int) $userId;
        $candidateOrderIds = array_values(array_unique(array_filter(array_map('intval', $candidateOrderIds))));

        if ($userId <= 0 || empty($candidateOrderIds)) {
            return [];
        }

        $notificationUnreadIds = [];
        try {
            $notificationService = new NotificationService();
            $feed = $notificationService->getActivityCenterFeed($userId, 50);
            $activeSalesOrders = is_array($feed['active_sales_orders'] ?? null) ? $feed['active_sales_orders'] : [];
            foreach ($activeSalesOrders as $item) {
                if (empty($item['is_read'])) {
                    $sourceId = (int) ($item['source_id'] ?? 0);
                    if ($sourceId > 0) {
                        $notificationUnreadIds[] = $sourceId;
                    }
                }
            }
        } catch (\Throwable $_) {
            $notificationUnreadIds = [];
        }

        $notificationUnreadIds = array_values(array_unique(array_filter(array_map('intval', $notificationUnreadIds))));
        $notificationUnreadIds = array_values(array_intersect($candidateOrderIds, $notificationUnreadIds));

        $fallbackUnreadIds = $this->computeFallbackUnreadOrdersFromSession($candidateOrderIds);
        $merged = array_values(array_unique(array_merge($notificationUnreadIds, $fallbackUnreadIds)));
        return array_values(array_intersect($candidateOrderIds, $merged));
    }

    /**
     * Session-based per-user fallback so alarm still works without notification tables.
     */
    protected function computeFallbackUnreadOrdersFromSession(array $candidateOrderIds): array
    {
        $candidateOrderIds = array_values(array_unique(array_filter(array_map('intval', $candidateOrderIds))));
        if (empty($candidateOrderIds)) {
            return [];
        }

        $seenMaxId = (int) (session()->get('documents_orders_alarm_seen_max_id') ?? 0);
        if ($seenMaxId > 0) {
            $newIdsById = [];
            foreach ($candidateOrderIds as $candidateId) {
                $candidateId = (int) $candidateId;
                if ($candidateId > $seenMaxId) {
                    $newIdsById[] = $candidateId;
                }
            }
            if (!empty($newIdsById)) {
                return array_values(array_unique($newIdsById));
            }
        }

        $seenAt = (string) (session()->get('documents_orders_alarm_seen_at') ?? '');
        $seenTs = $seenAt !== '' ? strtotime($seenAt) : 0;
        if ($seenTs === false) {
            $seenTs = 0;
        }

        try {
            $rows = (new SalesOrderModel())
                ->select('id, created_at, order_date')
                ->whereIn('id', $candidateOrderIds)
                ->findAll();
        } catch (\Throwable $_) {
            return [];
        }

        if (empty($rows)) {
            return [];
        }

        $newIds = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $eventAt = (string) ($row['created_at'] ?? $row['order_date'] ?? '');
            $eventTs = $eventAt !== '' ? strtotime($eventAt) : 0;
            if ($eventTs === false) {
                $eventTs = 0;
            }

            if ($seenTs > 0) {
                if ($eventTs > $seenTs) {
                    $newIds[] = $id;
                }
            } else {
                // First time: show a visible alarm baseline from latest orders.
                $newIds[] = $id;
            }
        }

        $newIds = array_values(array_unique(array_filter(array_map('intval', $newIds))));
        return array_values(array_intersect($candidateOrderIds, $newIds));
    }

    /**
     * Get latest visible sales order IDs for current user context.
     *
     * @return int[]
     */
    protected function getLatestVisibleSalesOrderIds(int $userId, int $limit = 10): array
    {
        $userId = (int) $userId;
        $limit = max(1, min(10, (int) $limit));

        $model = new SalesOrderModel();
        $db = \Config\Database::connect();

        $isAdmin = service('policy')->isAdmin();
        $privateUserIds = (new \App\Libraries\RoleDataAccess())->getPrivateUserIds($userId, $isAdmin);
        if (!empty($privateUserIds)) {
            try {
                if (in_array('created_by', $db->getFieldNames('sales_orders') ?: [], true)) {
                    $model->whereNotIn('created_by', $privateUserIds);
                }
            } catch (\Throwable $_) {}
        }

        try {
            $statusCols = [];
            try { $statusCols = $db->getFieldNames('sales_orders') ?: []; } catch (\Throwable $_) { $statusCols = []; }
            if (in_array('status', $statusCols, true)) {
                $model->whereNotIn('status', ['shipped', 'completed', 'cancelled']);
            }
            $rows = $model->select('id')->orderBy('id', 'desc')->findAll($limit);
        } catch (\Throwable $_) {
            return [];
        }

        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
