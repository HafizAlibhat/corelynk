<?php

namespace App\Controllers;

use Config\Database;

class Search extends BaseController
{
    public function index()
    {
        $this->requireAuth();

        $q = trim((string) ($this->request->getGet('q') ?? ''));
        $limit = (int) ($this->request->getGet('limit') ?? 8);
        $limit = max(3, min($limit, 20));

        $results = $q !== '' ? $this->runSearch($q, $limit) : [];

        $wantsJson = $this->request->isAJAX()
            || str_contains(strtolower((string) $this->request->getHeaderLine('Accept')), 'application/json')
            || strtolower((string) $this->request->getGet('format')) === 'json';

        if ($wantsJson) {
            return $this->response->setJSON([
                'success' => true,
                'query' => $q,
                'count' => count($results),
                'data' => $results,
            ]);
        }

        return view('search/index', [
            'query' => $q,
            'results' => $results,
        ]);
    }

    public function query($term = null)
    {
        $this->requireAuth();

        $q = trim((string) ($term ?? ''));
        $limit = (int) ($this->request->getGet('limit') ?? 8);
        $limit = max(3, min($limit, 20));

        $results = $q !== '' ? $this->runSearch($q, $limit) : [];

        return $this->response->setJSON([
            'success' => true,
            'query' => $q,
            'count' => count($results),
            'data' => $results,
        ]);
    }

    private function runSearch(string $query, int $limit): array
    {
        $db = Database::connect();
        $q = trim($query);

        $currentUserId = (int) (session()->get('user_id') ?? 0);
        $isAdmin = service('policy')->isAdmin();
        $privateUserIds = (new \App\Libraries\RoleDataAccess())->getPrivateUserIds($currentUserId, $isAdmin);

        $results = [];

        $handlers = [
            'searchPurchaseOrders',
            'searchPurchaseRfqs',
            'searchQuotations',
            'searchSalesOrders',
            'searchCustomerInvoices',
            'searchVendors',
            'searchCustomers',
            'searchWorkOrders',
            'searchProducts',
        ];

        foreach ($handlers as $handler) {
            try {
                $this->{$handler}($db, $q, $limit, $results, $privateUserIds);
            } catch (\Throwable $e) {
                log_message('warning', 'Global search skipped ' . $handler . ': ' . $e->getMessage());
            }
        }

        return array_slice($results, 0, 60);
    }

    private function searchPurchaseOrders($db, string $q, int $limit, array &$results, array $privateUserIds = []): void
    {
        if (!$db->tableExists('purchase_orders')) {
            return;
        }

        $rows = $db->table('purchase_orders po')
            ->select('po.id, po.po_number, po.status, po.total, v.name AS vendor_name')
            ->join('vendors v', 'v.id = po.vendor_id', 'left')
            ->groupStart()
                ->like('po.po_number', $q)
                ->orLike('v.name', $q)
            ->groupEnd()
            ->orderBy('po.id', 'DESC')
            ->limit($limit)
            ->get()->getResultArray();

        foreach ($rows as $row) {
            $poNumber = trim((string) ($row['po_number'] ?? ''));
            $title = $poNumber !== '' ? $poNumber : ('PO #' . (int) ($row['id'] ?? 0));
            $vendor = trim((string) ($row['vendor_name'] ?? '')); 
            $status = trim((string) ($row['status'] ?? 'draft'));
            $subtitle = $vendor !== '' ? ($vendor . ' · ' . ucfirst($status)) : ucfirst($status);

            $this->pushResult($results, [
                'module' => 'PO',
                'icon' => 'bi-cart-check',
                'title' => $title,
                'subtitle' => $subtitle,
                'url' => site_url('purchases/po/' . (int) $row['id']),
            ]);
        }
    }

    private function searchPurchaseRfqs($db, string $q, int $limit, array &$results, array $privateUserIds = []): void
    {
        if (!$db->tableExists('purchase_rfqs')) {
            return;
        }

        $rows = $db->table('purchase_rfqs r')
            ->select('r.id, r.rfq_number, r.status, v.name AS vendor_name')
            ->join('vendors v', 'v.id = r.vendor_id', 'left')
            ->groupStart()
                ->like('r.rfq_number', $q)
                ->orLike('v.name', $q)
            ->groupEnd()
            ->orderBy('r.id', 'DESC')
            ->limit($limit)
            ->get()->getResultArray();

        foreach ($rows as $row) {
            $rfqNumber = trim((string) ($row['rfq_number'] ?? ''));
            $title = $rfqNumber !== '' ? $rfqNumber : ('RFQ #' . (int) ($row['id'] ?? 0));
            $vendor = trim((string) ($row['vendor_name'] ?? ''));
            $status = trim((string) ($row['status'] ?? 'draft'));
            $subtitle = $vendor !== '' ? ($vendor . ' · ' . ucfirst($status)) : ucfirst($status);

            $this->pushResult($results, [
                'module' => 'RFQ',
                'icon' => 'bi-clipboard-check',
                'title' => $title,
                'subtitle' => $subtitle,
                'url' => site_url('purchases/rfq/' . (int) $row['id']),
            ]);
        }
    }

    private function searchQuotations($db, string $q, int $limit, array &$results, array $privateUserIds = []): void
    {
        if (!$db->tableExists('quotations')) {
            return;
        }

        $builder = $db->table('quotations q')
            ->select('q.id, q.quote_number, q.status, q.public_id, c.name AS customer_name')
            ->join('customers c', 'c.id = q.customer_id', 'left')
            ->groupStart()
                ->like('q.quote_number', $q)
                ->orLike('c.name', $q)
            ->groupEnd();
        if (!empty($privateUserIds) && $db->fieldExists('created_by', 'quotations')) {
            $builder->whereNotIn('q.created_by', $privateUserIds);
        }
        $rows = $builder->orderBy('q.id', 'DESC')->limit($limit)->get()->getResultArray();

        foreach ($rows as $row) {
            $quoteNumber = trim((string) ($row['quote_number'] ?? ''));
            $title = $quoteNumber !== '' ? $quoteNumber : ('Quotation #' . (int) ($row['id'] ?? 0));
            $customer = trim((string) ($row['customer_name'] ?? ''));
            $status = trim((string) ($row['status'] ?? 'draft'));
            $subtitle = $customer !== '' ? ($customer . ' · ' . ucfirst($status)) : ucfirst($status);

            $this->pushResult($results, [
                'module' => 'Quotation',
                'icon' => 'bi-file-earmark-text',
                'title' => $title,
                'subtitle' => $subtitle,
                'url' => site_url('quotations/view/' . urlencode((!empty($row['public_id']) && featureEnabled('enable_public_ids')) ? $row['public_id'] : (int) $row['id'])),
            ]);
        }
    }

    private function searchSalesOrders($db, string $q, int $limit, array &$results, array $privateUserIds = []): void
    {
        if (!$db->tableExists('sales_orders')) {
            return;
        }

        $builder = $db->table('sales_orders so')
            ->select('so.id, so.public_id, so.order_number, so.status, c.name AS customer_name')
            ->join('customers c', 'c.id = so.customer_id', 'left')
            ->groupStart()
                ->like('so.order_number', $q)
                ->orLike('c.name', $q)
            ->groupEnd();
        if (!empty($privateUserIds) && $db->fieldExists('created_by', 'sales_orders')) {
            $builder->whereNotIn('so.created_by', $privateUserIds);
        }
        $rows = $builder->orderBy('so.id', 'DESC')->limit($limit)->get()->getResultArray();

        foreach ($rows as $row) {
            $orderNumber = trim((string) ($row['order_number'] ?? ''));
            $title = $orderNumber !== '' ? $orderNumber : ('SO #' . (int) ($row['id'] ?? 0));
            $customer = trim((string) ($row['customer_name'] ?? ''));
            $status = trim((string) ($row['status'] ?? 'draft'));
            $subtitle = $customer !== '' ? ($customer . ' · ' . ucfirst($status)) : ucfirst($status);

            $identifier = (int) ($row['id'] ?? 0);
            if (featureEnabled('enable_public_ids') && !empty($row['public_id'])) {
                $identifier = $row['public_id'];
            }

            $this->pushResult($results, [
                'module' => 'Sales Order',
                'icon' => 'bi-receipt',
                'title' => $title,
                'subtitle' => $subtitle,
                'url' => site_url('sales-orders/view/' . $identifier),
            ]);
        }
    }

    private function searchCustomerInvoices($db, string $q, int $limit, array &$results, array $privateUserIds = []): void
    {
        if (!$db->tableExists('customer_invoices')) {
            return;
        }

        $rows = $db->table('customer_invoices ci')
            ->select('ci.id, ci.invoice_number, ci.status, c.name AS customer_name')
            ->join('customers c', 'c.id = ci.customer_id', 'left')
            ->groupStart()
                ->like('ci.invoice_number', $q)
                ->orLike('c.name', $q)
            ->groupEnd()
            ->orderBy('ci.id', 'DESC')
            ->limit($limit)
            ->get()->getResultArray();

        foreach ($rows as $row) {
            $invoiceNumber = trim((string) ($row['invoice_number'] ?? ''));
            $title = $invoiceNumber !== '' ? $invoiceNumber : ('Invoice #' . (int) ($row['id'] ?? 0));
            $customer = trim((string) ($row['customer_name'] ?? ''));
            $status = trim((string) ($row['status'] ?? 'draft'));
            $subtitle = $customer !== '' ? ($customer . ' · ' . ucfirst($status)) : ucfirst($status);

            $this->pushResult($results, [
                'module' => 'Customer Invoice',
                'icon' => 'bi-receipt-cutoff',
                'title' => $title,
                'subtitle' => $subtitle,
                'url' => site_url('customer-invoices/view/' . (int) $row['id']),
            ]);
        }
    }

    private function searchCustomers($db, string $q, int $limit, array &$results, array $privateUserIds = []): void
    {
        if (!$db->tableExists('customers')) {
            return;
        }

        $rows = $db->table('customers c')
            ->select('c.id, c.customer_code, c.name, c.company_name, c.status')
            ->groupStart()
                ->like('c.customer_code', $q)
                ->orLike('c.name', $q)
                ->orLike('c.company_name', $q)
            ->groupEnd()
            ->orderBy('c.id', 'DESC')
            ->limit($limit)
            ->get()->getResultArray();

        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $code = trim((string) ($row['customer_code'] ?? ''));
            $title = $name !== '' ? $name : ('Customer #' . (int) ($row['id'] ?? 0));
            $company = trim((string) ($row['company_name'] ?? ''));
            $status = trim((string) ($row['status'] ?? 'active'));

            // Guard against mixed/migrated records where vendor entries leaked into customers.
            // If code looks vendor-like and same name exists in vendors table, classify as vendor only.
            $looksVendorCode = (bool) preg_match('/^(V|VEN)[-\/_]?[0-9A-Z]+$/i', $code);
            if ($looksVendorCode && $db->tableExists('vendors') && $title !== '') {
                $vendorTwin = $db->table('vendors')
                    ->select('id')
                    ->where('name', $title)
                    ->limit(1)
                    ->get()
                    ->getRowArray();
                if (!empty($vendorTwin)) {
                    continue;
                }
            }

            $parts = [];
            if ($code !== '') {
                $parts[] = $code;
            }
            if ($company !== '') {
                $parts[] = $company;
            }
            $parts[] = ucfirst($status);

            $this->pushResult($results, [
                'module' => 'Customer',
                'icon' => 'bi-people',
                'title' => $title,
                'subtitle' => implode(' · ', $parts),
                'url' => site_url('customers/' . (int) $row['id']),
            ]);
        }
    }

    private function searchVendors($db, string $q, int $limit, array &$results, array $privateUserIds = []): void
    {
        if (!$db->tableExists('vendors')) {
            return;
        }

        $rows = $db->table('vendors v')
            ->select('v.id, v.vendor_code, v.name, v.contact_person, v.is_active')
            ->groupStart()
                ->like('v.vendor_code', $q)
                ->orLike('v.name', $q)
                ->orLike('v.contact_person', $q)
            ->groupEnd()
            ->orderBy('v.id', 'DESC')
            ->limit($limit)
            ->get()->getResultArray();

        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $code = trim((string) ($row['vendor_code'] ?? ''));
            $contact = trim((string) ($row['contact_person'] ?? ''));
            $title = $name !== '' ? $name : ('Vendor #' . (int) ($row['id'] ?? 0));

            $parts = [];
            if ($code !== '') {
                $parts[] = $code;
            }
            if ($contact !== '') {
                $parts[] = $contact;
            }
            $parts[] = ((int) ($row['is_active'] ?? 1) === 1) ? 'Active' : 'Inactive';

            $this->pushResult($results, [
                'module' => 'Vendor',
                'icon' => 'bi-building',
                'title' => $title,
                'subtitle' => implode(' · ', $parts),
                'url' => site_url('vendors/' . (int) $row['id']),
            ]);
        }
    }

    private function searchWorkOrders($db, string $q, int $limit, array &$results, array $privateUserIds = []): void
    {
        if (!$db->tableExists('work_orders')) {
            return;
        }

        $rows = [];
        $woCols = [];
        try {
            $woCols = $db->getFieldNames('work_orders');
        } catch (\Throwable $_) {
            $woCols = [];
        }

        $canJoinCustomer = in_array('customer_id', $woCols, true) && $db->tableExists('customers');

        if ($canJoinCustomer) {
            $rows = $db->table('work_orders wo')
                ->select('wo.id, wo.wo_number, wo.status, wo.priority, c.name AS customer_name')
                ->join('customers c', 'c.id = wo.customer_id', 'left')
                ->groupStart()
                    ->like('wo.wo_number', $q)
                    ->orLike('c.name', $q)
                ->groupEnd()
                ->orderBy('wo.id', 'DESC')
                ->limit($limit)
                ->get()->getResultArray();
        } else {
            $rows = $db->table('work_orders wo')
                ->select('wo.id, wo.wo_number, wo.status, wo.priority')
                ->groupStart()
                    ->like('wo.wo_number', $q)
                    ->orLike('wo.status', $q)
                    ->orLike('wo.priority', $q)
                ->groupEnd()
                ->orderBy('wo.id', 'DESC')
                ->limit($limit)
                ->get()->getResultArray();
        }

        foreach ($rows as $row) {
            $number = trim((string) ($row['wo_number'] ?? ''));
            $title = $number !== '' ? $number : ('WO #' . (int) ($row['id'] ?? 0));
            $status = trim((string) ($row['status'] ?? 'planned'));
            $priority = trim((string) ($row['priority'] ?? 'normal'));
            $customer = trim((string) ($row['customer_name'] ?? ''));

            $parts = [];
            if ($customer !== '') {
                $parts[] = $customer;
            }
            $parts[] = ucfirst(str_replace('_', ' ', $status));
            $parts[] = ucfirst($priority);

            $this->pushResult($results, [
                'module' => 'Work Order',
                'icon' => 'bi-clipboard-check',
                'title' => $title,
                'subtitle' => implode(' · ', $parts),
                'url' => site_url('work-orders/' . (int) $row['id']),
            ]);
        }
    }

    private function searchProducts($db, string $q, int $limit, array &$results, array $privateUserIds = []): void
    {
        if (!$db->tableExists('products')) {
            return;
        }

        $rows = $db->table('products p')
            ->select('p.id, p.code, p.sku, p.name, p.is_active')
            ->groupStart()
                ->like('p.code', $q)
                ->orLike('p.sku', $q)
                ->orLike('p.name', $q)
            ->groupEnd()
            ->orderBy('p.id', 'DESC')
            ->limit($limit)
            ->get()->getResultArray();

        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $code = trim((string) ($row['code'] ?? ''));
            $sku = trim((string) ($row['sku'] ?? ''));
            $title = $name !== '' ? $name : ('Product #' . (int) ($row['id'] ?? 0));

            $parts = [];
            if ($code !== '') {
                $parts[] = $code;
            }
            if ($sku !== '') {
                $parts[] = $sku;
            }
            $parts[] = ((int) ($row['is_active'] ?? 1) === 1) ? 'Active' : 'Inactive';

            $this->pushResult($results, [
                'module' => 'Product',
                'icon' => 'bi-box-seam',
                'title' => $title,
                'subtitle' => implode(' · ', $parts),
                'url' => site_url('products/' . (int) $row['id'] . '/edit'),
            ]);
        }

        // Variant-level results (art number / variant name / attributes)
        if (!$db->tableExists('product_variants')) {
            return;
        }

        $variantRows = $db->table('product_variants pv')
            ->select('pv.id, pv.product_id, pv.art_number, pv.name as variant_name, pv.attributes, p.name as product_name, p.code as product_code')
            ->join('products p', 'p.id = pv.product_id', 'left')
            ->groupStart()
                ->like('pv.art_number', $q)
                ->orLike('pv.name', $q)
                ->orLike('pv.attributes', $q)
                ->orLike('p.name', $q)
                ->orLike('p.code', $q)
            ->groupEnd()
            ->orderBy('pv.id', 'DESC')
            ->limit($limit)
            ->get()->getResultArray();

        foreach ($variantRows as $row) {
            $art = trim((string)($row['art_number'] ?? ''));
            $variantName = trim((string)($row['variant_name'] ?? ''));
            $productName = trim((string)($row['product_name'] ?? ''));
            $productCode = trim((string)($row['product_code'] ?? ''));
            $attributes = trim((string)($row['attributes'] ?? ''));

            $titleParts = [];
            if ($art !== '') $titleParts[] = $art;
            if ($productName !== '') $titleParts[] = $productName;
            if ($variantName !== '') $titleParts[] = $variantName;
            $title = !empty($titleParts) ? implode(' — ', $titleParts) : ('Variant #' . (int)($row['id'] ?? 0));

            $subtitleParts = [];
            if ($productCode !== '') $subtitleParts[] = $productCode;
            if ($attributes !== '') {
                $attrDisplay = $attributes;
                $decoded = json_decode($attributes, true);
                if (is_array($decoded)) {
                    $pairs = [];
                    foreach ($decoded as $k => $v) {
                        $pairs[] = trim((string)$k) . ': ' . trim((string)$v);
                    }
                    $attrDisplay = implode(' • ', $pairs);
                }
                if ($attrDisplay !== '') $subtitleParts[] = $attrDisplay;
            }

            $this->pushResult($results, [
                'module' => 'Variant',
                'icon' => 'bi-grid-3x3-gap',
                'title' => $title,
                'subtitle' => implode(' · ', $subtitleParts),
                'url' => site_url('product-variants/' . (int)$row['id'] . '/edit'),
            ]);
        }
    }

    private function pushResult(array &$results, array $item): void
    {
        foreach ($results as $existing) {
            if (($existing['url'] ?? '') === ($item['url'] ?? '')) {
                return;
            }
        }

        $results[] = $item;
    }
}
