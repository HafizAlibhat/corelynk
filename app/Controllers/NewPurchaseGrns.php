<?php

namespace App\Controllers;

use App\Models\PurchaseGrnModel;
use App\Models\PurchaseGrnLineModel;
use App\Models\PurchaseOrderModel;
use App\Models\PurchaseOrderLineModel;
use App\Models\VendorBillLineModel;
use App\Models\VendorBillModel;
use App\Models\GrnReceiptHistoryModel;
use App\Services\InventoryService;
use App\Services\PurchaseOrderStatusService;
use App\Services\GrnPartialReceiptService;
use App\Services\ProcessingBillingValidationService;
use App\Traits\PublicIdTrait;
use Config\Database;

class NewPurchaseGrns extends BaseController
{
    private function normalizeVariantMatchText(?string $value): string
    {
        $text = strtolower(trim(html_entity_decode((string)($value ?? ''), ENT_QUOTES, 'UTF-8')));
        if ($text === '') {
            return '';
        }
        $text = str_replace(['”', '“', "\"", "'"], '', $text);
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?? '';
        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    private function getGrnProductContext($db, int $productId): ?array
    {
        static $cache = [];
        if ($productId <= 0) {
            return null;
        }
        if (array_key_exists($productId, $cache)) {
            return $cache[$productId];
        }
        $row = $db->table('products')
            ->select('id, name, code, sku, product_type, detailed_type')
            ->where('id', $productId)
            ->get()
            ->getRowArray();
        $cache[$productId] = $row ?: null;
        return $cache[$productId];
    }

    private function getGrnVariantsForProduct($db, int $productId): array
    {
        static $cache = [];
        if ($productId <= 0) {
            return [];
        }
        if (array_key_exists($productId, $cache)) {
            return $cache[$productId];
        }
        try {
            $cache[$productId] = $db->table('product_variants')
                ->select('id, product_id, art_number, name, attributes, image')
                ->where('product_id', $productId)
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();
        } catch (\Throwable $_) {
            $cache[$productId] = [];
        }
        return $cache[$productId];
    }

    private function inferVariantIdForPoLine($db, array $line): ?int
    {
        $directVariantId = isset($line['variant_id']) ? (int)$line['variant_id'] : (isset($line['product_variant_id']) ? (int)$line['product_variant_id'] : 0);
        if ($directVariantId > 0) {
            return $directVariantId;
        }

        $productId = (int)($line['product_id'] ?? 0);
        if ($productId <= 0) {
            return null;
        }

        $product = $this->getGrnProductContext($db, $productId);
        $variants = $this->getGrnVariantsForProduct($db, $productId);
        if (empty($variants)) {
            return null;
        }
        if (count($variants) === 1) {
            return (int)($variants[0]['id'] ?? 0) ?: null;
        }

        $lineCode = strtoupper(trim((string)($line['variant_art_number'] ?? ($line['product_code'] ?? ($line['sku'] ?? '')))));
        if ($lineCode !== '') {
            $codeMatches = array_values(array_filter($variants, static function ($variant) use ($lineCode) {
                return strtoupper(trim((string)($variant['art_number'] ?? ''))) === $lineCode;
            }));
            if (count($codeMatches) === 1) {
                return (int)($codeMatches[0]['id'] ?? 0) ?: null;
            }
        }

        $description = $this->normalizeVariantMatchText($line['description'] ?? '');
        $productName = $this->normalizeVariantMatchText($line['product_name'] ?? ($product['name'] ?? ''));
        $textPool = array_values(array_filter([$description, $productName]));
        if (empty($textPool)) {
            return null;
        }

        $matches = [];
        foreach ($variants as $variant) {
            $variantName = $this->normalizeVariantMatchText($variant['name'] ?? '');
            $attributeText = '';
            $attrs = json_decode((string)($variant['attributes'] ?? ''), true);
            if (is_array($attrs)) {
                $attributeText = $this->normalizeVariantMatchText(implode(' ', array_map(static function ($value) {
                    return is_scalar($value) ? (string)$value : '';
                }, $attrs)));
            }

            foreach ($textPool as $candidate) {
                if ($candidate === '') {
                    continue;
                }
                if ($variantName !== '' && ($candidate === $variantName || strpos($candidate, $variantName) !== false || strpos($variantName, $candidate) !== false)) {
                    $matches[(int)$variant['id']] = (int)$variant['id'];
                    continue 2;
                }
                if ($attributeText !== '' && strpos($candidate, $attributeText) !== false) {
                    $matches[(int)$variant['id']] = (int)$variant['id'];
                    continue 2;
                }
            }
        }

        return count($matches) === 1 ? reset($matches) : null;
    }

    private function persistPoLineVariantId($db, int $poLineId, int $variantId): void
    {
        if ($poLineId <= 0 || $variantId <= 0) {
            return;
        }
        try {
            $fields = array_flip($db->getFieldNames('purchase_order_lines'));
            if (!isset($fields['variant_id'])) {
                return;
            }
            $db->table('purchase_order_lines')
                ->where('id', $poLineId)
                ->where('(variant_id IS NULL OR variant_id = 0)', null, false)
                ->update(['variant_id' => $variantId]);
        } catch (\Throwable $_) {
            // best-effort healing for legacy rows
        }
    }

    // List GRNs (read-only)
    public function list()
    {
        if (strtolower($this->request->getMethod()) !== 'get') { return $this->response->setStatusCode(405)->setBody('Method not allowed'); }
        $db = Database::connect();
        $this->ensureGrnPublicIds($db);
        $hasGrnPublicId = $db->fieldExists('public_id', 'purchase_grns');

        $movementSub = $db->table('stock_movements')
            ->select('reference_id as grn_id, MIN(warehouse_id) as warehouse_id, MIN(location_id) as location_id')
            ->where('reference_type', 'grn')
            ->groupBy('reference_id');

        $lineCountSub = $db->table('purchase_grn_lines')
            ->select('grn_id, COUNT(*) as line_count')
            ->groupBy('grn_id');

        $grnSelect = "g.id, g.grn_number, g.po_id, g.vendor_id, g.received_at, g.created_at, po.po_number, v.name as vendor_name, w.name as warehouse_name, l.name as location_name, u.username as created_by_username, TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) as created_by_name";
        if ($hasGrnPublicId) {
            $grnSelect = "g.id, g.public_id, " . substr($grnSelect, 5);
        }

        $rows = $db->table('purchase_grns g')
            ->select($grnSelect)
            ->join('purchase_orders po', 'po.id = g.po_id', 'left')
            ->join('vendors v', 'v.id = g.vendor_id', 'left')
            ->join('('.$movementSub->getCompiledSelect().') sm', 'sm.grn_id = g.id', 'left', false)
            ->join('('.$lineCountSub->getCompiledSelect().') gl', 'gl.grn_id = g.id', 'left', false)
            ->join('warehouses w', 'w.id = sm.warehouse_id', 'left')
            ->join('warehouse_locations l', 'l.id = sm.location_id', 'left')
            ->join('users u', 'u.id = g.created_by', 'left')
            ->where('(COALESCE(gl.line_count, 0) > 0 OR sm.grn_id IS NOT NULL)', null, false)
            ->orderBy('g.id', 'DESC')
            ->get()
            ->getResultArray();

        return view('purchase_ui/grn_list', ['grns' => $rows]);
    }

    // GRN detail (read-only)
    public function detail($grnId = null)
    {
        if (strtolower($this->request->getMethod()) !== 'get') { return $this->response->setStatusCode(405)->setBody('Method not allowed'); }
        $grnKey = trim((string)$grnId);
        if ($grnKey === '') { return $this->response->setStatusCode(404)->setBody('GRN not found'); }
        $db = Database::connect();
        $this->ensureGrnPublicIds($db);
        $hasGrnPublicId = $db->fieldExists('public_id', 'purchase_grns');

        $movementSub = $db->table('stock_movements')
            ->select('reference_id as grn_id, MIN(warehouse_id) as warehouse_id, MIN(location_id) as location_id')
            ->where('reference_type', 'grn')
            ->groupBy('reference_id');

        $grnBuilder = $db->table('purchase_grns g')
            ->select("g.*, po.po_number, po.public_id as po_public_id, sm.warehouse_id as movement_warehouse_id, sm.location_id as movement_location_id, v.name as vendor_name, v.public_id as vendor_public_id, w.name as warehouse_name, l.name as location_name, u.username as created_by_username, TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) as created_by_name")
            ->join('purchase_orders po', 'po.id = g.po_id', 'left')
            ->join('vendors v', 'v.id = g.vendor_id', 'left')
            ->join('('.$movementSub->getCompiledSelect().') sm', 'sm.grn_id = g.id', 'left', false)
            ->join('warehouses w', 'w.id = sm.warehouse_id', 'left')
            ->join('warehouse_locations l', 'l.id = sm.location_id', 'left')
            ->join('users u', 'u.id = g.created_by', 'left');

        $grnBuilder->groupStart();
        if ($hasGrnPublicId) {
            $grnBuilder->where('g.public_id', $grnKey)->orWhere('g.grn_number', $grnKey);
        } else {
            $grnBuilder->where('g.grn_number', $grnKey);
        }
        $grnBuilder->orWhere('g.id', is_numeric($grnKey) ? (int)$grnKey : 0);
        $grnBuilder->groupEnd();

        $grn = $grnBuilder->get()->getRowArray();

        if (!$grn) { return $this->response->setStatusCode(404)->setBody('GRN not found'); }
        $grnId = (int)($grn['id'] ?? 0);
        if ($grnId <= 0) { return $this->response->setStatusCode(404)->setBody('GRN not found'); }
        $grnPublicId = trim((string)($grn['public_id'] ?? ''));
        if ($grnPublicId !== '' && $grnKey !== $grnPublicId) {
            return redirect()->to(site_url('new-purchase-grns/detail/'.$grnPublicId));
        }

        $grn['location_path'] = '';
        $locId = (int)($grn['movement_location_id'] ?? 0);
        if ($locId > 0) {
            try {
                $locRows = $db->table('warehouse_locations')
                    ->select('id, name, parent_id, warehouse_id, is_active')
                    ->where('id', $locId)
                    ->get()
                    ->getResultArray();
                $withPath = $this->attachLocationPaths($locRows);
                if (!empty($withPath[0]['display_name'])) {
                    $grn['location_path'] = (string)$withPath[0]['display_name'];
                }
            } catch (\Throwable $_) {
                $grn['location_path'] = '';
            }
        }

        $productFields = array_flip($db->getFieldNames('products'));
        $lineSelect = 'gl.*, pol.qty as ordered_qty, p.name as product_name, p.sku as product_sku, p.code as product_code, p.images as product_images, p.unit as product_unit, p.public_id as product_public_id';
        if (isset($productFields['weight'])) {
            $lineSelect .= ', p.weight as product_weight';
        }
        if (isset($productFields['unit_weight'])) {
            $lineSelect .= ', p.unit_weight as product_unit_weight';
        }
        if (isset($productFields['weight_unit'])) {
            $lineSelect .= ', p.weight_unit as product_weight_unit';
        }

        $lineBuilder = $db->table('purchase_grn_lines gl')
            ->select($lineSelect)
            ->join('purchase_order_lines pol', 'pol.id = gl.po_line_id', 'left')
            ->join('products p', 'p.id = gl.product_id', 'left')
            ->where('gl.grn_id', $grnId);
        if ($db->fieldExists('variant_id', 'purchase_grn_lines')) {
            $lineBuilder->select('pv.art_number as variant_art_number, pv.name as variant_name, pv.image as variant_image');
            $lineBuilder->join('product_variants pv', 'pv.id = gl.variant_id', 'left');
        }
        $lines = $lineBuilder->get()->getResultArray();

        // Legacy/orphan guard: if this GRN has no persisted lines and no stock movement,
        // redirect to the latest sibling GRN on the same PO that actually has lines.
        if (empty($lines) && (int)($grn['po_id'] ?? 0) > 0) {
            try {
                $hasMovement = $db->table('stock_movements')
                    ->where('reference_type', 'grn')
                    ->where('reference_id', $grnId)
                    ->countAllResults() > 0;

                if (!$hasMovement && $db->tableExists('purchase_grn_lines')) {
                    $replacement = $db->table('purchase_grns g2')
                        ->select('g2.id, g2.public_id')
                        ->join('(SELECT grn_id, COUNT(*) AS line_count FROM purchase_grn_lines GROUP BY grn_id) gl2', 'gl2.grn_id = g2.id', 'inner', false)
                        ->where('g2.po_id', (int)$grn['po_id'])
                        ->where('g2.id !=', $grnId)
                        ->orderBy('g2.id', 'DESC')
                        ->get()
                        ->getRowArray();

                    if (!empty($replacement['id'])) {
                        $target = !empty($replacement['public_id']) ? (string)$replacement['public_id'] : (string)((int)$replacement['id']);
                        return redirect()->to(site_url('new-purchase-grns/detail/' . $target))
                            ->with('warning', 'Selected GRN had no received lines. Opened the latest GRN with recorded received items for this PO.');
                    }
                }
            } catch (\Throwable $_) {
                // Keep detail page resilient even if orphan recovery probe fails.
            }
        }

        $this->ensureIssueLogTable($db);
        $issueSummary = [];
        $issueHistory = [];
        try {
            $issueRows = $db->table('purchase_grn_line_issues')
                ->where('grn_id', $grnId)
                ->orderBy('action_date', 'DESC')
                ->orderBy('id', 'DESC')
                ->get()
                ->getResultArray();
            $issueHistory = $issueRows;

            foreach ($issueRows as $ir) {
                $lineId = (int)($ir['grn_line_id'] ?? 0);
                if ($lineId <= 0) {
                    continue;
                }
                if (!isset($issueSummary[$lineId])) {
                    $issueSummary[$lineId] = [
                        'scrap' => 0.0,
                        'return_to_vendor' => 0.0,
                        'send_for_repair' => 0.0,
                        'receive_repaired_back' => 0.0,
                    ];
                }
                $type = (string)($ir['action_type'] ?? '');
                $qty = (float)($ir['qty'] ?? 0);
                if (isset($issueSummary[$lineId][$type])) {
                    $issueSummary[$lineId][$type] += $qty;
                }
            }
        } catch (\Throwable $_) {
            $issueSummary = [];
            $issueHistory = [];
        }

        $relatedBills = [];
        try {
            if ($db->tableExists('vendor_bills')) {
                $billFields = array_flip($db->getFieldNames('vendor_bills'));
                if (isset($billFields['po_id']) && (int)($grn['po_id'] ?? 0) > 0) {
                    $billRefExpr = 'CONCAT("VB-", vb.id)';
                    if (isset($billFields['bill_number']) && isset($billFields['vendor_bill_number'])) {
                        $billRefExpr = 'COALESCE(vb.bill_number, vb.vendor_bill_number, CONCAT("VB-", vb.id))';
                    } elseif (isset($billFields['bill_number'])) {
                        $billRefExpr = 'COALESCE(vb.bill_number, CONCAT("VB-", vb.id))';
                    } elseif (isset($billFields['vendor_bill_number'])) {
                        $billRefExpr = 'COALESCE(vb.vendor_bill_number, CONCAT("VB-", vb.id))';
                    }

                    $statusExpr = isset($billFields['status']) ? 'vb.status' : "'draft'";
                    $totalExpr = isset($billFields['total_amount']) ? 'vb.total_amount' : '0';
                    $balanceExpr = isset($billFields['balance']) ? 'vb.balance' : '0';
                    $dateExpr = isset($billFields['bill_date']) ? 'vb.bill_date' : 'NULL';
                    $publicIdExpr = isset($billFields['public_id']) ? 'vb.public_id' : 'NULL';
                    $basedOnExpr = isset($billFields['based_on']) ? 'vb.based_on' : "''";

                    $relatedBills = $db->table('vendor_bills vb')
                        ->select("vb.id, {$publicIdExpr} as public_id, {$billRefExpr} as bill_ref, {$statusExpr} as status, {$totalExpr} as total_amount, {$balanceExpr} as balance, {$dateExpr} as bill_date, {$basedOnExpr} as based_on")
                        ->where('vb.po_id', (int)$grn['po_id'])
                        ->orderBy('vb.id', 'DESC')
                        ->limit(8)
                        ->get()
                        ->getResultArray();

                    // Recalculate paid/balance from posted payment allocations to avoid stale vendor_bills.balance values.
                    if (!empty($relatedBills) && $db->tableExists('vendor_payment_allocations') && $db->tableExists('vendor_payments')) {
                        $billIds = array_values(array_filter(array_map(static function ($rb) {
                            return isset($rb['id']) ? (int)$rb['id'] : 0;
                        }, $relatedBills)));

                        if (!empty($billIds)) {
                            $paidRows = $db->table('vendor_payment_allocations vpa')
                                ->select("vpa.vendor_bill_id, SUM(COALESCE(NULLIF(vpa.amount_allocated, 0), vpa.amount, 0)) as paid_amount")
                                ->join('vendor_payments vp', 'vp.id = vpa.payment_id', 'inner')
                                ->whereIn('vpa.vendor_bill_id', $billIds)
                                ->where('vp.status', 'posted')
                                ->groupBy('vpa.vendor_bill_id')
                                ->get()
                                ->getResultArray();

                            $paidMap = [];
                            foreach ($paidRows as $pr) {
                                $paidMap[(int)($pr['vendor_bill_id'] ?? 0)] = (float)($pr['paid_amount'] ?? 0);
                            }

                            foreach ($relatedBills as &$rb) {
                                $bid = (int)($rb['id'] ?? 0);
                                $totalAmt = (float)($rb['total_amount'] ?? 0);
                                $paidAmt = (float)($paidMap[$bid] ?? 0);
                                $dynBalance = max(0.0, $totalAmt - $paidAmt);
                                $rb['paid_amount'] = $paidAmt;
                                $rb['balance'] = $dynBalance;
                                $rb['is_paid'] = $dynBalance <= 0.0001;
                            }
                            unset($rb);
                        }
                    }
                }
            }
        } catch (\Throwable $_) {
            $relatedBills = [];
        }

        // Backfill legacy blank over-receipt reasons from persisted business evidence.
        try {
            if (!empty($lines)) {
                $lineFields = array_flip($db->getFieldNames('purchase_grn_lines'));
                $canSaveReason = isset($lineFields['over_receipt_reason_type']);
                $canSaveDetails = isset($lineFields['over_receipt_reason_details']);

                $inferredReason = '';
                $notesText = strtolower(trim((string)($grn['notes'] ?? '')));
                if ($notesText !== '') {
                    if (strpos($notesText, 'free replacement') !== false) {
                        $inferredReason = 'replacement_free';
                    } elseif (strpos($notesText, 'extra ordered') !== false) {
                        $inferredReason = 'extra_ordered';
                    } elseif (strpos($notesText, 'vendor sent extra') !== false) {
                        $inferredReason = 'vendor_extra';
                    }
                }

                if ($inferredReason === '' && !empty($relatedBills)) {
                    foreach ($relatedBills as $rb) {
                        $basedOn = strtolower(trim((string)($rb['based_on'] ?? '')));
                        if ($basedOn === 'po_over_receipt') {
                            $inferredReason = 'vendor_extra';
                            break;
                        }
                    }
                }

                if ($inferredReason === '' && !empty($relatedBills)) {
                    // Legacy fallback: multiple bills on same PO with one small delta bill implies payable vendor extra.
                    if (count($relatedBills) > 1) {
                        $inferredReason = 'vendor_extra';
                    }
                }

                if ($inferredReason !== '' && $canSaveReason) {
                    foreach ($lines as &$lineRef) {
                        $lineOver = (float)($lineRef['over_received_qty'] ?? 0);
                        $lineReason = trim((string)($lineRef['over_receipt_reason_type'] ?? ''));
                        if ($lineOver <= 0.0001 || $lineReason !== '') {
                            continue;
                        }
                        $lineId = (int)($lineRef['id'] ?? 0);
                        if ($lineId <= 0) {
                            continue;
                        }
                        $updateData = ['over_receipt_reason_type' => $inferredReason];
                        if ($canSaveDetails && trim((string)($lineRef['over_receipt_reason_details'] ?? '')) === '') {
                            $updateData['over_receipt_reason_details'] = 'Backfilled from bill/GRN evidence';
                        }
                        $db->table('purchase_grn_lines')->where('id', $lineId)->update($updateData);
                        $lineRef['over_receipt_reason_type'] = $inferredReason;
                        if (isset($updateData['over_receipt_reason_details'])) {
                            $lineRef['over_receipt_reason_details'] = $updateData['over_receipt_reason_details'];
                        }
                    }
                    unset($lineRef);
                }
            }
        } catch (\Throwable $_) {
            // keep detail page resilient if backfill fails
        }

        return view('purchase_ui/grn_detail', [
            'grn' => $grn,
            'lines' => $lines,
            'issueSummary' => $issueSummary,
            'issueHistory' => $issueHistory,
            'relatedBills' => $relatedBills,
        ]);
    }

    // Create GRN for a confirmed PO and route stock increases via InventoryGuard
    public function create()
    {
        log_message('debug', 'GRN create method called');
        $method = strtolower($this->request->getMethod());
        log_message('debug', 'GRN create method: ' . $method);
        if ($method === 'options') {
            return $this->response->setStatusCode(204);
        }
        if ($method !== 'post') {
            return $this->response
                ->setStatusCode(405)
                ->setJSON(['error' => 'Method not allowed', 'method' => $method]);
        }

        $data = $this->request->getJSON(true) ?: $this->request->getPost();
        log_message('debug', 'GRN create data: ' . json_encode($data));
        $poId = isset($data['po_id']) ? (int)$data['po_id'] : 0;
        $lines = $data['lines'] ?? [];
        $receivedDate = trim((string)($data['received_at'] ?? ''));
        // Backward-compatible fallback for older clients that still send a single reason.
        $legacyOverReasonType = strtolower(trim((string)($data['over_receipt_reason_type'] ?? '')));
        $legacyOverReasonDetails = trim((string)($data['over_receipt_reason_details'] ?? ''));
        $actorUserId = (int)(session()->get('user_id') ?: session()->get('id') ?: 0);
        if ($poId <= 0) return $this->response->setStatusCode(400)->setJSON(['error' => 'PO id required']);

        $receivedAt = date('Y-m-d H:i:s');
        if ($receivedDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $receivedDate)) {
            $receivedAt = $receivedDate . ' ' . date('H:i:s');
        }

        $db = Database::connect();
        $grnId = 0;
        $txStarted = false;
        try {
            $this->ensureGrnPublicIds($db);
            $poModel = new PurchaseOrderModel();
            $po = $poModel->find($poId);
            if (!$po) throw new \RuntimeException('PO not found');
            $poStatus = strtolower((string)($po['status'] ?? ''));
            if (!in_array($poStatus, ['confirmed', 'partial'], true)) {
                throw new \RuntimeException('GRN allowed only for confirmed or partial PO');
            }

            $warehouseId = isset($data['warehouse_id']) ? (int)$data['warehouse_id'] : 0;
            if ($warehouseId <= 0) throw new \RuntimeException('Warehouse is required for GRN');

            // Ensure warehouse exists
            $wh = $db->table('warehouses')->where('id', $warehouseId)->get()->getRowArray();
            if (!$wh || (int)$wh['is_active'] === 0) throw new \RuntimeException('Warehouse not found or inactive');

            // Ensure a location is provided; allow inline creation of a new stock location
            $locationId = isset($data['location_id']) ? (int)$data['location_id'] : 0;
            if (!$locationId && !empty($data['location_name'])) {
                $parentId = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
                $locationId = $this->createLocation($db, $warehouseId, $data['location_name'], $parentId);
            }
            if ($locationId <= 0) throw new \RuntimeException('Location is required for GRN');

            // verify location belongs to warehouse
            $loc = $db->table('warehouse_locations')->where('id', $locationId)->get()->getRowArray();
            if (!$loc) throw new \RuntimeException('Location not found');
            if ((int)$loc['warehouse_id'] !== $warehouseId) throw new \RuntimeException('Location must belong to selected warehouse');
            if (isset($loc['is_active']) && (int)$loc['is_active'] === 0) throw new \RuntimeException('Location inactive');

            // Pre-validate over-receipt split before any DB write.
            $poLineProbeModel = new PurchaseOrderLineModel();
            foreach ($lines as $lnProbe) {
                $probeQty = isset($lnProbe['qty_received']) ? (float)$lnProbe['qty_received'] : 0;
                if ($probeQty <= 0) {
                    continue;
                }
                $probePoLineId = isset($lnProbe['po_line_id']) ? (int)$lnProbe['po_line_id'] : 0;
                $probePoLine = $probePoLineId > 0 ? $poLineProbeModel->find($probePoLineId) : null;
                $probeOrdered = isset($probePoLine['qty']) ? (float)$probePoLine['qty'] : 0;
                $probeReceived = isset($probePoLine['qty_received']) ? (float)$probePoLine['qty_received'] : 0;
                $probePending = $probeOrdered - $probeReceived;
                $probeOver = max(0.0, round($probeQty - max(0.0, $probePending), 4));
                if ($probeOver > 0.0001) {
                    $probeSplit = $this->normalizeOverReceiptSplit($lnProbe, $probeOver, $legacyOverReasonType);
                    if (empty($probeSplit['valid'])) {
                        throw new \RuntimeException($probeSplit['error'] ?? 'Invalid over-receipt split provided.');
                    }
                }
            }

            // Start transaction before first insert/update so rollback can undo all writes.
            $db->transBegin();
            $txStarted = true;

            $grnModel = new PurchaseGrnModel();
            $grnLineModel = new PurchaseGrnLineModel();

            $grnNumber = $this->nextGrnNumber($db);

            $grnId = (int)$grnModel->insert([
                'grn_number' => $grnNumber,
                'po_id' => $poId,
                'vendor_id' => $po['vendor_id'] ?? null,
                'received_at' => $receivedAt,
                'created_by' => $actorUserId > 0 ? $actorUserId : null,
                'created_at' => date('Y-m-d H:i:s')
            ], true);
            if (!$grnId) throw new \RuntimeException('GRN insert failed');

            // Ensure optional reason tracking columns exist for auditability.
            try {
                $lineCols = $db->getFieldNames('purchase_grn_lines');
                if (!in_array('over_receipt_reason_type', $lineCols, true)) {
                    $db->query("ALTER TABLE purchase_grn_lines ADD COLUMN over_receipt_reason_type VARCHAR(50) NULL AFTER over_received_qty");
                }
                if (!in_array('over_receipt_reason_details', $lineCols, true)) {
                    $db->query("ALTER TABLE purchase_grn_lines ADD COLUMN over_receipt_reason_details TEXT NULL AFTER over_receipt_reason_type");
                }
                if (!in_array('over_receipt_split_json', $lineCols, true)) {
                    $db->query("ALTER TABLE purchase_grn_lines ADD COLUMN over_receipt_split_json TEXT NULL AFTER over_receipt_reason_details");
                }
            } catch (\Throwable $_) {
                // Keep flow resilient on restricted DB permissions.
            }

            $poLineModel = new PurchaseOrderLineModel();
            $extraLines = [];
            $hasOverReceipt = false;
            $hasBillableOverReceipt = false;
            $reasonSummary = [];
            $processedLines = 0;
            $billWarning = null;
            $processedGrnLines = [];  // Track processed lines for partial receipt service

            $inventory = new InventoryService();
            foreach ($lines as $ln) {
                $poLineId = isset($ln['po_line_id']) ? (int)$ln['po_line_id'] : null;
                $qtyReceived = isset($ln['qty_received']) ? (float)$ln['qty_received'] : 0;
                if ($qtyReceived <= 0) continue;

                // Fetch PO line for unit cost and product mapping (optional)
                $poLine = $poLineId ? $poLineModel->find($poLineId) : null;
                $productId = $poLine ? (int)$poLine['product_id'] : (isset($ln['product_id']) ? (int)$ln['product_id'] : null);
                $variantId = isset($ln['variant_id']) && (int)$ln['variant_id'] > 0 ? (int)$ln['variant_id'] : null;
                if (!$variantId && $poLine) {
                    $variantId = $this->inferVariantIdForPoLine($db, array_merge($poLine, [
                        'description' => $ln['description'] ?? ($poLine['description'] ?? null),
                        'product_name' => $ln['product_name'] ?? null,
                        'product_code' => $ln['product_code'] ?? null,
                    ]));
                    if ($variantId) {
                        $this->persistPoLineVariantId($db, (int)$poLineId, (int)$variantId);
                    }
                }
                // unit_price is the canonical deployed column; tolerate legacy unit_cost
                $unitPrice = $poLine ? (float)($poLine['unit_price'] ?? $poLine['unit_cost'] ?? null) : (isset($ln['unit_price']) ? (float)$ln['unit_price'] : (isset($ln['unit_cost']) ? (float)$ln['unit_cost'] : null));

                if ($productId) {
                    $productCtx = $this->getGrnProductContext($db, (int)$productId);
                    if (!$productCtx) {
                        throw new \RuntimeException('PO line ' . (int)$poLineId . ' references a missing product and cannot be received.');
                    }
                    if ((string)($productCtx['product_type'] ?? 'simple') === 'variable' && !$variantId) {
                        $lineLabel = trim((string)($ln['description'] ?? ($productCtx['name'] ?? ('PO line #' . (int)$poLineId))));
                        throw new \RuntimeException('Variant is missing for PO line ' . (int)$poLineId . ' (' . $lineLabel . '). Save a specific variant on the PO line before receiving.');
                    }
                }

                // Per-line location: allow override per product or use default
                $lineLocationId = isset($ln['location_id']) && (int)$ln['location_id'] > 0 ? (int)$ln['location_id'] : $locationId;

                // STRICT VALIDATION: Over-receipt split handling (payable + free on same line).
                $orderedQty = isset($poLine['qty']) ? (float)$poLine['qty'] : 0;
                $alreadyReceived = isset($poLine['qty_received']) ? (float)$poLine['qty_received'] : 0;
                $pendingQty = $orderedQty - $alreadyReceived;
                $overReceived = max(0.0, round($qtyReceived - max(0.0, $pendingQty), 4));

                $lineReasonDetails = trim((string)($ln['over_receipt_reason_details'] ?? ''));
                if ($lineReasonDetails === '') {
                    $lineReasonDetails = $legacyOverReasonDetails;
                }

                $lineReasonType = '';
                $linePayableQty = 0.0;
                $lineFreeQty = 0.0;
                $linePayableReasonType = '';
                $lineSplitJson = null;
                if ($overReceived > 0.0001) {
                    $hasOverReceipt = true;

                    $lineSplit = $this->normalizeOverReceiptSplit($ln, $overReceived, $legacyOverReasonType);
                    if (empty($lineSplit['valid'])) {
                        throw new \RuntimeException($lineSplit['error'] ?? 'Missing or invalid over-receipt split.');
                    }

                    $lineReasonType = (string)($lineSplit['resolved_reason_type'] ?? '');
                    $linePayableQty = (float)($lineSplit['payable_qty'] ?? 0);
                    $lineFreeQty = (float)($lineSplit['free_qty'] ?? 0);
                    $linePayableReasonType = (string)($lineSplit['payable_reason_type'] ?? '');

                    if ($linePayableQty > 0.0001) {
                        $reasonKey = $linePayableReasonType !== '' ? $linePayableReasonType : 'vendor_extra';
                        if (!isset($reasonSummary[$reasonKey])) {
                            $reasonSummary[$reasonKey] = 0.0;
                        }
                        $reasonSummary[$reasonKey] += $linePayableQty;
                        $hasBillableOverReceipt = true;
                    }
                    if ($lineFreeQty > 0.0001) {
                        if (!isset($reasonSummary['replacement_free'])) {
                            $reasonSummary['replacement_free'] = 0.0;
                        }
                        $reasonSummary['replacement_free'] += $lineFreeQty;
                    }

                    $lineSplitJson = json_encode([
                        'over_qty' => round($overReceived, 4),
                        'payable_qty' => round($linePayableQty, 4),
                        'free_qty' => round($lineFreeQty, 4),
                        'payable_reason_type' => $linePayableReasonType,
                        'free_reason_type' => $lineFreeQty > 0.0001 ? 'replacement_free' : null,
                    ], JSON_UNESCAPED_SLASHES);
                }

                $grnLineData = [
                    'grn_id' => $grnId,
                    'po_line_id' => $poLineId,
                    'product_id' => $productId,
                    'variant_id' => $variantId,
                    'description' => $ln['description'] ?? null,
                    'qty_received' => $qtyReceived,
                    'over_received_qty' => $overReceived,
                    'over_receipt_reason_type' => $overReceived > 0.0001 ? $lineReasonType : null,
                    'over_receipt_reason_details' => $overReceived > 0.0001 ? ($lineReasonDetails ?: null) : null,
                    'over_receipt_split_json' => $overReceived > 0.0001 ? $lineSplitJson : null,
                    'unit_cost' => $unitPrice,
                    'warehouse_id' => $warehouseId,
                    'location_id' => $lineLocationId,
                    'created_at' => date('Y-m-d H:i:s')
                ];

                // Column-safety: tolerate schemas that use unit_price or unit_cost
                $grnLineFields = array_flip(Database::connect()->getFieldNames('purchase_grn_lines'));
                if (isset($grnLineFields['unit_price']) && !isset($grnLineFields['unit_cost'])) {
                    $grnLineData['unit_price'] = $grnLineData['unit_cost'];
                }
                $grnLineData = array_intersect_key($grnLineData, $grnLineFields ?: $grnLineData);

                $insertedLineId = $grnLineModel->insert($grnLineData, true);
                if (!$insertedLineId) {
                    $errs = method_exists($grnLineModel, 'errors') ? $grnLineModel->errors() : [];
                    throw new \RuntimeException('Failed to save GRN line' . (!empty($errs) ? ': ' . json_encode($errs) : ''));
                }

                // Track for partial receipt service
                if ($poLineId) {
                    $qtyAppliedToPo = min($qtyReceived, max(0.0, $pendingQty));
                    $processedGrnLines[] = [
                        'po_line_id' => $poLineId,
                        'grn_line_id' => $insertedLineId,
                        'qty_received' => $qtyAppliedToPo,
                        'previous_qty_received' => (float)($poLine['qty_received'] ?? 0),
                        'ordered_qty' => $orderedQty,
                    ];
                }

                // Update PO line qty_received if applicable
                if ($poLineId && $poLine) {
                    $qtyAppliedToPo = min($qtyReceived, max(0.0, $pendingQty));
                    $newReceived = (float)$poLine['qty_received'] + $qtyAppliedToPo;
                    $updated = $poLineModel->update($poLineId, ['qty_received' => $newReceived]);
                    if ($updated === false) {
                        $errs = method_exists($poLineModel, 'errors') ? $poLineModel->errors() : [];
                        throw new \RuntimeException('Failed to update PO received quantity' . (!empty($errs) ? ': ' . json_encode($errs) : ''));
                    }

                    if ($overReceived > 0.0001 && $linePayableQty > 0.0001) {
                        $extraLines[] = [
                            'po_line_id' => (int)$poLineId,
                            'product_id' => $productId,
                            'variant_id' => $variantId,
                            'description' => $ln['description'] ?? ($poLine['description'] ?? null),
                            'unit_price' => $unitPrice,
                            'extra_qty' => $linePayableQty,
                            'ordered_qty' => $orderedQty,
                            'new_received_qty' => $newReceived,
                            'reason_type' => $linePayableReasonType ?: 'vendor_extra',
                            'reason_details' => $lineReasonDetails,
                        ];
                    }
                }

                // Route stock increase only through InventoryService
                if ($productId) {
                    $inventory->receiveFromGrn($productId, $warehouseId, $lineLocationId, $qtyReceived, $grnId, $actorUserId, $variantId);
                }

                $processedLines++;
            }

            if ($processedLines === 0) {
                throw new \RuntimeException('No valid receive quantities found to create GRN');
            }

            if ($hasOverReceipt) {
                $reasonLabels = [
                    'vendor_extra' => 'Vendor sent extra quantity',
                    'extra_ordered' => 'Extra ordered after PO',
                    'replacement_free' => 'Free replacement',
                ];
                $parts = [];
                foreach ($reasonSummary as $reasonKey => $qty) {
                    $parts[] = ($reasonLabels[$reasonKey] ?? $reasonKey) . ': ' . number_format((float)$qty, 2);
                }
                $noteText = 'Over-receipt breakdown: ' . implode(' | ', $parts);
                try {
                    $grnColumns = $db->getFieldNames('purchase_grns');
                    if (is_array($grnColumns) && in_array('notes', $grnColumns, true)) {
                        $db->table('purchase_grns')->where('id', $grnId)->update(['notes' => $noteText]);
                    }
                } catch (\Throwable $_) {
                    // best effort
                }
            }

            $extraBillId = null;
            if ($hasBillableOverReceipt && !empty($extraLines)) {
                try {
                    $extraBillId = $this->createOverReceiptAdjustmentBill(
                        $db,
                        $po,
                        $poId,
                        $grnId,
                        $receivedAt,
                        $extraLines
                    );
                } catch (\Throwable $billErr) {
                    // Keep receiving flow successful even if adjustment bill cannot be created.
                    $billWarning = 'GRN saved, but extra adjustment bill could not be created: ' . $billErr->getMessage();
                    log_message('error', 'Over-receipt adjustment bill failed for PO ' . $poId . ': ' . $billErr->getMessage());
                }
            }

            // Check if user wants to close PO and cancel backorder
            $closePo = !empty($data['close_po']);
            
            // INTEGRATE PARTIAL RECEIPT SERVICE: Record receipt history and update line statuses
            // This ensures proper tracking of partial receipts with audit trail
            try {
                $receiptService = new GrnPartialReceiptService();
                $receiptResult = $receiptService->processGrnCreation($grnId, $processedGrnLines, $actorUserId);
                if (!$receiptResult['success']) {
                    log_message('warning', 'Partial receipt tracking failed for GRN ' . $grnId . ': ' . $receiptResult['message']);
                }
            } catch (\Throwable $receiptErr) {
                // Keep GRN creation successful even if receipt history tracking fails
                log_message('error', 'Receipt history recording failed for GRN ' . $grnId . ': ' . $receiptErr->getMessage());
            }
            
            // AUTO-UPDATE PO status based on received quantities
            // (unless manually closed)
            $statusService = new PurchaseOrderStatusService();
            if (!$closePo) {
                $newStatus = $statusService->deriveAndUpdateStatus($poId);
            } else {
                // Manual close requested
                $poModel->update($poId, ['status' => 'closed']);
            }

            $db->transCommit();
            return $this->response->setJSON([
                'success' => true,
                'grn_id' => $grnId,
                'extra_bill_id' => $extraBillId,
                'needs_extra_bill' => $hasBillableOverReceipt && !$extraBillId,
                'warning' => $billWarning,
                'message' => $extraBillId
                    ? ('GRN created. Extra-quantity adjustment bill #' . $extraBillId . ' created in draft.')
                    : ($billWarning
                        ? 'GRN created successfully (adjustment bill pending manual creation).'
                        : ($hasBillableOverReceipt
                            ? 'GRN created. Billable extra quantity was recorded. Create the base bill first, then create the separate extra bill from the PO page.'
                            : ($hasOverReceipt && !$hasBillableOverReceipt
                            ? 'GRN created. Extra quantity recorded as non-payable (free replacement).'
                            : 'GRN created successfully'))),
            ]);
        } catch (\Throwable $e) {
            if ($txStarted) {
                $db->transRollback();
            }

            // Best-effort orphan cleanup for non-transactional tables/environments.
            if ($grnId > 0) {
                try {
                    if ($db->tableExists('purchase_grn_lines')) {
                        $db->table('purchase_grn_lines')->where('grn_id', $grnId)->delete();
                    }
                    $db->table('purchase_grns')->where('id', $grnId)->delete();
                } catch (\Throwable $_) {
                    // Keep original failure path; cleanup is best effort only.
                }
            }

            log_message('error', 'GRN create failed: '.$e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['error' => 'Failed to create GRN', 'message' => $e->getMessage()]);
        }
    }

    private function normalizeOverReceiptSplit(array $line, float $overQty, string $legacyReasonType = ''): array
    {
        $overQty = max(0.0, round($overQty, 4));
        if ($overQty <= 0.0001) {
            return [
                'valid' => true,
                'resolved_reason_type' => '',
                'payable_qty' => 0.0,
                'free_qty' => 0.0,
                'payable_reason_type' => '',
            ];
        }

        $reasonType = strtolower(trim((string)($line['over_receipt_reason_type'] ?? '')));
        if ($reasonType === '') {
            $reasonType = strtolower(trim($legacyReasonType));
        }

        $payableQty = isset($line['over_receipt_payable_qty']) ? (float)$line['over_receipt_payable_qty'] : 0.0;
        $freeQty = isset($line['over_receipt_free_qty']) ? (float)$line['over_receipt_free_qty'] : 0.0;
        $payableReasonType = strtolower(trim((string)($line['over_receipt_payable_reason_type'] ?? '')));

        $payableQty = max(0.0, round($payableQty, 4));
        $freeQty = max(0.0, round($freeQty, 4));

        // Backward compatibility with single-reason clients.
        if ($payableQty <= 0.0001 && $freeQty <= 0.0001) {
            if (in_array($reasonType, ['vendor_extra', 'extra_ordered'], true)) {
                $payableQty = $overQty;
                $payableReasonType = $reasonType;
            } elseif ($reasonType === 'replacement_free') {
                $freeQty = $overQty;
            }
        }

        if ($payableQty > $overQty) {
            $payableQty = $overQty;
        }
        if ($freeQty > $overQty) {
            $freeQty = $overQty;
        }

        $sum = $payableQty + $freeQty;
        if ($sum <= 0.0001) {
            return ['valid' => false, 'error' => 'Each over-received line must define payable/free split quantities.'];
        }

        // Allow tiny rounding variance, normalize to exact over qty.
        $diff = $overQty - $sum;
        if (abs($diff) <= 0.02) {
            if ($payableQty > 0.0001) {
                $payableQty = max(0.0, round($payableQty + $diff, 4));
            } else {
                $freeQty = max(0.0, round($freeQty + $diff, 4));
            }
            $sum = $payableQty + $freeQty;
        }

        if (abs($overQty - $sum) > 0.02) {
            return [
                'valid' => false,
                'error' => 'Over-receipt split mismatch: payable + free must match extra received quantity.',
            ];
        }

        if ($payableQty > 0.0001 && !in_array($payableReasonType, ['vendor_extra', 'extra_ordered'], true)) {
            if (in_array($reasonType, ['vendor_extra', 'extra_ordered'], true)) {
                $payableReasonType = $reasonType;
            } else {
                return ['valid' => false, 'error' => 'Payable extra quantity requires a payable reason type.'];
            }
        }

        if ($payableQty <= 0.0001) {
            $payableReasonType = '';
        }

        $resolvedReasonType = 'replacement_free';
        if ($payableQty > 0.0001 && $freeQty > 0.0001) {
            $resolvedReasonType = 'mixed';
        } elseif ($payableQty > 0.0001) {
            $resolvedReasonType = $payableReasonType;
        }

        return [
            'valid' => true,
            'resolved_reason_type' => $resolvedReasonType,
            'payable_qty' => round($payableQty, 4),
            'free_qty' => round($freeQty, 4),
            'payable_reason_type' => $payableReasonType,
        ];
    }

    private function ensureGrnPublicIds($db): void
    {
        try {
            if (!$db->fieldExists('public_id', 'purchase_grns')) {
                $db->query('ALTER TABLE purchase_grns ADD COLUMN public_id CHAR(36) NULL AFTER id');
                $db->query('ALTER TABLE purchase_grns ADD UNIQUE KEY uq_purchase_grns_public_id (public_id)');
            }
            $rows = $db->table('purchase_grns')->select('id')->where('public_id IS NULL', null, false)->limit(500)->get()->getResultArray();
            foreach ($rows as $r) {
                $id = (int)($r['id'] ?? 0);
                if ($id <= 0) continue;
                $db->table('purchase_grns')->where('id', $id)->update(['public_id' => PublicIdTrait::uuid4()]);
            }
        } catch (\Throwable $_) {
            // Keep GRN flow resilient if schema change/backfill cannot run.
        }
    }

    // Return confirmed POs with pending quantity (quantity > qty_received)
    public function eligible_pos()
    {
        if (strtolower($this->request->getMethod()) !== 'get') { $this->response->setStatusCode(405)->setJSON(['success' => false, 'error' => 'Method not allowed']); $this->response->send(); exit; }
        $db = Database::connect();

        $vendorId = $this->request->getGet('vendor_id');
        $vendorFilter = '';
        if ($vendorId && is_numeric($vendorId)) {
            $vendorId = (int)$vendorId;
            $vendorFilter = " AND po.vendor_id = " . $db->escape($vendorId);
        }

        // Query confirmed POs and compute pending qty by summing lines
        $sql = "SELECT po.id, po.vendor_id, po.created_at,
              SUM(pol.qty) AS total_qty,
              SUM(pol.qty_received) AS total_received,
              (SUM(pol.qty) - SUM(pol.qty_received)) AS pending_qty
          FROM purchase_orders po
          JOIN purchase_order_lines pol ON pol.po_id = po.id
          WHERE po.status = 'confirmed'" . $vendorFilter . "
          GROUP BY po.id
          HAVING pending_qty > 0
          ORDER BY po.created_at DESC";

        $query = $db->query($sql);
        $rows = $query->getResultArray();
        $this->response->setJSON(['success' => true, 'data' => $rows]);
        $this->response->send();
        exit;
    }

    // Return pending lines for a PO (items not yet fully received) - for continuation receipts
    public function pending_lines()
    {
        if (strtolower($this->request->getMethod()) !== 'get') {
            return $this->response->setStatusCode(405)->setJSON(['error' => 'Method not allowed']);
        }

        $poId = $this->request->getGet('po_id');
        if (!$poId || !is_numeric($poId)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'PO ID is required']);
        }

        $poId = (int)$poId;
        $db = Database::connect();

        try {
            $poLineFields = array_flip($db->getFieldNames('purchase_order_lines'));
            $hasVariantId = isset($poLineFields['variant_id']);
            $hasQtyReceived = isset($poLineFields['qty_received']);
            $hasReceiveStatus = isset($poLineFields['receive_status']);
            $hasUnitPrice = isset($poLineFields['unit_price']);
            $hasUnitCost = isset($poLineFields['unit_cost']);

            $variantSelect = $hasVariantId ? 'pol.variant_id' : 'NULL as variant_id';
            $receivedExpr = $hasQtyReceived ? 'COALESCE(pol.qty_received, 0)' : '0';
            $unitPriceExpr = $hasUnitPrice
                ? 'COALESCE(pol.unit_price, 0)'
                : ($hasUnitCost ? 'COALESCE(pol.unit_cost, 0)' : '0');
            $receiveStatusSelect = $hasReceiveStatus
                ? "COALESCE(pol.receive_status, 'pending') as receive_status"
                : "'pending' as receive_status";
            $receiveStatusWhere = $hasReceiveStatus
                ? "AND (pol.receive_status IS NULL OR pol.receive_status != 'fully_received')"
                : '';

            // Get PO header
            $po = $db->table('purchase_orders')
                ->select('id, po_number, vendor_id, status')
                ->where('id', $poId)
                ->get()
                ->getRowArray();

            if (!$po) {
                return $this->response->setStatusCode(404)->setJSON(['error' => 'PO not found']);
            }

            // Get PO lines with pending quantities
            // Only include lines where receive_status != 'fully_received' (or receive_status is NULL for backward compatibility)
            $sql = "SELECT 
                        pol.id as po_line_id,
                        pol.product_id,
                        {$variantSelect},
                        pol.description,
                        pol.qty as ordered_qty,
                        {$receivedExpr} as already_received_qty,
                        (pol.qty - {$receivedExpr}) as pending_qty,
                        {$unitPriceExpr} as unit_price,
                        {$receiveStatusSelect},
                        p.code as product_code,
                        p.name as product_name
                    FROM purchase_order_lines pol
                    LEFT JOIN products p ON p.id = pol.product_id
                    WHERE pol.po_id = ?
                    {$receiveStatusWhere}
                    AND (pol.qty - {$receivedExpr}) > 0
                    ORDER BY pol.id ASC";

            $lines = $db->query($sql, [$poId])->getResultArray();

            return $this->response->setJSON([
                'success' => true,
                'po' => $po,
                'lines' => $lines,
                'total_pending_items' => count($lines),
                'total_pending_qty' => array_sum(array_column($lines, 'pending_qty'))
            ]);

        } catch (\Throwable $e) {
            log_message('error', 'Failed to fetch pending lines for PO ' . $poId . ': ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'error' => 'Failed to fetch pending lines',
                'message' => $e->getMessage()
            ]);
        }
    }

    // Return the next GRN number for display (read-only helper; does not reserve the number)
    public function next_number()
    {
        $method = strtolower($this->request->getMethod());
        if ($method === 'options') { return $this->response->setStatusCode(204); }
        if ($method !== 'get') { return $this->response->setStatusCode(405)->setJSON(['error'=>'Method not allowed','method'=>$method]); }
        $default = 'GRN-' . str_pad('1', 6, '0', STR_PAD_LEFT);
        try {
            $db = Database::connect();
            $next = $this->nextGrnNumber($db) ?: $default;
            return $this->response->setJSON(['success' => true, 'grn_number' => $next]);
        } catch (\Throwable $e) {
            log_message('error', 'next_number endpoint failed: '.$e->getMessage());
            return $this->response->setJSON(['success' => true, 'grn_number' => $default, 'warning' => 'fallback']);
        }
    }

    // List active stock locations (for selection or hierarchy building)
    public function locations()
    {
        $method = strtolower($this->request->getMethod());
        if ($method === 'options') { return $this->response->setStatusCode(204); }
        if ($method !== 'get') { return $this->response->setStatusCode(405)->setJSON(['error'=>'Method not allowed','method'=>$method]); }
        $db = Database::connect();
        $whId = (int)($this->request->getGet('warehouse_id') ?? 0);
        $builder = $db->table('warehouse_locations')->select('id,name,parent_id,warehouse_id,is_active')->where('is_active', 1);
        if ($whId > 0) $builder->where('warehouse_id', $whId);
        $rows = $builder->orderBy('parent_id', 'ASC')->orderBy('name', 'ASC')->get()->getResultArray();
        $rows = $this->attachLocationPaths($rows);
        return $this->response->setJSON(['success' => true, 'data' => $rows]);
    }

    // Create a stock location (used by GRN modal)
    public function create_location()
    {
        $method = strtolower($this->request->getMethod());
        if ($method === 'options') { return $this->response->setStatusCode(204); }
        if ($method !== 'post') { return $this->response->setStatusCode(405)->setJSON(['error'=>'Method not allowed','method'=>$method]); }
        $data = $this->request->getJSON(true) ?: $this->request->getPost();
    $name = trim($data['name'] ?? ($data['location_name'] ?? ''));
        if ($name === '') return $this->response->setStatusCode(400)->setJSON(['error' => 'Name required']);
        $warehouseId = isset($data['warehouse_id']) ? (int)$data['warehouse_id'] : 0;
        if ($warehouseId <= 0) return $this->response->setStatusCode(400)->setJSON(['error' => 'Warehouse required']);
        $parentId = isset($data['parent_id']) && $data['parent_id'] !== '' ? (int)$data['parent_id'] : null;
        $db = Database::connect();
        $id = $this->createLocation($db, $warehouseId, $name, $parentId);
        $row = $db->table('warehouse_locations')->where('id', $id)->get()->getRowArray();
        $enrichedRows = $this->attachLocationPaths($row ? [$row] : []);
        $row = $enrichedRows[0] ?? null;
        return $this->response->setJSON(['success' => true, 'data' => $row, 'location_id' => $id]);
    }

    // Return PO lines with ordered/received/pending quantities for a given PO
    public function po_lines($poId = null)
    {
        $method = strtolower($this->request->getMethod());
        if ($method === 'options') { return $this->response->setStatusCode(204); }
        if ($method !== 'get') { return $this->response->setStatusCode(405)->setJSON(['error'=>'Method not allowed','method'=>$method]); }
        $poId = (int)$poId;
        if ($poId <= 0) return $this->response->setStatusCode(400)->setJSON(['error' => 'PO id required']);
        $db = Database::connect();
        $poLineModel = new PurchaseOrderLineModel();
        $lines = $poLineModel->where('po_id', $poId)->findAll();
        // attach product info for UI (product name + type + code + image)
        $productIds = array_values(array_filter(array_map(function ($ln) {
            return isset($ln['product_id']) ? (int)$ln['product_id'] : 0;
        }, $lines)));
        $productInfo = [];
        if (!empty($productIds)) {
            $pRows = $db->table('products')
                ->select('id, name, product_type, code, sku, images')
                ->whereIn('id', $productIds)
                ->get()
                ->getResultArray();
            foreach ($pRows as $pr) {
                $productInfo[(int)$pr['id']] = $pr;
            }
        }
        
        // Get variant IDs from lines (if variant column exists)
        $variantIds = [];
        $variantInfo = [];
        try {
            $variantIds = array_values(array_filter(array_map(function ($ln) {
                return isset($ln['product_variant_id']) ? (int)$ln['product_variant_id'] : (isset($ln['variant_id']) ? (int)$ln['variant_id'] : 0);
            }, $lines)));
            if (!empty($variantIds)) {
                $vRows = $db->table('product_variants')
                    ->select('id, product_id, art_number, name, image')
                    ->whereIn('id', $variantIds)
                    ->get()
                    ->getResultArray();
                foreach ($vRows as $vr) {
                    $variantInfo[(int)$vr['id']] = $vr;
                }
            }
        } catch (\Exception $e) {
            // Variant column may not exist in purchase_order_lines, skip variant enrichment
            log_message('debug', 'Could not fetch variant info for PO lines: ' . $e->getMessage());
        }
        
        foreach ($lines as &$ln) {
            $ordered = isset($ln['qty']) ? (float)$ln['qty'] : 0;
            $received = isset($ln['qty_received']) ? (float)$ln['qty_received'] : 0;
            $ln['ordered_qty'] = $ordered;
            $ln['received_qty'] = $received;
            $ln['pending_qty'] = $ordered - $received;
            
            $pid = isset($ln['product_id']) ? (int)$ln['product_id'] : 0;
            $vid = isset($ln['product_variant_id']) ? (int)$ln['product_variant_id'] : (isset($ln['variant_id']) ? (int)$ln['variant_id'] : 0);
            
            // Attach product info
            if ($pid && isset($productInfo[$pid])) {
                $prod = $productInfo[$pid];
                $ln['product_name'] = $prod['name'] ?? $ln['product_name'] ?? null;
                $ln['product_type'] = $prod['product_type'] ?? null;
                $ln['product_code'] = $prod['code'] ?? null;
                $ln['product_sku'] = $prod['sku'] ?? null;
                $ln['product_images'] = $prod['images'] ?? null;
            }
            
            // Attach variant info (overrides product code/image if variant exists)
            if (!$vid) {
                $inferredVariantId = $this->inferVariantIdForPoLine($db, $ln);
                if ($inferredVariantId) {
                    $vid = (int)$inferredVariantId;
                    $ln['variant_id'] = $vid;
                    $ln['product_variant_id'] = $vid;
                    if (!empty($ln['id'])) {
                        $this->persistPoLineVariantId($db, (int)$ln['id'], $vid);
                    }
                    if (!isset($variantInfo[$vid])) {
                        $variantRows = $this->getGrnVariantsForProduct($db, $pid);
                        foreach ($variantRows as $variantRow) {
                            if ((int)($variantRow['id'] ?? 0) === $vid) {
                                $variantInfo[$vid] = $variantRow;
                                break;
                            }
                        }
                    }
                }
            }

            if ($vid && isset($variantInfo[$vid])) {
                $variant = $variantInfo[$vid];
                $ln['variant_id'] = $vid;  // Ensure variant_id is set in response
                $ln['product_variant_id'] = $vid;  // Also set product_variant_id for compatibility
                $ln['variant_art_number'] = $variant['art_number'] ?? null;
                $ln['variant_name'] = $variant['name'] ?? null;
                $ln['variant_image'] = $variant['image'] ?? null;
            }
        }
        return $this->response->setJSON(['success' => true, 'data' => $lines]);
    }

    // Return variants for a product (used by GRN UI)
    public function product_variants($productId = null)
    {
        if (strtolower($this->request->getMethod()) !== 'get') { return $this->response->setStatusCode(405)->setJSON(['error'=>'Method not allowed']); }
        $productId = (int)$productId;
        if ($productId <= 0) return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'Product id required']);
        $db = Database::connect();
        try {
            $rows = $db->table('product_variants')
                ->select('id, product_id, art_number, name')
                ->where('product_id', $productId)
                ->orderBy('art_number', 'ASC')
                ->get()
                ->getResultArray();
            return $this->response->setJSON(['success' => true, 'data' => $rows]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => 'Failed to load variants', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Record GRN line issue actions (scrap / return-to-vendor / send-for-repair / repaired-back).
     */
    public function issue($grnId = null, $lineId = null)
    {
        if (strtolower($this->request->getMethod()) !== 'post') {
            return $this->response->setStatusCode(405)->setJSON(['success' => false, 'error' => 'Method not allowed']);
        }

        $grnId = (int)$grnId;
        $lineId = (int)$lineId;
        if ($grnId <= 0 || $lineId <= 0) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'Invalid GRN/line id']);
        }

        $payload = $this->request->getJSON(true) ?: $this->request->getPost();
        $actionType = strtolower(trim((string)($payload['action_type'] ?? '')));
        $qty = (float)($payload['qty'] ?? 0);
        $actionDate = trim((string)($payload['action_date'] ?? date('Y-m-d')));
        $reason = trim((string)($payload['reason'] ?? ''));

        $allowed = ['scrap', 'return_to_vendor', 'send_for_repair', 'receive_repaired_back'];
        if (!in_array($actionType, $allowed, true)) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'Invalid action type']);
        }
        if ($qty <= 0) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'Quantity must be > 0']);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $actionDate)) {
            $actionDate = date('Y-m-d');
        }

        $db = Database::connect();
        try {
            $grn = $db->table('purchase_grns')->where('id', $grnId)->get()->getRowArray();
            if (!$grn) {
                throw new \RuntimeException('GRN not found');
            }

            $line = $db->table('purchase_grn_lines')->where('id', $lineId)->where('grn_id', $grnId)->get()->getRowArray();
            if (!$line) {
                throw new \RuntimeException('GRN line not found');
            }

            $movement = $db->table('stock_movements')
                ->select('warehouse_id, location_id')
                ->where('reference_type', 'grn')
                ->where('reference_id', $grnId)
                ->orderBy('id', 'ASC')
                ->get(1)
                ->getRowArray();
            $warehouseId = (int)($movement['warehouse_id'] ?? 0);
            $locationId  = (int)($movement['location_id'] ?? 0);
            // Fallback: use warehouse/location stored directly on the GRN record
            if ($warehouseId <= 0) {
                $warehouseId = (int)($grn['warehouse_id'] ?? 0);
            }
            if ($locationId <= 0) {
                $locationId = (int)($grn['location_id'] ?? 0);
            }
            // Second fallback: look up the most recent stock movement for this product from this GRN
            if ($warehouseId <= 0 || $locationId <= 0) {
                $smFallback = $db->table('stock_movements')
                    ->select('warehouse_id, location_id')
                    ->where('reference_id', $grnId)
                    ->where('warehouse_id >', 0)
                    ->where('location_id >', 0)
                    ->orderBy('id', 'ASC')
                    ->get(1)
                    ->getRowArray();
                if (!empty($smFallback)) {
                    $warehouseId = $warehouseId > 0 ? $warehouseId : (int)$smFallback['warehouse_id'];
                    $locationId  = $locationId  > 0 ? $locationId  : (int)$smFallback['location_id'];
                }
            }
            if ($warehouseId <= 0 || $locationId <= 0) {
                throw new \RuntimeException('Warehouse/location not found for this GRN. Please ensure the GRN has a warehouse and location assigned.');
            }

            $this->ensureIssueLogTable($db);

            $actionRows = $db->table('purchase_grn_line_issues')
                ->where('grn_line_id', $lineId)
                ->get()
                ->getResultArray();
            $scrap = 0.0; $rtv = 0.0; $repairOut = 0.0; $repairBack = 0.0;
            foreach ($actionRows as $ar) {
                $q = (float)($ar['qty'] ?? 0);
                $t = (string)($ar['action_type'] ?? '');
                if ($t === 'scrap') $scrap += $q;
                if ($t === 'return_to_vendor') $rtv += $q;
                if ($t === 'send_for_repair') $repairOut += $q;
                if ($t === 'receive_repaired_back') $repairBack += $q;
            }

            $receivedQty = (float)($line['qty_received'] ?? 0);
            $availableForOut = $receivedQty - $scrap - $rtv - $repairOut + $repairBack;
            $outstandingRepair = $repairOut - $repairBack;

            if (in_array($actionType, ['scrap', 'return_to_vendor', 'send_for_repair'], true) && $qty - $availableForOut > 0.0001) {
                throw new \RuntimeException('Action qty exceeds available qty from GRN line');
            }
            if ($actionType === 'receive_repaired_back' && $qty - $outstandingRepair > 0.0001) {
                throw new \RuntimeException('Receive-back qty exceeds outstanding repair qty');
            }

            $inventory = new InventoryService();
            $productId = (int)($line['product_id'] ?? 0);
            $variantId = !empty($line['variant_id']) ? (int)$line['variant_id'] : null;
            $userId = (int)(session()->get('user_id') ?: 0);

            if (in_array($actionType, ['scrap', 'return_to_vendor', 'send_for_repair'], true)) {
                $inventory->issueFromStock(
                    $productId,
                    $warehouseId,
                    $locationId,
                    $qty,
                    $lineId,
                    $userId,
                    $actionType,
                    'grn_issue',
                    $variantId
                );
            } else {
                // repaired goods returned back into stock
                $inventory->receiveFromGrn(
                    $productId,
                    $warehouseId,
                    $locationId,
                    $qty,
                    $grnId,
                    $userId,
                    $variantId
                );
            }

            $db->table('purchase_grn_line_issues')->insert([
                'grn_id' => $grnId,
                'grn_line_id' => $lineId,
                'po_line_id' => (int)($line['po_line_id'] ?? 0),
                'product_id' => $productId,
                'variant_id' => $variantId,
                'action_type' => $actionType,
                'qty' => $qty,
                'action_date' => $actionDate,
                'reason' => $reason ?: null,
                'created_by' => $userId ?: null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return $this->response->setJSON(['success' => true, 'message' => 'Action recorded']);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function ensureIssueLogTable($db): void
    {
        try {
            if ($db->tableExists('purchase_grn_line_issues')) {
                return;
            }
            $db->query(
                'CREATE TABLE purchase_grn_line_issues ('
                . 'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,'
                . 'grn_id INT NOT NULL,'
                . 'grn_line_id INT NOT NULL,'
                . 'po_line_id INT NULL,'
                . 'product_id INT NULL,'
                . 'variant_id INT NULL,'
                . 'action_type VARCHAR(50) NOT NULL,'
                . 'qty DECIMAL(18,4) NOT NULL DEFAULT 0,'
                . 'action_date DATE NULL,'
                . 'reason TEXT NULL,'
                . 'created_by INT NULL,'
                . 'created_at DATETIME NULL,'
                . 'INDEX idx_grn_line (grn_line_id),'
                . 'INDEX idx_grn (grn_id),'
                . 'INDEX idx_action_type (action_type)'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
        } catch (\Throwable $_) {
            // no-op: caller handles absence if create fails due permissions
        }
    }

    private function createLocation($db, int $warehouseId, string $name, $parentId = null)
    {
        // ensure parent belongs to same warehouse
        if ($parentId) {
            $parent = $db->table('warehouse_locations')->where('id', $parentId)->get()->getRowArray();
            if (!$parent || (int)$parent['warehouse_id'] !== $warehouseId) throw new \RuntimeException('Parent must be in same warehouse');
        }
        $data = [
            'warehouse_id' => $warehouseId,
            'name' => $name,
            'parent_id' => $parentId ?: null,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        $db->table('warehouse_locations')->insert($data);
        $id = $db->insertID();
        if (!$id) throw new \RuntimeException('Failed to create location');
        return (int)$id;
    }

    private function attachLocationPaths(array $rows): array
    {
        if (empty($rows)) {
            return $rows;
        }

        $db = Database::connect();
        $allRows = $db->table('warehouse_locations')
            ->select('id, name, parent_id, warehouse_id, is_active')
            ->where('is_active', 1)
            ->orderBy('parent_id', 'ASC')
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        $locationMap = [];
        foreach ($allRows as $location) {
            $locationMap[(int)$location['id']] = $location;
        }

        $buildPath = function (?int $locationId) use (&$locationMap): string {
            if (!$locationId || !isset($locationMap[$locationId])) {
                return '';
            }

            $parts = [];
            $seen = [];
            while ($locationId && isset($locationMap[$locationId]) && !in_array($locationId, $seen, true)) {
                $seen[] = $locationId;
                $parts[] = (string)($locationMap[$locationId]['name'] ?? '');
                $locationId = !empty($locationMap[$locationId]['parent_id']) ? (int)$locationMap[$locationId]['parent_id'] : null;
            }

            return implode(' \\ ', array_reverse(array_filter($parts, static fn ($part) => $part !== '')));
        };

        foreach ($rows as &$row) {
            $row['location_path'] = $buildPath(isset($row['id']) ? (int)$row['id'] : null);
            $row['display_name'] = $row['location_path'] ?: (string)($row['name'] ?? '');
        }
        unset($row);

        usort($rows, static function (array $left, array $right): int {
            return strcmp((string)($left['display_name'] ?? ''), (string)($right['display_name'] ?? ''));
        });

        return $rows;
    }

    private function nextGrnNumber($db): string
    {
        $prefix = 'GRN-';
        $default = $prefix . str_pad('1', 6, '0', STR_PAD_LEFT);
        try {
            $row = $db->table('purchase_grns')
                ->select('grn_number')
                ->orderBy('id', 'DESC')
                ->limit(1)
                ->get()
                ->getRowArray();

            if (!$row || empty($row['grn_number'])) {
                return $default;
            }

            $grn = trim((string)$row['grn_number']);
            $numPart = null;
            if (preg_match('/(\d+)$/', $grn, $m)) {
                $numPart = (int)$m[1];
            }
            $next = ($numPart !== null && $numPart > 0) ? $numPart + 1 : 1;
            return $prefix . str_pad((string)$next, 6, '0', STR_PAD_LEFT);
        } catch (\Throwable $e) {
            log_message('error', 'nextGrnNumber failed: ' . $e->getMessage());
            return $default;
        }
    }

    /**
     * Create a draft adjustment vendor bill for over-received quantities when prior bill exists.
     */
    private function createOverReceiptAdjustmentBill($db, array $po, int $poId, int $grnId, string $receivedAt, array $extraLines): ?int
    {
        if (empty($extraLines)) {
            return null;
        }

        if (!($db->tableExists('vendor_bills') && $db->tableExists('vendor_bill_lines'))) {
            return null;
        }

        // Only create adjustment bill if a prior posted/confirmed liability already exists.
        $hasPriorBill = false;
        try {
            $prior = $db->query(
                "SELECT id FROM vendor_bills WHERE po_id = ? AND LOWER(COALESCE(status,'')) IN ('confirmed','partially_paid','paid') ORDER BY id DESC LIMIT 1",
                [$poId]
            )->getRowArray();
            $hasPriorBill = !empty($prior);
        } catch (\Throwable $_) {
            $hasPriorBill = false;
        }

        if (!$hasPriorBill) {
            return null;
        }

        $memo = 'Over-receipt adjustment from GRN #' . $grnId;
        $reasonLabels = [
            'vendor_extra' => 'Vendor sent extra quantity',
            'extra_ordered' => 'Extra ordered after PO',
        ];
        $reasonSeen = [];
        foreach ($extraLines as $line) {
            $rt = strtolower(trim((string)($line['reason_type'] ?? '')));
            if ($rt !== '') {
                $reasonSeen[$rt] = true;
            }
        }
        $reasonParts = [];
        foreach (array_keys($reasonSeen) as $rt) {
            $reasonParts[] = $reasonLabels[$rt] ?? $rt;
        }
        $notes = (!empty($reasonParts) ? implode(' + ', $reasonParts) : 'Over-receipt adjustment') . ' on ' . substr($receivedAt, 0, 10);

        $lineDetails = [];
        foreach ($extraLines as $line) {
            $details = trim((string)($line['reason_details'] ?? ''));
            if ($details !== '') {
                $lineDetails[] = $details;
            }
        }
        $lineDetails = array_values(array_unique($lineDetails));
        if (!empty($lineDetails)) {
            $notes .= ' | Details: ' . implode(' | ', array_slice($lineDetails, 0, 3));
        }

        $currency = strtoupper(trim((string)($po['currency'] ?? 'PKR')));
        if ($currency === '') {
            $currency = 'PKR';
        }

        $total = 0.0;
        $processingRecordId = (int) ($po['processing_record_id'] ?? 0);
        $proposedQty = 0.0;
        foreach ($extraLines as $line) {
            $proposedQty += max(0.0, (float)($line['extra_qty'] ?? 0));
            $total += max(0.0, (float)($line['extra_qty'] ?? 0) * (float)($line['unit_price'] ?? 0));
        }
        $total = round($total, 2);
        if ($total <= 0.0) {
            return null;
        }

        if ($processingRecordId > 0) {
            $processingBillingService = new ProcessingBillingValidationService();
            $validation = $processingBillingService->validateBillQuantity($processingRecordId, $proposedQty);
            if (empty($validation['valid'])) {
                $summary = $validation['summary'] ?? [];
                throw new \RuntimeException(
                    'Billing quantity exceeds remaining processed quantity. '
                    . 'Processed: ' . number_format((float) ($summary['total_processed_qty'] ?? 0), 4)
                    . ', Already billed: ' . number_format((float) ($summary['total_billed_qty'] ?? 0), 4)
                    . ', Remaining: ' . number_format((float) ($summary['remaining_qty'] ?? 0), 4)
                );
            }
        }

        $billCols = [];
        try {
            $billCols = $db->getFieldNames('vendor_bills');
        } catch (\Throwable $_) {
            $billCols = [];
        }

        $billData = [
            'vendor_id' => $po['vendor_id'] ?? null,
            'po_id' => $poId,
            'bill_date' => substr($receivedAt, 0, 10),
            'total_amount' => $total,
            'balance' => $total,
            'status' => 'draft',
            'based_on' => 'po_over_receipt',
            'currency_code' => $currency,
            'memo' => $memo,
            'notes' => $notes,
            'created_by' => session()->get('user_id') ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        if (!empty($billCols)) {
            $billData = array_intersect_key($billData, array_flip($billCols));
        }

        // keep tracking text when memo is missing but notes exists
        if (in_array('notes', $billCols, true) && !isset($billData['notes'])) {
            $billData['notes'] = $notes;
        }

        $billInserted = $db->table('vendor_bills')->insert($billData);
        if (! $billInserted) {
            throw new \RuntimeException('Failed to create over-receipt adjustment bill');
        }
        $billId = (int) $db->insertID();
        if ($billId <= 0) {
            throw new \RuntimeException('Failed to resolve over-receipt adjustment bill id');
        }

        foreach ($extraLines as $line) {
            $qty = max(0.0, (float)($line['extra_qty'] ?? 0));
            $price = (float)($line['unit_price'] ?? 0);
            if ($qty <= 0.0) {
                continue;
            }

            $billLineData = [
                'vendor_bill_id' => $billId,
                'po_line_id' => (int)($line['po_line_id'] ?? 0),
                'processing_record_id' => $processingRecordId > 0 ? $processingRecordId : null,
                'product_id' => $line['product_id'] ?? null,
                'variant_id' => $line['variant_id'] ?? null,
                'qty' => $qty,
                'unit_price' => $price,
                'line_total' => round($qty * $price, 2),
                'created_at' => date('Y-m-d H:i:s'),
            ];

            $lineCols = [];
            try {
                $lineCols = $db->getFieldNames('vendor_bill_lines');
            } catch (\Throwable $_) {
                $lineCols = [];
            }
            if (!empty($lineCols)) {
                $billLineData = array_intersect_key($billLineData, array_flip($lineCols));
            }

            $ok = $db->table('vendor_bill_lines')->insert($billLineData);
            if (! $ok) {
                throw new \RuntimeException('Failed to create over-receipt bill line');
            }
        }

        return (int)$billId;

    }

        public function printView($grnId = null)
        {
            $db = \Config\Database::connect();
            $grnModel = new \App\Models\PurchaseGrnModel();
            $grn = $grnModel->findByPublicIdOrId($grnId);
            if (!$grn) {
                return redirect()->back()->with('error', 'GRN not found');
            }
            $grnId = (int)$grn['id'];

            $vendor = [];
            try {
                $vendor = $db->table('vendors')->where('id', (int)($grn['vendor_id'] ?? 0))->get()->getRowArray() ?: [];
            } catch (\Throwable $_) {}

            $lineModel = new \App\Models\PurchaseGrnLineModel();
            $lines = $lineModel->where('grn_id', $grnId)->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->findAll();

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

                if (!empty($variantMap)) {
                    $missingProductIds = [];
                    foreach ($variantMap as $variant) {
                        $variantProductId = isset($variant['product_id']) ? (int) $variant['product_id'] : 0;
                        if ($variantProductId > 0 && !isset($prodMap[$variantProductId])) {
                            $missingProductIds[] = $variantProductId;
                        }
                    }
                    $missingProductIds = array_values(array_unique($missingProductIds));
                    if (!empty($missingProductIds)) {
                        try {
                            $productModel = $productModel ?? new \App\Models\ProductModel();
                            $products = $productModel->whereIn('id', $missingProductIds)->findAll();
                            foreach ($products as $product) {
                                $prodMap[(int) $product['id']] = $product;
                            }
                        } catch (\Throwable $_) {}
                    }
                }

                foreach ($lines as &$line) {
                    $productId = isset($line['product_id']) ? (int) $line['product_id'] : null;
                    $variantId = isset($line['product_variant_id']) ? (int) $line['product_variant_id'] : null;

                    if ((!$productId || $productId <= 0) && $variantId && isset($variantMap[$variantId])) {
                        $productId = isset($variantMap[$variantId]['product_id']) ? (int) $variantMap[$variantId]['product_id'] : null;
                        if ($productId) {
                            $line['product_id'] = $productId;
                        }
                    }

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
                $price = (float) ($line['unit_cost'] ?? ($line['unit_price'] ?? 0));
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

            $currency = strtoupper(trim((string) ($grn['currency'] ?? ($company['base_currency'] ?? 'USD'))));
            $symbols = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'PKR' => '₨', 'INR' => '₹'];
            $sym = $symbols[$currency] ?? $currency;
            $fmt = fn($value) => $sym . ' ' . number_format((float) $value, 2);

            $subtotal = (float) ($grn['subtotal'] ?? 0);
            $total = (float) ($grn['total'] ?? 0);
            $grnNumber = esc($grn['grn_number'] ?? ('GRN-' . $grnId));
            $grnDate = '';
            $rawDate = trim((string) ($grn['grn_date'] ?? ($grn['created_at'] ?? '')));
            if ($rawDate && strpos($rawDate, '0000') === false) {
                $ts = strtotime($rawDate);
                if ($ts) {
                    $grnDate = date('d-m-Y', $ts);
                }
            }
            $vendorName = esc($vendor['name'] ?? 'Vendor');
            $companyName = esc($company['name'] ?? '');

            ob_start();
            ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="utf-8">
    <title>GRN <?= $grnNumber ?></title>
    <style>
      *{box-sizing:border-box;margin:0;padding:0}
      body{font-family:Arial,sans-serif;font-size:12px;color:#1e293b;background:#f8fafc;padding:24px}
      .grn-doc{max-width:1100px;margin:0 auto}
      .grn-hero{background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);border-radius:.75rem .75rem 0 0;padding:1.6rem 2rem 1.4rem;color:#fff;position:relative;overflow:hidden}
      .grn-hero::after{content:'GRN';position:absolute;right:-1rem;top:50%;transform:translateY(-50%);font-size:7rem;font-weight:900;opacity:.04;pointer-events:none;user-select:none;line-height:1}
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
        <div class="grn-doc-type">Goods Receipt Note</div>
        <div class="grn-hero-num"><?= $grnNumber ?></div>
        <div class="grn-hero-sub"><?= $companyName ?></div>
        <div class="grn-hero-actions no-print">
          <button type="button" class="grn-hero-btn" onclick="window.print()">Print</button>
          <button type="button" class="grn-hero-btn" onclick="window.close()">Close</button>
        </div>
      </div>

      <div class="grn-facts">
        <div class="grn-fact"><div class="grn-fact-lbl">Vendor</div><div class="grn-fact-val"><?= $vendorName ?></div></div>
        <div class="grn-fact"><div class="grn-fact-lbl">GRN Date</div><div class="grn-fact-val"><?= esc($grnDate ?: '-') ?></div></div>
        <div class="grn-fact"><div class="grn-fact-lbl">Currency</div><div class="grn-fact-val"><?= esc($currency) ?></div></div>
        <div class="grn-fact"><div class="grn-fact-lbl">Lines</div><div class="grn-fact-val"><?= number_format(count($printLines), 0) ?></div></div>
      </div>

      <div class="grn-sec">
        <div class="grn-sec-hd">GRN Lines<span class="grn-sec-badge"><?= number_format(count($printLines), 0) ?></span></div>
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
