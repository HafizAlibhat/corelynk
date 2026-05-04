<?php

namespace App\Controllers;

use Config\Database;

class ProductLedger extends BaseController
{
    public function index($productId = null)
    {
        return view('reports/product_ledger', [
            'productId' => (int)($productId ?? 0),
            'pageTitle' => 'Product Ledger',
        ]);
    }

    // ─── JSON API: product + variant search autocomplete ─────────────────

    public function searchProducts()
    {
        $q  = trim($this->request->getGet('q') ?? '');
        $db = Database::connect();
        $hasAdjAudit = false;
        try {
            $hasAdjAudit = $db->tableExists('stock_adjustment_audit');
        } catch (\Throwable $e) {
            $hasAdjAudit = false;
        }

        try {
            $results = [];

            if ($q === '') {
                $products = $db->query(
                    'SELECT p.id, p.code, p.name,
                            (SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id = p.id) AS variant_count
                     FROM products p ORDER BY p.name ASC LIMIT 30'
                )->getResultArray();
            } else {
                $like = '%' . $q . '%';
                $products = $db->query(
                    'SELECT p.id, p.code, p.name,
                            (SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id = p.id) AS variant_count
                     FROM products p
                     WHERE p.name LIKE ? OR p.code LIKE ?
                     ORDER BY p.name ASC LIMIT 20',
                    [$like, $like]
                )->getResultArray();
            }

            foreach ($products as $p) {
                $results[] = [
                    'product_id' => (int)$p['id'],
                    'variant_id' => 0,
                    'label'      => ($p['code'] ? '[' . $p['code'] . '] ' : '') . $p['name'],
                    'sub'        => 'Product' . ((int)$p['variant_count'] > 0 ? ' · ' . $p['variant_count'] . ' variants' : ''),
                    'type'       => 'product',
                ];
            }

            // Also search variants by name / art_number
            if ($q !== '') {
                $like = '%' . $q . '%';
                $variants = $db->query(
                    'SELECT pv.id AS variant_id, pv.art_number, pv.name AS variant_name,
                            p.id AS product_id, p.code, p.name AS product_name
                     FROM product_variants pv
                     JOIN products p ON p.id = pv.product_id
                     WHERE pv.name LIKE ? OR pv.art_number LIKE ?
                     ORDER BY pv.art_number ASC LIMIT 20',
                    [$like, $like]
                )->getResultArray();

                foreach ($variants as $v) {
                    $results[] = [
                        'product_id' => (int)$v['product_id'],
                        'variant_id' => (int)$v['variant_id'],
                        'label'      => ($v['code'] ? '[' . $v['code'] . '] ' : '') . $v['product_name']
                                        . ' — ' . ($v['variant_name'] ?: $v['art_number']),
                        'sub'        => 'Variant · ' . $v['art_number'],
                        'type'       => 'variant',
                    ];
                }
            }
        } catch (\Throwable $e) {
            return $this->response->setJSON(['success' => false, 'error' => $e->getMessage()]);
        }

        return $this->response->setJSON(['success' => true, 'data' => $results]);
    }

    // ─── JSON API: stock movement history (uses stock_movements table) ───

    public function history()
    {
        $productId = (int)($this->request->getGet('product_id') ?? 0);
        $variantId = (int)($this->request->getGet('variant_id') ?? 0);
        $vendorId  = (int)($this->request->getGet('vendor_id') ?? 0);
        $customerId = (int)($this->request->getGet('customer_id') ?? 0);
        $dateFrom  = $this->request->getGet('date_from') ?? '';
        $dateTo    = $this->request->getGet('date_to') ?? '';
        $direction = $this->request->getGet('direction') ?? 'all';

        if ($productId <= 0 && $vendorId <= 0 && $customerId <= 0) {
            return $this->response->setJSON(['success' => false, 'error' => 'Select a product or choose vendor/customer filter']);
        }

        $db = Database::connect();

        $product = null;
        if ($productId > 0) {
            $product = $db->query(
                'SELECT id, code, name FROM products WHERE id = ? LIMIT 1',
                [$productId]
            )->getRowArray();

            if (!$product) {
                return $this->response->setJSON(['success' => false, 'error' => 'Product not found']);
            }
        }

        $variant = null;
        if ($variantId > 0 && $productId > 0) {
            $variant = $db->query(
                'SELECT id, product_id, art_number, name FROM product_variants WHERE id = ? LIMIT 1',
                [$variantId]
            )->getRowArray();

            if (!$variant || (int)($variant['product_id'] ?? 0) !== $productId) {
                return $this->response->setJSON([
                    'success' => false,
                    'error'   => 'Selected variant does not belong to the chosen product.',
                ]);
            }
        }

        // Default: last 2 months
        if ($dateFrom === '') $dateFrom = date('Y-m-d', strtotime('-2 months'));
        if ($dateTo === '')   $dateTo   = date('Y-m-d');

        // Opening balance is meaningful for product-scoped ledger only.
        $openingBalance = 0.0;
        if ($productId > 0) {
            $obSql    = 'SELECT COALESCE(SUM(qty_change), 0) AS ob FROM stock_movements WHERE product_id = ? AND DATE(created_at) < ?';
            $obParams = [$productId, $dateFrom];
            if ($variantId > 0) { $obSql .= ' AND variant_id = ?'; $obParams[] = $variantId; }
            $openingBalance = (float)($db->query($obSql, $obParams)->getRowArray()['ob'] ?? 0);
        }

        // Movements in date range
        $auditSelect = $hasAdjAudit
            ? ', saa.action_kind AS adjust_action_kind, saa.reason_code AS adjust_reason_code, saa.reason_text AS adjust_reason_text, saa.old_balance AS adjust_old_balance, saa.target_qty AS adjust_target_qty '
            : '';
        $auditJoin = $hasAdjAudit
            ? ' LEFT JOIN stock_adjustment_audit saa ON saa.movement_id = sm.id '
            : '';

        $sql = 'SELECT sm.id, sm.product_id, sm.qty_change, sm.unit_cost, sm.movement_type,
                       sm.reference_type, sm.reference_id, sm.stock_source,
                       sm.possible_vendor_id, sm.warehouse_id, sm.location_id,
                       sm.created_at, sm.created_by, sm.variant_id,
                       pglc.unit_cost AS grn_line_cost,
                       p.code AS product_code,
                       p.name AS product_name,
                       pv.name AS variant_name, pv.art_number,
                       w.name AS warehouse_name,
                       wl.name AS location_name,
                       v.name AS vendor_name,
                       rv.id AS resolved_vendor_id,
                       rv.name AS resolved_vendor_name,
                       rc.id AS resolved_customer_id,
                       rc.name AS resolved_customer_name,
                              CONCAT(u.first_name, " ", u.last_name) AS user_name'
                          . $auditSelect . '
                FROM stock_movements sm
                LEFT JOIN products p ON p.id = sm.product_id
                LEFT JOIN product_variants pv ON pv.id = sm.variant_id
                LEFT JOIN warehouses w ON w.id = sm.warehouse_id
                LEFT JOIN warehouse_locations wl ON wl.id = sm.location_id
                LEFT JOIN vendors v ON v.id = sm.possible_vendor_id
                LEFT JOIN purchase_grns grn_ref ON sm.reference_type = "grn" AND sm.reference_id = grn_ref.id
                LEFT JOIN purchase_orders po_ref ON po_ref.id = grn_ref.po_id
                LEFT JOIN vendors rv ON rv.id = COALESCE(sm.possible_vendor_id, grn_ref.vendor_id, po_ref.vendor_id)
                LEFT JOIN delivery_orders do_ref ON sm.reference_type = "delivery_order" AND sm.reference_id = do_ref.id
                LEFT JOIN sales_orders so_ref ON so_ref.id = do_ref.sales_order_id
                LEFT JOIN customers rc ON rc.id = so_ref.customer_id
                LEFT JOIN (
                    SELECT grn_id, product_id, variant_id, MAX(unit_cost) AS unit_cost
                    FROM purchase_grn_lines
                    GROUP BY grn_id, product_id, variant_id
                ) pglc
                    ON sm.reference_type = "grn"
                   AND sm.reference_id = pglc.grn_id
                   AND sm.product_id = pglc.product_id
                   AND COALESCE(sm.variant_id, 0) = COALESCE(pglc.variant_id, 0)
                LEFT JOIN users u ON u.id = sm.created_by
                ' . $auditJoin . '
                WHERE 1 = 1';
            $params = [];

            if ($productId > 0) {
                $sql .= ' AND sm.product_id = ?';
                $params[] = $productId;
            }

            if ($variantId > 0 && $productId > 0) { $sql .= ' AND sm.variant_id = ?'; $params[] = $variantId; }

        $sql .= ' AND DATE(sm.created_at) >= ? AND DATE(sm.created_at) <= ?';
        $params[] = $dateFrom;
        $params[] = $dateTo;

        if ($direction === 'in')  $sql .= ' AND sm.qty_change > 0';
        if ($direction === 'out') $sql .= ' AND sm.qty_change < 0';
        if ($vendorId > 0) {
            $sql .= ' AND COALESCE(sm.possible_vendor_id, grn_ref.vendor_id, po_ref.vendor_id) = ?';
            $params[] = $vendorId;
        }
        if ($customerId > 0) {
            $sql .= ' AND rc.id = ?';
            $params[] = $customerId;
        }

        $sql .= ' ORDER BY sm.created_at ASC, sm.id ASC';

        try {
            $movements = $db->query($sql, $params)->getResultArray();
        } catch (\Throwable $e) {
            return $this->response->setJSON(['success' => false, 'error' => 'Query error: ' . $e->getMessage()]);
        }

        // Batch-resolve reference context (doc labels + counterparties)
        $refContext = $this->resolveReferenceContext($movements, $db);
        $grnValuation = $this->resolveGrnValuation($movements, $db);

        // Helpful hints when a specific variant has no movement rows.
        $hints = [];
        if ($productId > 0 && $variantId > 0 && empty($movements)) {
            $hints = $this->buildNoMovementHints($db, $productId, $variantId, $dateFrom, $dateTo);
        }

        // Build rows with running balance
        $balance     = $openingBalance;
        $totalIn     = 0;
        $totalOut    = 0;
        $totalInVal  = 0;
        $totalOutVal = 0;
        $totalFreeIn = 0;
        $rows        = [];

        $openingByVariant = $productId > 0 ? $this->openingBalancesByVariant($db, $productId, $variantId, $dateFrom) : [];
        $lastCostByVariant = $productId > 0 ? $this->lastKnownCostByVariant($db, $productId, $variantId, $dateFrom) : [];
        $variantBalances = $openingByVariant;
        $paidBalances = $openingByVariant;
        if ($variantId > 0 && !isset($variantBalances[$variantId])) {
            $variantBalances[$variantId] = $openingBalance;
            $paidBalances[$variantId] = $openingBalance;
        }

        foreach ($movements as $m) {
            $variantKey = (int)($m['variant_id'] ?? 0);
            $qty   = (float)$m['qty_change'];
            $cost  = (float)($m['unit_cost'] ?? 0);
            if ($cost <= 0) {
                $cost = (float)($m['grn_line_cost'] ?? 0);
            }
            if ($cost <= 0) {
                $cost = (float)($lastCostByVariant[$variantKey] ?? 0);
            }
            $balance += $qty;
            $isIn  = $qty > 0;

            $qtyAbs = abs($qty);
            $paidQty = $qtyAbs;
            $freeQty = 0.0;
            $valuationNote = '';

            $refKey = ($m['reference_type'] ?? '') . ':' . ($m['reference_id'] ?? '');
            $ctx = $refContext[$refKey] ?? [];

            if ($isIn && ($m['reference_type'] ?? '') === 'grn' && ($m['reference_id'] ?? null) !== null) {
                $gk = ((int)$m['reference_id']) . ':' . ((int)$m['product_id']) . ':' . ((int)($m['variant_id'] ?? 0));
                $freeFromGrn = (float)($grnValuation[$gk]['free_qty'] ?? 0);
                if ($freeFromGrn > 0) {
                    $freeQty = min($qtyAbs, $freeFromGrn);
                    $paidQty = max(0, $qtyAbs - $freeQty);
                    $totalFreeIn += $freeQty;
                    $reason = (string)($grnValuation[$gk]['reason'] ?? 'free replacement');
                    $valuationNote = 'Free qty: ' . rtrim(rtrim(number_format($freeQty, 4, '.', ''), '0'), '.') . ' (' . $reason . ')';
                }
            }

            $value = $paidQty * $cost;

            if (!isset($variantBalances[$variantKey])) {
                $variantBalances[$variantKey] = 0;
            }
            $variantBalances[$variantKey] += $qty;

            if (!isset($paidBalances[$variantKey])) {
                $paidBalances[$variantKey] = 0;
            }
            if ($isIn) {
                $paidBalances[$variantKey] += $paidQty;
            } else {
                $consumePaid = min((float)$paidBalances[$variantKey], $qtyAbs);
                $paidBalances[$variantKey] -= $consumePaid;
                // Outbound value should not consume free units as paid cost.
                if ($cost > 0) {
                    $value = $consumePaid * $cost;
                }
            }

            if ($cost > 0 && $isIn) {
                $lastCostByVariant[$variantKey] = $cost;
            }

            if ($isIn) { $totalIn += $qty; $totalInVal += $value; }
            else       { $totalOut += abs($qty); $totalOutVal += $value; }

            $adjustReasonText = '';
            if (($m['movement_type'] ?? '') === 'adjustment' && !empty($m['adjust_reason_text'])) {
                $adjustReasonText = (string)$m['adjust_reason_text'];
            }
            $adjustKind = (string)($m['adjust_action_kind'] ?? '');
            $oldBal = isset($m['adjust_old_balance']) ? (float)$m['adjust_old_balance'] : null;
            $targetQty = isset($m['adjust_target_qty']) ? (float)$m['adjust_target_qty'] : null;

            $rows[] = [
                'date'       => $m['created_at'],
                'direction'  => $isIn ? 'IN' : 'OUT',
                'qty'        => $qty,
                'qty_abs'    => abs($qty),
                'paid_qty'   => round($paidQty, 4),
                'free_qty'   => round($freeQty, 4),
                'valuation_note' => $valuationNote,
                'unit_cost'  => $cost > 0 ? round($cost, 4) : null,
                'value'      => $cost > 0 ? round($value, 2) : null,
                'unit_cost_visible' => $cost > 0,
                'balance'    => round($balance, 4),
                'type'       => $m['movement_type'],
                'movement_label' => $this->movementLabel((string)($m['movement_type'] ?? '')),
                'ref_type'   => $m['reference_type'] ?? '',
                'ref_id'     => $m['reference_id'] ?? '',
                'ref_label'  => $ctx['ref_label'] ?? '—',
                'document'   => $ctx['document'] ?? '',
                'doc_url'    => $ctx['doc_url'] ?? '',
                'product_id' => (int)($m['product_id'] ?? 0),
                'product_label' => trim(((string)($m['product_code'] ?? '') !== '' ? ('[' . $m['product_code'] . '] ') : '') . (string)($m['product_name'] ?? '')),
                'variant'    => $m['variant_name'] ?: ($m['art_number'] ?: ''),
                'art_number' => $m['art_number'] ?? '',
                'warehouse'  => $m['warehouse_name'] ?? '',
                'location'   => $m['location_name'] ?? '',
                'counterparty' => $ctx['counterparty'] ?? ($m['resolved_vendor_name'] ?? ($m['vendor_name'] ?? '')),
                'counterparty_role' => $ctx['counterparty_role'] ?? '',
                'counterparty_id'   => $ctx['counterparty_id'] ?? '',
                'user'       => $m['user_name'] ?? '',
                'notes'      => $m['stock_source'] ?? '',
                'adjust_action_kind' => $adjustKind,
                'adjust_old_balance' => $oldBal,
                'adjust_target_qty' => $targetQty,
                'adjust_reason_text' => $adjustReasonText,
            ];
        }

        $stockValue = 0;
        if ($productId > 0) {
            foreach ($paidBalances as $vk => $paidQtyBal) {
                $k = (int)$vk;
                $c = (float)($lastCostByVariant[$k] ?? 0);
                if ($c > 0 && (float)$paidQtyBal !== 0.0) {
                    $stockValue += ((float)$paidQtyBal * $c);
                }
            }
        }

        $vendorOptions = $this->queryVendorOptions($db, $productId, $variantId, $dateFrom, $dateTo);

        return $this->response->setJSON([
            'success' => true,
            'product' => $product,
            'variant' => $variant,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'vendor_id' => $vendorId,
                'customer_id' => $customerId,
                'all_products' => $productId <= 0,
            ],
            'summary' => [
                'opening_balance' => round($openingBalance, 4),
                'total_in'        => round($totalIn, 4),
                'total_in_free'   => round($totalFreeIn, 4),
                'total_out'       => round($totalOut, 4),
                'total_in_value'  => round($totalInVal, 2),
                'total_out_value' => round($totalOutVal, 2),
                'total_out_value_hidden' => false,
                'stock_value'     => round($stockValue, 2),
                'stock_value_basis' => 'paid_only',
                'closing_balance' => round($balance, 4),
            ],
            'rows'  => $rows,
            'count' => count($rows),
            'hints' => $hints,
            'vendors' => $vendorOptions,
        ]);
    }

    public function vendors()
    {
        $productId = (int)($this->request->getGet('product_id') ?? 0);
        $variantId = (int)($this->request->getGet('variant_id') ?? 0);
        $dateFrom  = $this->request->getGet('date_from') ?? '';
        $dateTo    = $this->request->getGet('date_to') ?? '';

        if ($dateFrom === '') $dateFrom = date('Y-m-d', strtotime('-2 months'));
        if ($dateTo === '')   $dateTo   = date('Y-m-d');

        $db = Database::connect();
        $vendors = $this->queryVendorOptions($db, $productId, $variantId, $dateFrom, $dateTo);

        return $this->response->setJSON(['success' => true, 'data' => $vendors]);
    }

    public function customers()
    {
        $productId = (int)($this->request->getGet('product_id') ?? 0);
        $variantId = (int)($this->request->getGet('variant_id') ?? 0);
        $dateFrom  = $this->request->getGet('date_from') ?? '';
        $dateTo    = $this->request->getGet('date_to') ?? '';

        if ($dateFrom === '') $dateFrom = date('Y-m-d', strtotime('-2 months'));
        if ($dateTo === '')   $dateTo   = date('Y-m-d');

        $db = Database::connect();
        $customers = $this->queryCustomerOptions($db, $productId, $variantId, $dateFrom, $dateTo);

        return $this->response->setJSON(['success' => true, 'data' => $customers]);
    }

    // ─── Batch-resolve reference labels + counterparties (avoids N+1) ───

    private function resolveReferenceContext(array $movements, $db): array
    {
        $groups = [];
        foreach ($movements as $m) {
            $type = $m['reference_type'] ?? '';
            $id   = (int)($m['reference_id'] ?? 0);
            if ($type && $id) $groups[$type][$id] = true;
        }

        $context = [];

        if (!empty($groups['grn'])) {
            $ids = array_keys($groups['grn']);
            $ph  = implode(',', array_fill(0, count($ids), '?'));
            try {
                $rows = $db->query(
                    "SELECT g.id, g.grn_number, g.po_id, v.name AS vendor_name, v.public_id AS vendor_public_id,
                            po.po_number, po.public_id AS po_public_id
                     FROM purchase_grns g
                     LEFT JOIN purchase_orders po ON po.id = g.po_id
                     LEFT JOIN vendors v ON v.id = po.vendor_id
                     WHERE g.id IN ($ph)",
                    $ids
                )->getResultArray();
                foreach ($rows as $r) {
                    $key = 'grn:' . $r['id'];
                    $poPublicId = trim((string)($r['po_public_id'] ?? ''));
                    $poId       = (int)($r['po_id'] ?? 0);
                    $docUrl     = $poPublicId !== '' ? ('/new-purchase-orders/' . $poPublicId)
                                : ($poId > 0 ? '/new-purchase-orders/' . $poId : '');
                    $context[$key] = [
                        'ref_label'          => 'GRN ' . ($r['grn_number'] ?? ('#' . $r['id'])),
                        'document'           => !empty($r['po_number']) ? ('PO ' . $r['po_number']) : '',
                        'doc_url'            => $docUrl,
                        'counterparty'       => $r['vendor_name'] ?? '',
                        'counterparty_role'  => !empty($r['vendor_name']) ? 'Vendor' : '',
                        'counterparty_id'    => !empty($r['vendor_public_id']) ? $r['vendor_public_id'] : (int)($r['vendor_id'] ?? 0),
                    ];
                }
            } catch (\Throwable $e) {
                foreach ($ids as $id) {
                    $context['grn:' . $id] = ['ref_label' => 'GRN #' . $id];
                }
            }
        }

        if (!empty($groups['delivery_order'])) {
            $ids = array_keys($groups['delivery_order']);
            $ph  = implode(',', array_fill(0, count($ids), '?'));
            try {
                $rows = $db->query(
                    "SELECT d.id, d.do_number, s.order_number AS so_number,
                            s.public_id AS so_public_id, s.id AS so_id,
                            c.name AS customer_name
                     FROM delivery_orders d
                     LEFT JOIN sales_orders s ON s.id = d.sales_order_id
                     LEFT JOIN customers c ON c.id = s.customer_id
                     WHERE d.id IN ($ph)",
                    $ids
                )->getResultArray();
                foreach ($rows as $r) {
                    $key = 'delivery_order:' . $r['id'];
                    $soPublicId = trim((string)($r['so_public_id'] ?? ''));
                    $soId       = (int)($r['so_id'] ?? 0);
                    $docUrl     = $soPublicId !== '' ? ('/sales-orders/view/' . $soPublicId)
                                : ($soId > 0 ? '/sales-orders/view/' . $soId : '');
                    $context[$key] = [
                        'ref_label'         => 'DO ' . ($r['do_number'] ?? ('#' . $r['id'])),
                        'document'          => !empty($r['so_number']) ? ('SO ' . $r['so_number']) : '',
                        'doc_url'           => $docUrl,
                        'counterparty'      => $r['customer_name'] ?? '',
                        'counterparty_role' => !empty($r['customer_name']) ? 'Customer' : '',
                    ];
                }
            } catch (\Throwable $e) {
                foreach ($ids as $id) {
                    $context['delivery_order:' . $id] = ['ref_label' => 'DO #' . $id];
                }
            }
        }

        if (!empty($groups['stock_adjustment'])) {
            foreach (array_keys($groups['stock_adjustment']) as $id) {
                $context['stock_adjustment:' . $id] = ['ref_label' => 'Adj #' . $id];
            }
        }

        if (!empty($groups['internal_transfer'])) {
            $ids = array_keys($groups['internal_transfer']);
            $ph  = implode(',', array_fill(0, count($ids), '?'));
            try {
                $rows = $db->query(
                    "SELECT it.id, it.transfer_number,
                            fl.name AS from_loc, tl.name AS to_loc
                     FROM internal_transfers it
                     LEFT JOIN warehouse_locations fl ON fl.id = it.from_location_id
                     LEFT JOIN warehouse_locations tl ON tl.id = it.to_location_id
                     WHERE it.id IN ($ph)",
                    $ids
                )->getResultArray();
                foreach ($rows as $r) {
                    $context['internal_transfer:' . $r['id']] = [
                        'ref_label' => $r['transfer_number'] ?? ('Transfer #' . $r['id']),
                        'document' => 'From ' . ($r['from_loc'] ?? '?') . ' to ' . ($r['to_loc'] ?? '?'),
                        'counterparty' => 'Internal Transfer',
                        'counterparty_role' => 'Internal',
                    ];
                }
            } catch (\Throwable $e) {
                foreach ($ids as $id) {
                    $context['internal_transfer:' . $id] = ['ref_label' => 'Transfer #' . $id];
                }
            }
        }

        // Fallback for any other reference types
        foreach ($groups as $type => $ids) {
            foreach (array_keys($ids) as $id) {
                $key = $type . ':' . $id;
                if (!isset($context[$key])) {
                    $context[$key] = [
                        'ref_label' => ucfirst(str_replace('_', ' ', $type)) . ' #' . $id,
                    ];
                }
            }
        }

        return $context;
    }

    private function buildNoMovementHints($db, int $productId, int $variantId, string $dateFrom, string $dateTo): array
    {
        $hints = [];

        try {
            $productCount = (int)($db->query(
                'SELECT COUNT(*) AS cnt
                 FROM stock_movements
                 WHERE product_id = ? AND DATE(created_at) >= ? AND DATE(created_at) <= ?',
                [$productId, $dateFrom, $dateTo]
            )->getRowArray()['cnt'] ?? 0);

            if ($productCount > 0) {
                $hints['message'] = 'No movement found for this exact variant in the selected dates, but this product has movement in the same period.';
                $rows = $db->query(
                    'SELECT sm.variant_id, pv.art_number, pv.name,
                            SUM(CASE WHEN sm.qty_change > 0 THEN sm.qty_change ELSE 0 END) AS qty_in,
                            SUM(CASE WHEN sm.qty_change < 0 THEN -sm.qty_change ELSE 0 END) AS qty_out,
                            COUNT(*) AS row_count
                     FROM stock_movements sm
                     LEFT JOIN product_variants pv ON pv.id = sm.variant_id
                     WHERE sm.product_id = ?
                       AND DATE(sm.created_at) >= ?
                       AND DATE(sm.created_at) <= ?
                     GROUP BY sm.variant_id, pv.art_number, pv.name
                     ORDER BY row_count DESC
                     LIMIT 8',
                    [$productId, $dateFrom, $dateTo]
                )->getResultArray();

                $suggestions = [];
                foreach ($rows as $r) {
                    $vid = (int)($r['variant_id'] ?? 0);
                    if ($vid <= 0 || $vid === $variantId) {
                        continue;
                    }
                    $suggestions[] = [
                        'variant_id' => $vid,
                        'label' => trim((string)($r['name'] ?? '')),
                        'art_number' => (string)($r['art_number'] ?? ''),
                        'qty_in' => (float)($r['qty_in'] ?? 0),
                        'qty_out' => (float)($r['qty_out'] ?? 0),
                    ];
                }

                if (!empty($suggestions)) {
                    $hints['suggested_variants'] = $suggestions;
                }
            }
        } catch (\Throwable $e) {
            // Soft-fail only: hints are optional.
        }

        return $hints;
    }

    private function openingBalancesByVariant($db, int $productId, int $variantId, string $dateFrom): array
    {
        $sql = 'SELECT COALESCE(variant_id, 0) AS variant_key, COALESCE(SUM(qty_change), 0) AS qty
                FROM stock_movements
                WHERE product_id = ? AND DATE(created_at) < ?';
        $params = [$productId, $dateFrom];
        if ($variantId > 0) {
            $sql .= ' AND variant_id = ?';
            $params[] = $variantId;
        }
        $sql .= ' GROUP BY COALESCE(variant_id, 0)';

        $out = [];
        $rows = $db->query($sql, $params)->getResultArray();
        foreach ($rows as $r) {
            $out[(int)$r['variant_key']] = (float)$r['qty'];
        }
        return $out;
    }

    private function lastKnownCostByVariant($db, int $productId, int $variantId, string $dateFrom): array
    {
        $sql = 'SELECT COALESCE(sm.variant_id, 0) AS variant_key,
                       COALESCE(NULLIF(sm.unit_cost, 0), pglc.unit_cost, 0) AS resolved_cost
                FROM stock_movements sm
                LEFT JOIN (
                    SELECT grn_id, product_id, variant_id, MAX(unit_cost) AS unit_cost
                    FROM purchase_grn_lines
                    GROUP BY grn_id, product_id, variant_id
                ) pglc
                    ON sm.reference_type = "grn"
                   AND sm.reference_id = pglc.grn_id
                   AND sm.product_id = pglc.product_id
                   AND COALESCE(sm.variant_id, 0) = COALESCE(pglc.variant_id, 0)
                WHERE sm.product_id = ?
                  AND DATE(sm.created_at) < ?';
        $params = [$productId, $dateFrom];
        if ($variantId > 0) {
            $sql .= ' AND sm.variant_id = ?';
            $params[] = $variantId;
        }
        $sql .= ' ORDER BY sm.created_at DESC, sm.id DESC';

        $rows = $db->query($sql, $params)->getResultArray();
        $costByVariant = [];
        foreach ($rows as $r) {
            $k = (int)$r['variant_key'];
            if (isset($costByVariant[$k])) {
                continue;
            }
            $c = (float)($r['resolved_cost'] ?? 0);
            if ($c > 0) {
                $costByVariant[$k] = $c;
            }
        }
        return $costByVariant;
    }

    private function queryVendorOptions($db, int $productId, int $variantId, string $dateFrom, string $dateTo): array
    {
        $sql = 'SELECT DISTINCT v.id, v.name
                FROM stock_movements sm
                LEFT JOIN purchase_grns g ON sm.reference_type = "grn" AND sm.reference_id = g.id
                LEFT JOIN purchase_orders po ON po.id = g.po_id
                LEFT JOIN vendors v ON v.id = COALESCE(sm.possible_vendor_id, g.vendor_id, po.vendor_id)
                WHERE sm.qty_change > 0
                  AND DATE(sm.created_at) >= ?
                  AND DATE(sm.created_at) <= ?
                  AND v.id IS NOT NULL';
        $params = [$dateFrom, $dateTo];
        if ($productId > 0) {
            $sql .= ' AND sm.product_id = ?';
            $params[] = $productId;
        }
        if ($variantId > 0 && $productId > 0) {
            $sql .= ' AND sm.variant_id = ?';
            $params[] = $variantId;
        }
        $sql .= ' ORDER BY v.name ASC';

        $rows = $db->query($sql, $params)->getResultArray();
        $out = [];
        foreach ($rows as $r) {
            $out[] = ['id' => (int)$r['id'], 'name' => (string)$r['name']];
        }
        return $out;
    }

    private function queryCustomerOptions($db, int $productId, int $variantId, string $dateFrom, string $dateTo): array
    {
        $sql = 'SELECT DISTINCT c.id, c.name
                FROM stock_movements sm
                LEFT JOIN delivery_orders d ON sm.reference_type = "delivery_order" AND sm.reference_id = d.id
                LEFT JOIN sales_orders so ON so.id = d.sales_order_id
                LEFT JOIN customers c ON c.id = so.customer_id
                WHERE sm.qty_change < 0
                  AND DATE(sm.created_at) >= ?
                  AND DATE(sm.created_at) <= ?
                  AND c.id IS NOT NULL';
        $params = [$dateFrom, $dateTo];
        if ($productId > 0) {
            $sql .= ' AND sm.product_id = ?';
            $params[] = $productId;
        }
        if ($variantId > 0 && $productId > 0) {
            $sql .= ' AND sm.variant_id = ?';
            $params[] = $variantId;
        }
        $sql .= ' ORDER BY c.name ASC';

        $rows = $db->query($sql, $params)->getResultArray();
        $out = [];
        foreach ($rows as $r) {
            $out[] = ['id' => (int)$r['id'], 'name' => (string)$r['name']];
        }
        return $out;
    }

    private function resolveGrnValuation(array $movements, $db): array
    {
        $keys = [];
        foreach ($movements as $m) {
            if (($m['reference_type'] ?? '') !== 'grn') {
                continue;
            }
            $grnId = (int)($m['reference_id'] ?? 0);
            $productId = (int)($m['product_id'] ?? 0);
            $variantId = (int)($m['variant_id'] ?? 0);
            if ($grnId > 0 && $productId > 0) {
                $keys[] = [$grnId, $productId, $variantId];
            }
        }

        if (empty($keys)) {
            return [];
        }

        $result = [];
        foreach ($keys as $k) {
            [$grnId, $productId, $variantId] = $k;
            try {
                $row = $db->query(
                    'SELECT COALESCE(over_received_qty, 0) AS free_qty,
                            COALESCE(over_receipt_reason_type, "") AS reason
                     FROM purchase_grn_lines
                     WHERE grn_id = ? AND product_id = ? AND COALESCE(variant_id, 0) = ?
                     LIMIT 1',
                    [$grnId, $productId, $variantId]
                )->getRowArray();

                $key    = $grnId . ':' . $productId . ':' . $variantId;
                $reason = (string)($row['reason'] ?? '');
                // Only replacement_free is truly free (no bill). vendor_extra / extra_ordered are paid.
                $isFree = ($reason === 'replacement_free');
                $result[$key] = [
                    'free_qty' => $isFree ? (float)($row['free_qty'] ?? 0) : 0.0,
                    'reason'   => $reason,
                ];
            } catch (\Throwable $e) {
                // Soft-fail: valuation enrichment is best-effort.
            }
        }

        return $result;
    }

    private function movementLabel(string $type): string
    {
        $map = [
            'grn' => 'Purchase Receipt',
            'shipment' => 'Customer Shipment',
            'transfer_out' => 'Internal Transfer Out',
            'transfer_in' => 'Internal Transfer In',
            'subcontract_out' => 'Subcontract Out',
            'subcontract_in' => 'Subcontract In',
            'subcontract_scrap' => 'Subcontract Scrap',
            'subcontract_cancel' => 'Subcontract Cancel',
            'scrap' => 'Scrap',
            'return_to_vendor' => 'Return to Vendor',
            'send_for_repair' => 'Sent for Repair',
            'receive_repaired_back' => 'Repair Received Back',
            'opening_stock' => 'Opening Stock',
            'adjustment' => 'Stock Adjustment',
            'in' => 'Stock In',
        ];

        return $map[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }
}
