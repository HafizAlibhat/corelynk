<?php

namespace App\Controllers;

use App\Models\PurchaseOrderModel;
use App\Models\PurchaseOrderLineModel;
use App\Libraries\DocumentLogger;
use App\Libraries\RoleDataAccess;
use Config\Database;
use App\Models\CompanySettingsModel;
use App\Services\SearchService;
use App\Services\ProcessingBillingValidationService;
use App\Libraries\InvoicePdfGenerator;

class NewPurchaseOrders extends BaseController
{
    /**
     * Validate that products with variants are not saved as template-only lines.
     *
     * @param array $lines
     * @return array{missing: array<int,array<string,mixed>>, invalid: array<int,array<string,mixed>>}
     */
    private function validateVariantSelections(array $lines): array
    {
        $issues = ['missing' => [], 'invalid' => []];
        if (empty($lines)) {
            return $issues;
        }

        $productIds = [];
        $variantIds = [];
        foreach ($lines as $ln) {
            if (!is_array($ln)) {
                continue;
            }
            $pid = isset($ln['product_id']) ? (int)$ln['product_id'] : 0;
            $vid = isset($ln['variant_id']) ? (int)$ln['variant_id'] : (isset($ln['product_variant_id']) ? (int)$ln['product_variant_id'] : 0);
            if ($pid > 0) {
                $productIds[] = $pid;
            }
            if ($vid > 0) {
                $variantIds[] = $vid;
            }
        }

        $productIds = array_values(array_unique($productIds));
        $variantIds = array_values(array_unique($variantIds));
        if (empty($productIds)) {
            return $issues;
        }

        $db = Database::connect();
        $variantRequiredMap = [];
        $variantOwners = [];
        $productLabels = [];

        try {
            if ($db->tableExists('products')) {
                $rows = $db->table('products')->select('id, name, code')->whereIn('id', $productIds)->get()->getResultArray();
                foreach ($rows as $row) {
                    $pid = (int)($row['id'] ?? 0);
                    if ($pid <= 0) {
                        continue;
                    }
                    $label = trim((string)($row['name'] ?? ''));
                    if ($label === '') {
                        $label = trim((string)($row['code'] ?? ''));
                    }
                    $productLabels[$pid] = $label !== '' ? $label : ('Product #' . $pid);
                }
            }

            if ($db->tableExists('product_variants')) {
                $rows = $db->table('product_variants')
                    ->select('product_id, COUNT(*) AS cnt')
                    ->whereIn('product_id', $productIds)
                    ->groupBy('product_id')
                    ->get()
                    ->getResultArray();
                foreach ($rows as $row) {
                    $pid = (int)($row['product_id'] ?? 0);
                    $cnt = (int)($row['cnt'] ?? 0);
                    if ($pid > 0 && $cnt > 0) {
                        $variantRequiredMap[$pid] = true;
                    }
                }

                if (!empty($variantIds)) {
                    $ownerRows = $db->table('product_variants')->select('id, product_id')->whereIn('id', $variantIds)->get()->getResultArray();
                    foreach ($ownerRows as $row) {
                        $vid = (int)($row['id'] ?? 0);
                        if ($vid > 0) {
                            $variantOwners[$vid] = (int)($row['product_id'] ?? 0);
                        }
                    }
                }
            }
        } catch (\Throwable $_) {
            return $issues;
        }

        foreach ($lines as $idx => $ln) {
            if (!is_array($ln)) {
                continue;
            }
            $pid = isset($ln['product_id']) ? (int)$ln['product_id'] : 0;
            $vid = isset($ln['variant_id']) ? (int)$ln['variant_id'] : (isset($ln['product_variant_id']) ? (int)$ln['product_variant_id'] : 0);
            if ($pid <= 0) {
                continue;
            }

            $label = (string)($productLabels[$pid] ?? ('Product #' . $pid));
            if (!empty($variantRequiredMap[$pid]) && $vid <= 0) {
                $issues['missing'][] = [
                    'line_no' => (int)$idx + 1,
                    'label' => $label,
                ];
                continue;
            }

            if ($vid > 0) {
                $ownerPid = isset($variantOwners[$vid]) ? (int)$variantOwners[$vid] : 0;
                if ($ownerPid <= 0 || $ownerPid !== $pid) {
                    $issues['invalid'][] = [
                        'line_no' => (int)$idx + 1,
                        'label' => $label,
                    ];
                }
            }
        }

        return $issues;
    }

    private function buildVariantValidationMessage(array $issues): string
    {
        $parts = [];
        if (!empty($issues['missing'])) {
            $labels = array_values(array_unique(array_map(static fn($x) => (string)($x['label'] ?? ''), $issues['missing'])));
            $parts[] = 'Variant selection is required for: ' . implode(', ', array_slice($labels, 0, 8)) . (count($labels) > 8 ? '...' : '');
        }
        if (!empty($issues['invalid'])) {
            $labels = array_values(array_unique(array_map(static fn($x) => (string)($x['label'] ?? ''), $issues['invalid'])));
            $parts[] = 'Some selected variants do not belong to their products: ' . implode(', ', array_slice($labels, 0, 8)) . (count($labels) > 8 ? '...' : '');
        }

        $base = 'Template product cannot be used when variants exist. Please select a specific variant (ART/code) for each variant-based product.';
        if (!empty($parts)) {
            $base .= ' ' . implode(' ', $parts);
        }
        return $base;
    }

    private function calculateOrderedPoTotal(array $poLines): float
    {
        $total = 0.0;
        foreach ($poLines as $line) {
            $qty = (float)($line['qty'] ?? 0);
            $unitPrice = (float)($line['unit_price'] ?? ($line['unit_cost'] ?? 0));
            if ($qty <= 0) {
                continue;
            }
            $total += $qty * $unitPrice;
        }
        return round($total, 2);
    }

    private function getBaseBilledQtyByPoLine($db, int $poId): array
    {
        $billedQtyByPoLine = [];
        try {
            if ($db->tableExists('vendor_bill_lines') && $db->tableExists('vendor_bills')) {
                $billedRows = $db->query(
                    'SELECT vbl.po_line_id, COALESCE(SUM(vbl.qty),0) AS billed_qty '
                    . 'FROM vendor_bill_lines vbl '
                    . 'INNER JOIN vendor_bills vb ON vb.id = vbl.vendor_bill_id '
                    . "WHERE vb.po_id = ? AND LOWER(COALESCE(vb.status,'')) <> 'cancelled' AND LOWER(COALESCE(vb.based_on,'')) <> 'po_over_receipt' "
                    . 'GROUP BY vbl.po_line_id',
                    [$poId]
                )->getResultArray();

                foreach ($billedRows as $br) {
                    $plId = (int)($br['po_line_id'] ?? 0);
                    if ($plId > 0) {
                        $billedQtyByPoLine[$plId] = (float)($br['billed_qty'] ?? 0);
                    }
                }
            }
        } catch (\Throwable $_) {
            $billedQtyByPoLine = [];
        }

        return $billedQtyByPoLine;
    }

    private function getBillableExtraReceiptData($db, int $poId): array
    {
        $result = [
            'total_qty' => 0.0,
            'lines' => [],
            'notes' => [],
        ];

        if (!($db->tableExists('purchase_grn_lines') && $db->tableExists('purchase_grns'))) {
            return $result;
        }

        try {
            $extraRows = $db->query(
                'SELECT gl.po_line_id, gl.product_id, gl.variant_id, gl.description, '
                . 'COALESCE(MAX(gl.unit_cost), 0) AS unit_cost, '
                . 'COALESCE(SUM(COALESCE(gl.over_received_qty,0)),0) AS extra_qty, '
                . "GROUP_CONCAT(DISTINCT NULLIF(TRIM(COALESCE(gl.over_receipt_reason_type, '')), '')) AS reason_types, "
                . "GROUP_CONCAT(DISTINCT NULLIF(TRIM(COALESCE(gl.over_receipt_reason_details, '')), '') SEPARATOR ' | ') AS reason_details "
                . 'FROM purchase_grn_lines gl '
                . 'INNER JOIN purchase_grns g ON g.id = gl.grn_id '
                . "WHERE g.po_id = ? AND gl.over_receipt_reason_type IN ('vendor_extra','extra_ordered') "
                . 'GROUP BY gl.po_line_id, gl.product_id, gl.variant_id, gl.description',
                [$poId]
            )->getResultArray();
        } catch (\Throwable $_) {
            return $result;
        }

        if (empty($extraRows)) {
            return $result;
        }

        $alreadyBilled = [];
        try {
            $billedRows = $db->query(
                'SELECT vbl.po_line_id, COALESCE(SUM(vbl.qty),0) AS billed_qty '
                . 'FROM vendor_bill_lines vbl '
                . 'INNER JOIN vendor_bills vb ON vb.id = vbl.vendor_bill_id '
                . "WHERE vb.po_id = ? AND LOWER(COALESCE(vb.status,'')) <> 'cancelled' AND LOWER(COALESCE(vb.based_on,'')) = 'po_over_receipt' "
                . 'GROUP BY vbl.po_line_id',
                [$poId]
            )->getResultArray();
            foreach ($billedRows as $row) {
                $poLineId = (int)($row['po_line_id'] ?? 0);
                if ($poLineId > 0) {
                    $alreadyBilled[$poLineId] = (float)($row['billed_qty'] ?? 0);
                }
            }
        } catch (\Throwable $_) {
            $alreadyBilled = [];
        }

        foreach ($extraRows as $row) {
            $poLineId = (int)($row['po_line_id'] ?? 0);
            $extraQty = (float)($row['extra_qty'] ?? 0);
            $remainingQty = max(0.0, round($extraQty - (float)($alreadyBilled[$poLineId] ?? 0), 4));
            if ($poLineId <= 0 || $remainingQty <= 0.0001) {
                continue;
            }

            $reasonDetails = trim((string)($row['reason_details'] ?? ''));
            if ($reasonDetails !== '') {
                $result['notes'][$reasonDetails] = $reasonDetails;
            }

            $result['lines'][] = [
                'po_line_id' => $poLineId,
                'product_id' => isset($row['product_id']) ? (int)$row['product_id'] : null,
                'variant_id' => isset($row['variant_id']) && (int)$row['variant_id'] > 0 ? (int)$row['variant_id'] : null,
                'description' => $row['description'] ?? null,
                'extra_qty' => $remainingQty,
                'unit_price' => (float)($row['unit_cost'] ?? 0),
                'reason_types' => (string)($row['reason_types'] ?? ''),
                'reason_details' => $reasonDetails,
            ];
            $result['total_qty'] += $remainingQty;
        }

        $result['total_qty'] = round($result['total_qty'], 4);
        $result['notes'] = array_values($result['notes']);
        return $result;
    }

    private function createPoOverReceiptBill($db, array $po, int $poId, array $extraLines): int
    {
        if (empty($extraLines)) {
            throw new \RuntimeException('No payable extra quantity is available to bill.');
        }

        $currency = strtoupper(trim((string)($po['currency'] ?? $po['currency_code'] ?? 'PKR')));
        if ($currency === '') {
            $currency = 'PKR';
        }

        $reasonLabels = [
            'vendor_extra' => 'Vendor sent extra quantity',
            'extra_ordered' => 'Extra ordered after PO',
        ];

        $reasonSeen = [];
        $detailSeen = [];
        $totalAmount = 0.0;
        foreach ($extraLines as $line) {
            $qty = max(0.0, (float)($line['extra_qty'] ?? 0));
            $price = (float)($line['unit_price'] ?? 0);
            $totalAmount += $qty * $price;

            $reasonTypes = array_filter(array_map('trim', explode(',', (string)($line['reason_types'] ?? ''))));
            foreach ($reasonTypes as $reasonType) {
                $reasonSeen[$reasonType] = $reasonLabels[$reasonType] ?? $reasonType;
            }

            $details = trim((string)($line['reason_details'] ?? ''));
            if ($details !== '') {
                $detailSeen[$details] = $details;
            }
        }

        $totalAmount = round($totalAmount, 2);
        if ($totalAmount <= 0.0) {
            throw new \RuntimeException('Extra received quantity has no billable value.');
        }

        $notes = !empty($reasonSeen) ? implode(' + ', array_values($reasonSeen)) : 'Over-receipt adjustment';
        $notes .= ' on ' . date('Y-m-d');
        if (!empty($detailSeen)) {
            $notes .= ' | Details: ' . implode(' | ', array_slice(array_values($detailSeen), 0, 3));
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
            'bill_date' => date('Y-m-d'),
            'total_amount' => $totalAmount,
            'balance' => $totalAmount,
            'status' => 'draft',
            'based_on' => 'po_over_receipt',
            'currency_code' => $currency,
            'memo' => 'Over-receipt adjustment for PO #' . ($po['po_number'] ?? $poId),
            'notes' => $notes,
            'created_by' => session()->get('user_id') ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        if (!empty($billCols)) {
            $billData = array_intersect_key($billData, array_flip($billCols));
        }

        $inserted = $db->table('vendor_bills')->insert($billData);
        if (!$inserted) {
            throw new \RuntimeException('Failed to create extra vendor bill.');
        }
        $billId = (int)$db->insertID();
        if ($billId <= 0) {
            throw new \RuntimeException('Failed to resolve extra vendor bill id.');
        }

        foreach ($extraLines as $line) {
            $qty = max(0.0, (float)($line['extra_qty'] ?? 0));
            if ($qty <= 0.0001) {
                continue;
            }
            $price = (float)($line['unit_price'] ?? 0);
            $lineTotal = round($qty * $price, 2);
            $db->table('vendor_bill_lines')->insert([
                'vendor_bill_id' => $billId,
                'po_line_id' => (int)($line['po_line_id'] ?? 0),
                'product_id' => $line['product_id'] ?? null,
                'variant_id' => $line['variant_id'] ?? null,
                'qty' => $qty,
                'unit_price' => $price,
                'line_total' => $lineTotal,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $billId;
    }

    private function getPurchaseDocumentPrefix(): string
    {
        $default = 'RI-PO-';
        try {
            $db = Database::connect();
            $row = $db->query('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1', ['purchase_rfq_prefix'])->getRowArray();
            $val = isset($row['setting_value']) ? trim((string) $row['setting_value']) : '';
            return $val !== '' ? $val : $default;
        } catch (\Throwable $_) {
            return $default;
        }
    }

    private function generateNextPurchaseDocumentNumber(\CodeIgniter\Database\BaseConnection $db): string
    {
        $prefix = $this->getPurchaseDocumentPrefix();
        $lastNumber = 0;

        try {
            $rfqRow = $db->query(
                'SELECT rfq_number AS doc_number FROM purchase_rfqs WHERE rfq_number LIKE ? ORDER BY rfq_number DESC LIMIT 1 FOR UPDATE',
                [$prefix . '%']
            )->getRowArray();
            if (!empty($rfqRow['doc_number'])) {
                $lastNumber = max($lastNumber, (int) preg_replace('/[^0-9]/', '', (string) $rfqRow['doc_number']));
            }
        } catch (\Throwable $_) {}

        try {
            $poRow = $db->query(
                'SELECT po_number AS doc_number FROM purchase_orders WHERE po_number LIKE ? ORDER BY po_number DESC LIMIT 1 FOR UPDATE',
                [$prefix . '%']
            )->getRowArray();
            if (!empty($poRow['doc_number'])) {
                $lastNumber = max($lastNumber, (int) preg_replace('/[^0-9]/', '', (string) $poRow['doc_number']));
            }
        } catch (\Throwable $_) {}

        return $prefix . str_pad((string) ($lastNumber + 1), 4, '0', STR_PAD_LEFT);
    }

    private function getDefaultPurchaseCurrency(): string
    {
        try {
            $company = (new CompanySettingsModel())->first();
            if (!empty($company['default_purchase_currency'])) return $company['default_purchase_currency'];
            if (!empty($company['base_currency'])) return $company['base_currency'];
            if (!empty($company['secondary_currency'])) return $company['secondary_currency'];
        } catch (\Throwable $_) { }
        return 'USD';
    }
    // Create a new Purchase Order (initially draft)
    public function create()
    {
        if ($this->request->getMethod() !== 'post') return $this->response->setStatusCode(405);
        $data = $this->request->getPost();
        $rfqId = isset($data['rfq_id']) ? (int)$data['rfq_id'] : null;
        $vendorId = isset($data['vendor_id']) ? (int)$data['vendor_id'] : null;
        $lines = $data['lines'] ?? [];

        $variantIssues = $this->validateVariantSelections($lines);
        if (!empty($variantIssues['missing']) || !empty($variantIssues['invalid'])) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'error' => $this->buildVariantValidationMessage($variantIssues),
            ]);
        }

    $currency = isset($data['currency']) && $data['currency'] !== '' ? strtoupper(trim((string)$data['currency'])) : $this->getDefaultPurchaseCurrency();

        $db = Database::connect();
        $db->transBegin();
        try {
            $poModel = new PurchaseOrderModel();
            $poCols = [];
            try { $poCols = $db->getFieldNames('purchase_orders'); } catch (\Throwable $_) { $poCols = []; }
            $poNumber = trim((string)($data['po_number'] ?? ''));
            if ($poNumber === '') {
                $poNumber = $this->generateNextPurchaseDocumentNumber($db);
            }
            $poInsert = [
                'po_number' => $poNumber,
                'rfq_id' => $rfqId,
                'vendor_id' => $vendorId,
                'status' => 'draft',
                'subtotal' => $data['subtotal'] ?? null,
                'total' => $data['total'] ?? null,
                'created_by' => session()->get('user_id')?:null,
                'created_at' => date('Y-m-d H:i:s')
            ];
            if (in_array('currency', $poCols)) {
                $poInsert['currency'] = $currency;
            }
            $poId = $poModel->insert($poInsert, true);
            if (!$poId) throw new \RuntimeException('PO insert failed');

            $lineModel = new PurchaseOrderLineModel();
            $lineCols = [];
            try { $lineCols = $db->getFieldNames('purchase_order_lines'); } catch (\Throwable $_) { $lineCols = []; }
            foreach ($lines as $ln) {
                // accept either 'qty' or legacy 'quantity'
                $qty = isset($ln['qty']) ? (float)$ln['qty'] : (isset($ln['quantity']) ? (float)$ln['quantity'] : 0);
                if ($qty <= 0) continue;
                $lineInsert = [
                    'po_id' => $poId,
                    'product_id' => isset($ln['product_id']) ? (int)$ln['product_id'] : null,
                    'description' => $ln['description'] ?? null,
                    'qty' => $qty,
                    'unit_price' => isset($ln['unit_price']) ? (float)$ln['unit_price'] : (isset($ln['unit_cost']) ? (float)$ln['unit_cost'] : null),
                    'qty_received' => 0,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                if (!empty($lineCols) && in_array('variant_id', $lineCols, true)) {
                    $variantId = $ln['variant_id'] ?? $ln['product_variant_id'] ?? null;
                    if (!empty($variantId)) {
                        $lineInsert['variant_id'] = (int)$variantId;
                    }
                }
                $lineModel->insert($lineInsert);
            }

            $db->transCommit();
            DocumentLogger::log(DocumentLogger::TYPE_PURCHASE_ORDER, (int)$poId, DocumentLogger::ACTION_CREATED);
            foreach ($lines as $ln) {
                $qty = isset($ln['qty']) ? (float)$ln['qty'] : (isset($ln['quantity']) ? (float)$ln['quantity'] : 0);
                if ($qty <= 0) continue;
                $pName = trim((string)($ln['description'] ?? ''));
                DocumentLogger::log(DocumentLogger::TYPE_PURCHASE_ORDER, (int)$poId, DocumentLogger::ACTION_LINE_ADDED, [
                    'product' => $pName ?: ('Product #' . ($ln['product_id'] ?? '')),
                    'qty'     => $qty,
                    'price'   => isset($ln['unit_price']) ? number_format((float)$ln['unit_price'], 2) : null,
                ]);
            }
            return $this->response->setJSON(['success' => true, 'po_id' => $poId]);
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'PO create failed: '.$e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['error' => 'Failed to create PO']);
        }
    }

    // List POs (read-only) for UI consumption
    public function index()
    {
    if (strtolower($this->request->getMethod()) !== 'get') { return $this->response->setStatusCode(405)->setJSON(['success' => false, 'error' => 'Method not allowed']); }
        $searchTerm = $this->request->getGet('q') ?? $this->request->getGet('search');
        $userId = (int) (session()->get('user_id') ?? 0);
        $access = (new RoleDataAccess())->resolveForUser($userId);
        $isolate = !empty($access['isolate_purchase_orders']);
        $isAdmin = service('policy')->isAdmin();
        $privateUserIds = (new RoleDataAccess())->getPrivateUserIds($userId, $isAdmin);
        $db = Database::connect();
        $builder = $db->table('purchase_orders po')
            ->select('po.*, v.name as vendor_name,
                (SELECT COUNT(*) FROM purchase_order_lines pol WHERE pol.po_id = po.id) AS line_count,
                (SELECT COALESCE(p.name, pol.description, "") FROM purchase_order_lines pol LEFT JOIN products p ON p.id = pol.product_id WHERE pol.po_id = po.id ORDER BY pol.id LIMIT 1) AS sample_product,
                (SELECT COALESCE(p.code, "") FROM purchase_order_lines pol LEFT JOIN products p ON p.id = pol.product_id WHERE pol.po_id = po.id ORDER BY pol.id LIMIT 1) AS sample_product_code,
                (SELECT COALESCE(pv.art_number, "") FROM purchase_order_lines pol LEFT JOIN product_variants pv ON pv.id = pol.variant_id WHERE pol.po_id = po.id ORDER BY pol.id LIMIT 1) AS sample_variant_code,
                (SELECT COALESCE(pv.name, "") FROM purchase_order_lines pol LEFT JOIN product_variants pv ON pv.id = pol.variant_id WHERE pol.po_id = po.id ORDER BY pol.id LIMIT 1) AS sample_variant_name')
            ->join('vendors v', 'v.id = po.vendor_id', 'left')
            ->orderBy('po.created_at', 'DESC');
        if ($isolate && $db->fieldExists('created_by', 'purchase_orders')) {
            $builder->where('po.created_by', $userId);
        }
        if (!empty($privateUserIds) && $db->fieldExists('created_by', 'purchase_orders')) {
            $builder->whereNotIn('po.created_by', $privateUserIds);
        }
        $raw = $builder->get()->getResultArray();
        
        // Get GRN IDs and receipt status for all POs
        $grnMap = [];
        $receiptStatusMap = [];
        $lineQtyMap = [];
        $grnQtyMap = [];
        try {
            $db = Database::connect();
            $poIds = array_values(array_filter(array_map(function($r){ return $r['id'] ?? null; }, $raw)));
            
            // Get GRNs for POs
            if (!empty($poIds)) {
                $grnRows = $db->table('purchase_grns')
                    ->select('po_id, MAX(id) as id')
                    ->whereIn('po_id', $poIds)
                    ->groupBy('po_id')
                    ->get()
                    ->getResultArray();
                foreach ($grnRows as $grn) {
                    $grnMap[(int)$grn['po_id']] = (int)$grn['id'];
                }

                $lineQtyRows = $db->table('purchase_order_lines')
                    ->select('po_id, SUM(qty) as total_qty, SUM(qty_received) as total_received')
                    ->whereIn('po_id', $poIds)
                    ->groupBy('po_id')
                    ->get()
                    ->getResultArray();
                foreach ($lineQtyRows as $lineQtyRow) {
                    $lineQtyMap[(int)$lineQtyRow['po_id']] = [
                        'ordered' => (float)($lineQtyRow['total_qty'] ?? 0),
                        'received' => (float)($lineQtyRow['total_received'] ?? 0),
                    ];
                }

                $grnQtyRows = $db->table('purchase_grn_lines pgl')
                    ->select('pg.po_id, SUM(pgl.qty_received) as total_received')
                    ->join('purchase_grns pg', 'pg.id = pgl.grn_id', 'inner')
                    ->whereIn('pg.po_id', $poIds)
                    ->groupBy('pg.po_id')
                    ->get()
                    ->getResultArray();
                foreach ($grnQtyRows as $grnQtyRow) {
                    $grnQtyMap[(int)$grnQtyRow['po_id']] = (float)($grnQtyRow['total_received'] ?? 0);
                }
            }
            
            // Detect receipt status for each PO
            foreach ($raw as $po) {
                $poId = (int)($po['id'] ?? 0);
                $poStatus = strtolower($po['status'] ?? '');
                $orderedQty = (float)($lineQtyMap[$poId]['ordered'] ?? 0);
                $lineReceivedQty = (float)($lineQtyMap[$poId]['received'] ?? 0);
                $grnReceivedQty = (float)($grnQtyMap[$poId] ?? 0);
                $receivedQty = max($lineReceivedQty, $grnReceivedQty);
                
                // Status priority: cancelled > fully_received > partial_received > closed > open
                if (strpos($poStatus, 'cancel') === 0) {
                    $receiptStatusMap[$poId] = 'cancelled';
                } elseif ((strpos($poStatus, 'received') === 0) || ($orderedQty > 0 && $receivedQty >= $orderedQty)) {
                    $receiptStatusMap[$poId] = 'fully_received';
                } elseif ($receivedQty > 0) {
                    $receiptStatusMap[$poId] = 'partial_received';
                } elseif (strpos($poStatus, 'closed') === 0) {
                    $receiptStatusMap[$poId] = 'closed';
                } else {
                    $receiptStatusMap[$poId] = 'open';
                }
            }
        } catch (\Throwable $_) {}
        
        $rows = [];
        foreach ($raw as $r) {
            $poId = (int)($r['id'] ?? 0);
            
            // Calculate correct totals from lines to include taxes
            $computed = ['subtotal' => 0, 'total_discount' => 0, 'total_tax' => 0, 'grand_total' => 0];
            try {
                $lineRows = Database::connect()->table('purchase_order_lines')
                    ->where('po_id', $poId)
                    ->get()
                    ->getResultArray();
                
                foreach ($lineRows as $line) {
                    $qty = (float)($line['qty'] ?? 0);
                    $unitPrice = (float)($line['unit_price'] ?? 0);
                    $discountPct = (float)($line['discount_percent'] ?? 0);
                    $taxPct = (float)($line['tax_percent'] ?? 0);
                    
                    $lineBase = $qty * $unitPrice;
                    $discountAmount = ($discountPct / 100) * $lineBase;
                    $taxable = max(0, $lineBase - $discountAmount);
                    $taxAmount = ($taxPct / 100) * $taxable;
                    
                    $computed['subtotal'] += $lineBase;
                    $computed['total_discount'] += $discountAmount;
                    $computed['total_tax'] += $taxAmount;
                    $computed['grand_total'] += ($taxable + $taxAmount);
                }
            } catch (\Throwable $_) {}
            
            // Use computed values if available and greater than 0, fallback to DB values
            $subtotal = $computed['subtotal'] > 0 ? $computed['subtotal'] : ((float)($r['subtotal'] ?? 0));
            $totalTax = $computed['total_tax'] > 0 ? $computed['total_tax'] : ((float)($r['total_tax'] ?? 0));
            $grandTotal = $computed['grand_total'] > 0 ? $computed['grand_total'] : ((float)($r['total'] ?? 0));
            
            $rows[] = [
                'id' => $r['id'] ?? null,
                'po_number' => $r['po_number'] ?? null,
                'rfq_id' => $r['rfq_id'] ?? null,
                'vendor_id' => $r['vendor_id'] ?? null,
                'vendor_name' => $r['vendor_name'] ?? null,
                'status' => $r['status'] ?? null,
                'subtotal' => $subtotal,
                'total_discount' => $computed['total_discount'] > 0 ? $computed['total_discount'] : ((float)($r['total_discount'] ?? 0)),
                'total_tax' => $totalTax,
                'total' => $grandTotal,
                'grand_total' => $grandTotal,
                'currency' => $r['currency'] ?? null,
                'delivery_date' => $r['delivery_date'] ?? null,
                'created_at' => $r['created_at'] ?? null,
                'grn_id' => $grnMap[$poId] ?? null,
                'receipt_status' => $receiptStatusMap[$poId] ?? 'open',
                'line_count' => $r['line_count'] ?? 0,
                'sample_product' => $r['sample_product'] ?? null,
                'sample_product_code' => $r['sample_product_code'] ?? null,
                'sample_variant_code' => $r['sample_variant_code'] ?? null,
                'sample_variant_name' => $r['sample_variant_name'] ?? null,
            ];
        }
        if (!empty($searchTerm)) {
            $rows = SearchService::filterRows($rows, $searchTerm, [
                'po_number',
                'vendor_name',
                'vendor_id',
                'status',
                'currency',
                'created_at'
            ]);
        }
        return $this->response->setJSON(['success' => true, 'data' => $rows]);
    }

    // Confirm a PO (change status draft -> confirmed)
    public function confirm($id = null)
    {
        $poId = (int)$id;
        if ($poId <= 0) return $this->response->setStatusCode(400)->setJSON(['error' => 'Invalid PO id']);
        $poModel = new PurchaseOrderModel();
        $po = $poModel->find($poId);
        if (!$po) return $this->response->setStatusCode(404)->setJSON(['error' => 'PO not found']);
        if ($po['status'] !== 'draft') return $this->response->setStatusCode(400)->setJSON(['error' => 'Only draft PO can be confirmed']);

        $poModel->update($poId, ['status' => 'confirmed', 'updated_at' => date('Y-m-d H:i:s')]);
        DocumentLogger::log(DocumentLogger::TYPE_PURCHASE_ORDER, $poId, DocumentLogger::ACTION_STATUS_CHANGED, ['from' => 'draft', 'to' => 'confirmed']);
        return $this->response->setJSON(['success' => true]);
    }

    /**
     * setDraft() so quantities/prices can be edited before rebilling.
     * Guardrails:
     * - Disallow when PO is cancelled/closed.
     * - Disallow when any confirmed/paid vendor bill exists against this PO.
     */
    public function setDraft($id = null)
    {
        $poId = (int)$id;
        if ($poId <= 0) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'Invalid PO id']);
        }

        $poModel = new PurchaseOrderModel();
        $po = $poModel->find($poId);
        if (!$po) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'PO not found']);
        }

        $status = strtolower((string)($po['status'] ?? ''));
        if (in_array($status, ['cancelled', 'closed'], true)) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'error' => 'Cancelled or closed PO cannot be reopened to draft',
            ]);
        }

        $db = Database::connect();
        $blockingBill = null;
        try {
            if ($db->tableExists('vendor_bills')) {
                $blockingBill = $db->query(
                    "SELECT id, status FROM vendor_bills WHERE po_id = ? AND LOWER(COALESCE(status, '')) IN ('confirmed','partially_paid','paid') ORDER BY id DESC LIMIT 1",
                    [$poId]
                )->getRowArray();
            }
        } catch (\Throwable $_) {
            $blockingBill = null;
        }

        if (!empty($blockingBill)) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'error' => 'Cannot set PO to draft because a confirmed/paid bill exists (Bill #' . (int)$blockingBill['id'] . '). Use bill adjustment flow instead.',
                'bill_id' => (int)$blockingBill['id'],
            ]);
        }

        $poModel->update($poId, ['status' => 'draft', 'updated_at' => date('Y-m-d H:i:s')]);
        DocumentLogger::log(DocumentLogger::TYPE_PURCHASE_ORDER, $poId, DocumentLogger::ACTION_STATUS_CHANGED, ['from' => $status, 'to' => 'draft']);
        return $this->response->setJSON(['success' => true, 'message' => 'PO set to draft']);
    }

    // Create PO from an accepted RFQ
    public function from_rfq($rfq_id = null)
    {
        if ($this->request->getMethod() !== 'post') return $this->response->setStatusCode(405)->setJSON(['error' => 'POST required']);
        $rfqId = (int)$rfq_id;
        if ($rfqId <= 0) return $this->response->setStatusCode(400)->setJSON(['error' => 'Invalid RFQ id']);

        $rfqModel = new \App\Models\PurchaseRfqModel();
        $rfqLineModel = new \App\Models\PurchaseRfqLineModel();
        $poModel = new PurchaseOrderModel();
        $poLineModel = new PurchaseOrderLineModel();

        $rfq = $rfqModel->find($rfqId);
        if (!$rfq) return $this->response->setStatusCode(404)->setJSON(['error' => 'RFQ not found']);
        if ($rfq['status'] !== 'accepted') return $this->response->setStatusCode(400)->setJSON(['error' => 'RFQ must be accepted before creating PO']);

        $db = Database::connect();
        $db->transBegin();
        try {
            $poCols = [];
            try { $poCols = $db->getFieldNames('purchase_orders'); } catch (\Throwable $_) { $poCols = []; }
            $poInsert = [
                'po_number' => null,
                'rfq_id' => $rfqId,
                'vendor_id' => $rfq['vendor_id'] ?? null,
                'status' => 'draft',
                'subtotal' => null,
                'total' => null,
                'created_by' => session()->get('user_id')?:null,
                'created_at' => date('Y-m-d H:i:s'),
                'delivery_date' => $rfq['delivery_date'] ?? null
            ];
            if (in_array('currency', $poCols)) {
                $poInsert['currency'] = $rfq['currency'] ?? $this->getDefaultPurchaseCurrency();
            }
            $poId = $poModel->insert($poInsert, true);
            if (!$poId) throw new \RuntimeException('PO insert failed');

            $rfqLines = $rfqLineModel->where('rfq_id', $rfqId)->findAll();
            $lineCols = [];
            try { $lineCols = $db->getFieldNames('purchase_order_lines'); } catch (\Throwable $_) { $lineCols = []; }
            foreach ($rfqLines as $ln) {
                // tolerate both rfq line naming styles
                $qty = isset($ln['qty']) ? (float)$ln['qty'] : (isset($ln['quantity']) ? (float)$ln['quantity'] : 0);
                if ($qty <= 0) continue;
                $lineInsert = [
                    'po_id' => $poId,
                    'product_id' => $ln['product_id'] ?? null,
                    'description' => $ln['description'] ?? null,
                    'qty' => $qty,
                    'unit_price' => isset($ln['unit_price']) ? (float)$ln['unit_price'] : (isset($ln['unit_cost']) ? (float)$ln['unit_cost'] : null),
                    'qty_received' => 0,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                if (!empty($lineCols) && in_array('variant_id', $lineCols, true)) {
                    $variantId = $ln['variant_id'] ?? $ln['product_variant_id'] ?? null;
                    if (!empty($variantId)) {
                        $lineInsert['variant_id'] = (int)$variantId;
                    }
                }
                $poLineModel->insert($lineInsert);
            }

            $db->transCommit();
            return $this->response->setJSON(['success' => true, 'po_id' => $poId]);
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'PO from RFQ failed: '.$e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['error' => 'Failed to create PO from RFQ']);
        }
    }

    // Show a single PO with lines (for view/edit UI)
    public function show($id = null)
    {
        $accept = strtolower((string)($this->request->getHeaderLine('Accept') ?? ''));
        $wantsJson = $this->request->isAJAX() || strpos($accept, 'application/json') !== false;

        $poModel = new PurchaseOrderModel();
        $po = $poModel->findByPublicIdOrId($id);
        if (!$po) {
            if ($wantsJson) {
                return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'PO not found']);
            }
            return redirect()->to(site_url('newpurchaseui/rfqpo'))->with('error', 'Purchase order not found.');
        }
        $poId = (int)$po['id'];

        $db = Database::connect();
        $userId = (int) (session()->get('user_id') ?? 0);
        $access = (new RoleDataAccess())->resolveForUser($userId);
        $isolate = !empty($access['isolate_purchase_orders']);
        if ($isolate && $db->fieldExists('created_by', 'purchase_orders')) {
            $ownerId = (int) ($po['created_by'] ?? 0);
            if ($ownerId > 0 && $ownerId !== $userId) {
                if ($wantsJson) {
                    return $this->response->setStatusCode(403)->setJSON(['success' => false, 'error' => 'Access denied by role data policy']);
                }
                return redirect()->to('/new-purchase-orders')->with('error', 'You are not allowed to view this purchase order.');
            }
        }

        // Browser navigation should open the full PO UI page.
        if (!$wantsJson) {
            $company = null;
            try { $company = (new CompanySettingsModel())->first(); } catch (\Throwable $_) { $company = null; }
            $defaultPurchaseCurrency = $company['default_purchase_currency'] ?? ($company['base_currency'] ?? 'PKR');
            return view('purchase_ui/po_view', [
                'defaultCurrency' => $defaultPurchaseCurrency,
                'poIdentifier'    => (string)$id,
            ]);
        }

        // Fetch lines
        $lineModel = new PurchaseOrderLineModel();
        $lineOrderField = 'id';
        try {
            if ($db->fieldExists('sort_order', 'purchase_order_lines')) {
                $lineOrderField = 'sort_order';
            }
        } catch (\Throwable $_) {
            $lineOrderField = 'id';
        }
        $lines = $lineModel->where('po_id', $poId)->orderBy($lineOrderField, 'ASC')->orderBy('id','ASC')->findAll();

        // Enrich lines with product info (name/code/sku) and VARIANT info (image/code/name) when possible
        try {
            $db = Database::connect();
            $productIds = array_values(array_filter(array_unique(array_map(function($l){ return isset($l['product_id']) ? (int)$l['product_id'] : null; }, $lines))));
            $variantIds = array_values(array_filter(array_unique(array_map(function($l){
                if (isset($l['variant_id']) && $l['variant_id']) return (int)$l['variant_id'];
                if (isset($l['product_variant_id']) && $l['product_variant_id']) return (int)$l['product_variant_id'];
                return null;
            }, $lines))));
            
            $prodMap = [];
            $variantMap = [];
            $variantsByProduct = [];
            
            // Get product details
            if (!empty($productIds)) {
                $productModel = new \App\Models\ProductModel();
                $products = $productModel->whereIn('id', $productIds)->findAll();
                foreach ($products as $p) {
                    $prodMap[(int)$p['id']] = $p;
                }
            }
            
            // Get variant details (image, code, name)
            if (!empty($variantIds) && $db->tableExists('product_variants')) {
                try {
                    $variants = $db->table('product_variants')
                        ->select('id, product_id, art_number, name, image, attributes')
                        ->whereIn('id', $variantIds)
                        ->get()
                        ->getResultArray();
                    foreach ($variants as $v) {
                        $variantMap[(int)$v['id']] = $v;
                    }
                } catch (\Throwable $_) {
                    // if variant fetch fails, continue without variant data
                }
            }

            // Fallback: preload variants by product for description-based matching
            if (!empty($productIds) && $db->tableExists('product_variants')) {
                try {
                    $allVariants = $db->table('product_variants')
                        ->select('id, product_id, art_number, name, image, attributes')
                        ->whereIn('product_id', $productIds)
                        ->get()
                        ->getResultArray();
                    foreach ($allVariants as $v) {
                        $pid = (int)($v['product_id'] ?? 0);
                        if ($pid <= 0) continue;
                        if (!isset($variantsByProduct[$pid])) $variantsByProduct[$pid] = [];
                        $variantsByProduct[$pid][] = $v;
                    }
                } catch (\Throwable $_) {
                    // ignore
                }
            }
            
            // Enrich each line
            foreach ($lines as &$ln) {
                $pid = isset($ln['product_id']) ? (int)$ln['product_id'] : null;
                $vid = isset($ln['variant_id']) ? (int)$ln['variant_id'] : (isset($ln['product_variant_id']) ? (int)$ln['product_variant_id'] : null);
                
                // Enrich product data
                if ($pid && isset($prodMap[$pid])) {
                    $p = $prodMap[$pid];
                    $ln['product_name'] = $p['name'] ?? null;
                    $ln['product_code'] = $p['code'] ?? ($p['sku'] ?? null);
                    $ln['product_unit'] = $p['unit'] ?? null;
                    $ln['product_type'] = $p['product_type'] ?? null;
                    $ln['detailed_type'] = $p['detailed_type'] ?? null;
                    $ln['product_image'] = $p['image'] ?? null;
                    if (!empty($p['image'])) {
                        $ln['product_image_url'] = base_url('/uploads/products/' . ltrim($p['image'], '/'));
                    } elseif (!empty($p['images'])) {
                        $imgs = is_string($p['images']) ? json_decode($p['images'], true) : $p['images'];
                        if (is_array($imgs) && !empty($imgs[0])) {
                            $ln['product_image_url'] = base_url('/uploads/products/' . ltrim($imgs[0], '/'));
                        }
                    }
                }
                
                // Enrich variant data (PRIORITY over product for image/code)
                if ($vid && isset($variantMap[$vid])) {
                    $v = $variantMap[$vid];
                    $ln['variant_art_number'] = $v['art_number'] ?? null;
                    $ln['variant_code'] = $v['art_number'] ?? null;
                    $ln['variant_name'] = $v['name'] ?? null;
                    $ln['variant_image'] = $v['image'] ?? null;
                    if (!empty($v['image'])) {
                        $ln['variant_image_url'] = base_url('/uploads/variants/' . ltrim($v['image'], '/'));
                    }
                }

                // If no variant id, try to match by description against variant attributes
                if (!$vid && $pid && !empty($ln['description']) && !empty($variantsByProduct[$pid])) {
                    $desc = (string)$ln['description'];
                    foreach ($variantsByProduct[$pid] as $vdata) {
                        $attrs = $vdata['attributes'] ?? null;
                        if (empty($attrs)) {
                            continue;
                        }
                        $attrsArr = is_string($attrs) ? json_decode($attrs, true) : $attrs;
                        if (!is_array($attrsArr)) {
                            continue;
                        }
                        $match = true;
                        foreach ($attrsArr as $key => $value) {
                            if ($value === null || $value === '') continue;
                            if (stripos($desc, (string)$value) === false) {
                                $match = false;
                                break;
                            }
                        }
                        if ($match) {
                            $vid = (int)($vdata['id'] ?? 0);
                            $ln['variant_id'] = $vid;
                            $ln['variant_art_number'] = $vdata['art_number'] ?? null;
                            $ln['variant_code'] = $vdata['art_number'] ?? null;
                            $ln['variant_name'] = $vdata['name'] ?? null;
                            $ln['variant_image'] = $vdata['image'] ?? null;
                            if (!empty($vdata['image'])) {
                                $ln['variant_image_url'] = base_url('/uploads/variants/' . ltrim($vdata['image'], '/'));
                            }
                            break;
                        }
                    }
                }
            }
            unset($ln);
        } catch (\Throwable $_) {
            // non-fatal: if product/variant table missing or query fails, continue without enrichment
        }

        // Classify PO document type for UI behavior (inventory vs service vs mixed).
        $serviceLineCount = 0;
        $inventoryLineCount = 0;
        foreach ($lines as &$ln) {
            $desc = strtolower(trim((string)($ln['description'] ?? '')));
            $pType = strtolower(trim((string)($ln['product_type'] ?? '')));
            $dType = strtolower(trim((string)($ln['detailed_type'] ?? '')));
            $unit = strtolower(trim((string)($ln['product_unit'] ?? ($ln['unit'] ?? ''))));

            $isService = in_array($dType, ['service', 'services'], true)
                || in_array($pType, ['service', 'services'], true)
                || in_array($unit, ['service', 'svc', 'shp', 'shipment'], true)
                || strpos($desc, 'shipping:') === 0
                || (strpos($desc, 'shipping') !== false && strpos($desc, '(do #') !== false);

            $ln['is_service_line'] = $isService;
            if ($isService) {
                $serviceLineCount++;
            } else {
                $inventoryLineCount++;
            }
        }
        unset($ln);

        // Ensure each line has a computed line_total (qty * unit_price minus discounts/taxes if present)
        try {
            foreach ($lines as &$ln) {
                $qty = isset($ln['qty']) ? (float)$ln['qty'] : (isset($ln['quantity']) ? (float)$ln['quantity'] : 0);
                $unit = isset($ln['unit_price']) ? (float)$ln['unit_price'] : (isset($ln['unit_cost']) ? (float)$ln['unit_cost'] : 0);
                $discount = isset($ln['discount']) ? (float)$ln['discount'] : 0;
                $tax = isset($ln['tax_amount']) ? (float)$ln['tax_amount'] : 0;
                // Basic calculation: qty * unit_price - discount + tax (if discount/tax present). If not present, fallback to qty * unit_price.
                $calc = ($qty * $unit) - $discount + $tax;
                $ln['line_total'] = is_nan($calc) ? 0.0 : (float) round($calc, 4);
            }
            unset($ln);
        } catch (\Throwable $_) {}

        // Compute PO subtotal/total if missing
        try {
            $computedSubtotal = 0.0;
            foreach ($lines as $ln) { $computedSubtotal += isset($ln['line_total']) ? (float)$ln['line_total'] : 0.0; }
            if (!isset($po['subtotal']) || $po['subtotal'] === null) $po['subtotal'] = (float) round($computedSubtotal, 4);
            if (!isset($po['total']) || $po['total'] === null) $po['total'] = (float) round($computedSubtotal, 4);
        } catch (\Throwable $_) {}

        // Enrich with vendor name if available
        $vendorName = null;
        try {
            $db = Database::connect();
            $r = $db->query('SELECT name FROM vendors WHERE id = ? LIMIT 1', [$po['vendor_id']])->getRowArray();
            if ($r) $vendorName = $r['name'] ?? null;
        } catch (\Throwable $_) {}

        $po['vendor_name'] = $vendorName;

        // Check if GRN exists for this PO
        $grnId = null;
        $grnPublicId = null;
        try {
            $db = Database::connect();
            $hasGrnPublicId = $db->fieldExists('public_id', 'purchase_grns');
            $grnSelect = $hasGrnPublicId ? 'id, public_id' : 'id';
            $grnRow = $db->table('purchase_grns')
                ->select($grnSelect)
                ->where('po_id', $poId)
                ->orderBy('id', 'DESC')
                ->limit(1)
                ->get()
                ->getRowArray();
            if ($grnRow) {
                $grnId = (int)$grnRow['id'];
                $grnPublicId = trim((string)($grnRow['public_id'] ?? ''));
            }
        } catch (\Throwable $_) {}

        $po['grn_id'] = $grnId;
        $po['grn_public_id'] = $grnPublicId;

        // Check if vendor bill exists for this PO
        $vendorBillId = null;
        $vendorBillStatus = null;
        $poBills = [];
        try {
            $db = Database::connect();
            $billRows = $db->table('vendor_bills')
                ->select('id,status,bill_number,bill_date,total_amount,balance,based_on,notes,created_at')
                ->where('po_id', $poId)
                ->where('status !=', 'cancelled')
                ->orderBy('id', 'DESC')
                ->get()
                ->getResultArray();
            foreach ($billRows as &$br) {
                $basedOn = strtolower(trim((string)($br['based_on'] ?? '')));
                $notes = strtolower((string)($br['notes'] ?? ''));
                if ($basedOn === '' && strpos($notes, 'over-receipt adjustment from grn') !== false) {
                    $basedOn = 'po_over_receipt';
                    $br['based_on'] = $basedOn;
                }

                if ($basedOn === 'po_over_receipt') {
                    $br['source_label'] = 'Over Receipt Adjustment';
                } elseif ($basedOn === 'po_qty_adjustment') {
                    $br['source_label'] = 'PO Qty Adjustment';
                } elseif ($basedOn === 'po_qty') {
                    $br['source_label'] = 'PO Base Bill';
                } else {
                    $br['source_label'] = $basedOn !== '' ? strtoupper(str_replace('_', ' ', $basedOn)) : 'Manual';
                }
            }
            unset($br);

            if (!empty($billRows)) {
                $vendorBillId = (int)$billRows[0]['id'];
                $vendorBillStatus = strtolower((string)($billRows[0]['status'] ?? ''));
                $poBills = $billRows;
            }
        } catch (\Throwable $_) {}

        $po['vendor_bill_id'] = $vendorBillId;
        $po['vendor_bill_status'] = $vendorBillStatus;
        $po['vendor_bills'] = $poBills;

        // Over-receipt summary + reason trail from GRN lines
        $po['over_received_total'] = 0.0;
        $po['over_receipt_reasons'] = [];
        $po['latest_over_receipt_bill_id'] = null;
        try {
            $db = Database::connect();
            if ($db->tableExists('purchase_grn_lines') && $db->tableExists('purchase_grns')) {
                $sumRow = $db->query(
                    'SELECT COALESCE(SUM(COALESCE(gl.over_received_qty,0)),0) AS over_total '
                    . 'FROM purchase_grn_lines gl '
                    . 'INNER JOIN purchase_grns g ON g.id = gl.grn_id '
                    . 'WHERE g.po_id = ?',
                    [$poId]
                )->getRowArray();
                $po['over_received_total'] = round((float)($sumRow['over_total'] ?? 0), 4);

                $lineCols = $db->getFieldNames('purchase_grn_lines');
                if (in_array('over_receipt_reason_type', $lineCols, true) || in_array('over_receipt_reason_details', $lineCols, true)) {
                    $reasonTypeExpr = in_array('over_receipt_reason_type', $lineCols, true) ? 'gl.over_receipt_reason_type' : "'' AS over_receipt_reason_type";
                    $reasonDetailsExpr = in_array('over_receipt_reason_details', $lineCols, true) ? 'gl.over_receipt_reason_details' : "'' AS over_receipt_reason_details";
                    $po['over_receipt_reasons'] = $db->query(
                        'SELECT g.id AS grn_id, g.received_at, ' . $reasonTypeExpr . ', ' . $reasonDetailsExpr . ', COALESCE(gl.over_received_qty,0) AS over_received_qty '
                        . 'FROM purchase_grn_lines gl '
                        . 'INNER JOIN purchase_grns g ON g.id = gl.grn_id '
                        . 'WHERE g.po_id = ? AND COALESCE(gl.over_received_qty,0) > 0 '
                        . 'ORDER BY g.id DESC, gl.id DESC',
                        [$poId]
                    )->getResultArray();
                }

                // Fallback for historical rows where reason columns were unavailable.
                if (empty($po['over_receipt_reasons']) && $po['over_received_total'] > 0) {
                    $fallbackRows = $db->query(
                        "SELECT id, bill_date, notes FROM vendor_bills WHERE po_id = ? AND LOWER(COALESCE(status,'')) <> 'cancelled' ORDER BY id DESC",
                        [$poId]
                    )->getResultArray();
                    foreach ($fallbackRows as $fr) {
                        $notes = (string)($fr['notes'] ?? '');
                        if (stripos($notes, 'over-receipt adjustment from grn') === false) {
                            continue;
                        }
                        $det = '';
                        if (preg_match('/\|\s*Details:\s*(.+)$/i', $notes, $m)) {
                            $det = trim((string)$m[1]);
                        }
                        $po['over_receipt_reasons'][] = [
                            'grn_id' => null,
                            'received_at' => ($fr['bill_date'] ?? null),
                            'over_receipt_reason_type' => 'vendor_extra',
                            'over_receipt_reason_details' => $det,
                            'over_received_qty' => $po['over_received_total'],
                        ];
                        break;
                    }
                }
            }

            if ($db->tableExists('vendor_bills')) {
                $adj = $db->table('vendor_bills')
                    ->select('id')
                    ->where('po_id', $poId)
                    ->where('based_on', 'po_over_receipt')
                    ->orderBy('id', 'DESC')
                    ->get()
                    ->getRowArray();
                if (!empty($adj['id'])) {
                    $po['latest_over_receipt_bill_id'] = (int)$adj['id'];
                }
            }
        } catch (\Throwable $_) {}

        // Add vendor balance information
        $vendorBalance = $this->getVendorBalance($po['vendor_id'] ?? null);
        $po['vendor_balance'] = $vendorBalance;

        // Shipping/service context (when PO is generated from a Delivery Order)
        $po['shipping_context'] = null;
        try {
            if ($db->tableExists('delivery_orders')) {
                $doRow = $db->table('delivery_orders')
                    ->select('id, public_id, do_number, status, final_weight_kg, shipped_at, estimated_delivery_days, destination_country, tracking_number, tracking_url')
                    ->where('shipping_po_id', $poId)
                    ->orderBy('id', 'DESC')
                    ->get(1)
                    ->getRowArray();

                // Fallback: derive DO id from service line description pattern "(DO #17)"
                if (empty($doRow)) {
                    foreach ($lines as $ln) {
                        $desc = (string)($ln['description'] ?? '');
                        if (preg_match('/\(\s*DO\s*#\s*(\d+)\s*\)/i', $desc, $m)) {
                            $doId = (int)($m[1] ?? 0);
                            if ($doId > 0) {
                                $doRow = $db->table('delivery_orders')
                                    ->select('id, public_id, do_number, status, final_weight_kg, shipped_at, estimated_delivery_days, destination_country, tracking_number, tracking_url')
                                    ->where('id', $doId)
                                    ->get(1)
                                    ->getRowArray();
                            }
                            break;
                        }
                    }
                }

                if (!empty($doRow)) {
                    $po['shipping_context'] = [
                        'delivery_order_id' => (int)($doRow['id'] ?? 0),
                        'delivery_order_public_id' => (string)($doRow['public_id'] ?? ''),
                        'delivery_order_number' => (string)($doRow['do_number'] ?? ''),
                        'delivery_order_status' => (string)($doRow['status'] ?? ''),
                        'shipment_weight_kg' => (float)($doRow['final_weight_kg'] ?? 0),
                        'shipped_at' => $doRow['shipped_at'] ?? null,
                        'estimated_delivery_days' => isset($doRow['estimated_delivery_days']) ? (int)$doRow['estimated_delivery_days'] : null,
                        'destination_country' => (string)($doRow['destination_country'] ?? ''),
                        'tracking_number' => (string)($doRow['tracking_number'] ?? ''),
                        'tracking_url' => (string)($doRow['tracking_url'] ?? ''),
                    ];
                }
            }
        } catch (\Throwable $_) {}

        $lineCount = count($lines);
        $po['service_line_count'] = $serviceLineCount;
        $po['inventory_line_count'] = $inventoryLineCount;
        $po['is_service_document'] = $lineCount > 0 && $inventoryLineCount === 0 && $serviceLineCount > 0;
        $po['is_mixed_document'] = $lineCount > 0 && $inventoryLineCount > 0 && $serviceLineCount > 0;
        $po['document_type'] = $po['is_service_document']
            ? 'service'
            : ($po['is_mixed_document'] ? 'mixed' : 'inventory');
        $po['suppress_receiving'] = (bool)$po['is_service_document'];

        return $this->response->setJSON(['success' => true, 'data' => ['po' => $po, 'lines' => $lines]]);
    }

    public function printView($id = null)
    {
        $poModel = new PurchaseOrderModel();
        $po = $poModel->findByPublicIdOrId($id);
        if (!$po) {
            return redirect()->back()->with('error', 'Purchase order not found');
        }
        $poId = (int)$po['id'];

        $db = Database::connect();
        $vendor = [];
        try {
            $vendor = $db->table('vendors')->where('id', (int)($po['vendor_id'] ?? 0))->get()->getRowArray() ?: [];
        } catch (\Throwable $_) {}

        $lineModel = new PurchaseOrderLineModel();
        $lineOrderField = 'id';
        try {
            if ($db->fieldExists('sort_order', 'purchase_order_lines')) {
                $lineOrderField = 'sort_order';
            }
        } catch (\Throwable $_) {
            $lineOrderField = 'id';
        }
        $rawLines = $lineModel->where('po_id', $poId)->orderBy($lineOrderField, 'ASC')->orderBy('id', 'ASC')->findAll();

        // Reuse the same enrichment approach as the working PO screen.
        try {
            $productIds = array_values(array_filter(array_unique(array_map(function ($line) {
                return isset($line['product_id']) ? (int) $line['product_id'] : null;
            }, $rawLines))));
            $variantIds = array_values(array_filter(array_unique(array_map(function ($line) {
                if (isset($line['variant_id']) && $line['variant_id']) {
                    return (int) $line['variant_id'];
                }
                if (isset($line['product_variant_id']) && $line['product_variant_id']) {
                    return (int) $line['product_variant_id'];
                }
                return null;
            }, $rawLines))));

            $prodMap = [];
            $variantMap = [];
            $variantsByProduct = [];

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
                        ->select('id, product_id, art_number, name, image, attributes')
                        ->whereIn('id', $variantIds)
                        ->get()
                        ->getResultArray();
                    foreach ($variants as $variant) {
                        $variantMap[(int) $variant['id']] = $variant;
                    }
                } catch (\Throwable $_) {
                }
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
                        $extraProducts = $productModel->whereIn('id', $missingProductIds)->findAll();
                        foreach ($extraProducts as $product) {
                            $prodMap[(int) $product['id']] = $product;
                        }
                    } catch (\Throwable $_) {
                    }
                }
            }

            if (!empty($productIds) && $db->tableExists('product_variants')) {
                try {
                    $allVariants = $db->table('product_variants')
                        ->select('id, product_id, art_number, name, image, attributes')
                        ->whereIn('product_id', $productIds)
                        ->get()
                        ->getResultArray();
                    foreach ($allVariants as $variant) {
                        $productId = (int) ($variant['product_id'] ?? 0);
                        if ($productId <= 0) {
                            continue;
                        }
                        if (!isset($variantsByProduct[$productId])) {
                            $variantsByProduct[$productId] = [];
                        }
                        $variantsByProduct[$productId][] = $variant;
                    }
                } catch (\Throwable $_) {
                }
            }

            foreach ($rawLines as &$line) {
                $productId = isset($line['product_id']) ? (int) $line['product_id'] : null;
                $variantId = isset($line['variant_id']) ? (int) $line['variant_id'] : (isset($line['product_variant_id']) ? (int) $line['product_variant_id'] : null);

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

                if (!$variantId && $productId && !empty($line['description']) && !empty($variantsByProduct[$productId])) {
                    $description = (string) $line['description'];
                    foreach ($variantsByProduct[$productId] as $variant) {
                        $attrs = $variant['attributes'] ?? null;
                        if (empty($attrs)) {
                            continue;
                        }
                        $attrsArr = is_string($attrs) ? json_decode($attrs, true) : $attrs;
                        if (!is_array($attrsArr)) {
                            continue;
                        }
                        $match = true;
                        foreach ($attrsArr as $value) {
                            if ($value === null || $value === '') {
                                continue;
                            }
                            if (stripos($description, (string) $value) === false) {
                                $match = false;
                                break;
                            }
                        }
                        if ($match) {
                            $line['variant_id'] = (int) ($variant['id'] ?? 0);
                            $line['variant_code'] = $variant['art_number'] ?? null;
                            $line['variant_name'] = $variant['name'] ?? null;
                            $line['variant_image'] = $variant['image'] ?? null;
                            break;
                        }
                    }
                }
            }
            unset($line);
        } catch (\Throwable $_) {
            // Keep basic line rows if enrichment fails.
        }

        $company = (new CompanySettingsModel())->orderBy('id', 'DESC')->first() ?: [];

        $lines = [];
        foreach ($rawLines as $ln) {
            $qty = (float) ($ln['qty'] ?? ($ln['quantity'] ?? 0));
            $price = (float) ($ln['unit_price'] ?? ($ln['unit_cost'] ?? 0));
            $storedTotal = isset($ln['line_total']) ? (float) $ln['line_total'] : 0.0;
            $total = $storedTotal > 0 ? $storedTotal : ($qty * $price);
            $code = trim((string) ($ln['variant_code'] ?? ($ln['product_code'] ?? ($ln['code'] ?? ''))));
            $desc = trim((string) ($ln['variant_name'] ?? ($ln['product_name'] ?? ($ln['description'] ?? ''))));
            if ($code === '') {
                $code = trim((string) ($ln['product_code'] ?? ''));
            }
            if ($desc === '' && !empty($ln['description'])) {
                $desc = trim((string) $ln['description']);
            }
            $unit = trim((string) ($ln['product_unit'] ?? ($ln['unit'] ?? '')));

            $imgSrc = '';
            $imageCandidates = [];
            foreach ([
                $ln['variant_image'] ?? '',
                $ln['product_image'] ?? '',
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
            if (empty($imageCandidates) && !empty($ln['product_images'])) {
                $images = is_string($ln['product_images']) ? json_decode($ln['product_images'], true) : $ln['product_images'];
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

            $lines[] = compact('code', 'desc', 'imgSrc', 'qty', 'price', 'total', 'unit');
        }

        $currency = strtoupper(trim((string)(
            $po['currency_code'] ?? ($po['currency'] ?? ($company['base_currency'] ?? 'PKR'))
        )));
        $symbols  = ['USD'=>'$','EUR'=>'€','GBP'=>'£','PKR'=>'₨','INR'=>'₹'];
        $sym      = $symbols[$currency] ?? $currency;
        $fmt      = fn($v) => $sym . ' ' . number_format((float)$v, 2);

        $subtotal   = (float)($po['subtotal'] ?? 0);
        $total      = (float)($po['total'] ?? 0);
        $poNumber   = esc($po['po_number'] ?? ('PO-' . $poId));
        $poDate     = '';
        $raw = trim((string)($po['order_date'] ?? ($po['created_at'] ?? '')));
        if ($raw && strpos($raw, '0000') === false) {
            $ts = strtotime($raw);
            if ($ts) $poDate = date('d-m-Y', $ts);
        }
        $vendorName = esc($vendor['name'] ?? 'Vendor');
        $companyName = esc($company['name'] ?? '');

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>PO <?= $poNumber ?></title>
<style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:Arial,sans-serif;font-size:12px;color:#1e293b;background:#f8fafc;padding:24px}
    .grn-doc{max-width:1100px;margin:0 auto}
    .grn-hero{background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);border-radius:.75rem .75rem 0 0;padding:1.6rem 2rem 1.4rem;color:#fff;position:relative;overflow:hidden}
    .grn-hero::after{content:'PO';position:absolute;right:-1rem;top:50%;transform:translateY(-50%);font-size:7rem;font-weight:900;opacity:.04;pointer-events:none;user-select:none;line-height:1}
    .grn-doc-type{display:inline-flex;align-items:center;gap:.4rem;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);border-radius:2rem;padding:.22rem .8rem;font-size:.7rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#93c5fd;margin-bottom:.55rem}
    .grn-hero-num{font-size:1.85rem;font-weight:800;letter-spacing:-.01em;line-height:1.1;margin-bottom:.25rem}
    .grn-hero-sub{font-size:.82rem;color:rgba(255,255,255,.72)}
    .grn-hero-actions{position:absolute;top:1.05rem;right:1.1rem;display:flex;gap:.4rem;flex-wrap:wrap;justify-content:flex-end;max-width:56%}
    .grn-hero-btn{display:inline-flex;align-items:center;gap:.34rem;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.24);border-radius:.42rem;padding:.34rem .7rem;font-size:.75rem;font-weight:700;color:rgba(255,255,255,.88);text-decoration:none;transition:background .15s,border-color .15s;cursor:pointer}
    .grn-hero-btn:hover{background:rgba(255,255,255,.18);border-color:rgba(255,255,255,.42);color:#fff}
    .grn-facts{background:#fff;border:1px solid #dee2e6;border-top:none;display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr))}
    .grn-fact{padding:.75rem 1rem;border-right:1px solid #dee2e6}
    .grn-fact:last-child{border-right:none}
    .grn-fact-lbl{font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:#64748b;font-weight:700;margin-bottom:.18rem}
    .grn-fact-val{font-size:.95rem;font-weight:700;color:#1e293b}
    .grn-sec{background:#fff;border:1px solid #dee2e6;border-top:none}
    .grn-sec-hd{padding:.7rem 1.3rem;border-bottom:1px solid #dee2e6;display:flex;align-items:center;gap:.55rem;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#6c757d}
    .grn-sec-badge{margin-left:auto;background:#e0e7ff;color:#3730a3;border-radius:2rem;padding:.08rem .5rem;font-size:.68rem;font-weight:700}
    .grn-body{padding:0 1.1rem 1rem}
    .grn-tbl{width:100%;border-collapse:collapse}
    .grn-tbl thead th{background:linear-gradient(180deg,#f8fafc 0%,#eef2f7 100%);border-bottom:2px solid #dbe5f0;padding:.72rem .65rem;text-align:left;font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:#64748b}
    .grn-tbl tbody td{padding:.75rem .65rem;border-bottom:1px solid #eef2f7;vertical-align:middle;font-size:.84rem}
    .grn-tbl .r{text-align:right}
    .prod-code{display:inline-flex;align-items:center;padding:.15rem .45rem;border-radius:999px;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;font-size:.72rem;font-weight:700}
    .prod-thumb{width:42px;height:42px;object-fit:contain;border:1px solid #dbe5f0;border-radius:.35rem;background:#fff}
    .no-img{font-size:.68rem;color:#94a3b8;border:1px dashed #cbd5e1;padding:.18rem .35rem;border-radius:.25rem;display:inline-block}
    .desc-main{font-weight:700;color:#1e293b;line-height:1.45}
    .totals{padding:1rem 1.1rem 1.2rem;display:flex;justify-content:flex-end;background:#fff;border:1px solid #dee2e6;border-top:none;border-radius:0 0 .75rem .75rem}
    .totals table{width:280px;border-collapse:collapse}
    .totals td{padding:.33rem .2rem}
    .totals .lbl{color:#64748b;text-align:right;padding-right:.8rem}
    .totals .val{text-align:right}
    .totals .grand td{font-size:1.08rem;font-weight:700;border-top:2px solid #1e293b;padding-top:.55rem;color:#111827}
    @media print{*{color-adjust:exact!important;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}body{padding:12mm;background:#fff!important;color:#1e293b!important}.no-print,.grn-hero-actions{display:none!important}.grn-doc{max-width:1100px!important;margin:0 auto!important}.grn-hero{background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%)!important;border-radius:.75rem!important;color:#fff!important;border:1px solid #0a0f1a!important;page-break-inside:avoid!important}.grn-hero-num,.grn-hero-sub,.grn-doc-type{color:#fff!important}.grn-doc-type{background:rgba(255,255,255,.12)!important;border:1px solid rgba(255,255,255,.18)!important;color:#93c5fd!important}.grn-facts{background:#fff!important;border:1px solid #dee2e6!important;border-radius:0!important;page-break-inside:avoid!important}.grn-fact{border-right:1px solid #dee2e6!important;background:#fff!important}.grn-fact-lbl{color:#64748b!important}.grn-fact-val{color:#1e293b!important}.grn-sec{background:#fff!important;border:1px solid #dee2e6!important;border-radius:0!important}.grn-sec-hd{background:#f8fafc!important;color:#6c757d!important;border-bottom:1px solid #dee2e6!important}.grn-sec-badge{background:#e0e7ff!important;color:#3730a3!important;border-radius:2rem!important}.grn-body{background:#fff!important}.grn-tbl{width:100%!important;border-collapse:collapse!important;page-break-inside:avoid!important}.grn-tbl thead th{background:linear-gradient(180deg,#f8fafc 0%,#eef2f7 100%)!important;border-bottom:2px solid #dbe5f0!important;color:#64748b!important;text-align:left!important}.grn-tbl tbody td{border-bottom:1px solid #eef2f7!important;color:#1e293b!important;background:#fff!important}.grn-tbl tbody tr{background:#fff!important;page-break-inside:avoid!important}.prod-code{background:#eff6ff!important;border:1px solid #bfdbfe!important;color:#1d4ed8!important;border-radius:999px!important}.prod-thumb{border:1px solid #dbe5f0!important;background:#fff!important}.no-img{color:#94a3b8!important;border:1px dashed #cbd5e1!important;background:#fff!important}.desc-main{color:#1e293b!important;font-weight:700!important}.totals{background:#fff!important;border:1px solid #dee2e6!important;border-radius:.75rem!important;display:flex!important;justify-content:flex-end!important;page-break-inside:avoid!important}.totals table{border-collapse:collapse!important;width:280px!important}.totals td{color:#1e293b!important}.totals .lbl{color:#64748b!important}.totals .grand td{color:#111827!important;border-top:2px solid #1e293b!important;font-weight:700!important}table,thead,tbody,tr,td,th{page-break-inside:avoid!important;break-inside:avoid!important}}
    @media(max-width:768px){body{padding:12px}.grn-hero{padding:1rem 1rem .9rem}.grn-hero-num{font-size:1.3rem}.grn-hero-actions{position:static;max-width:100%;margin-top:.7rem;justify-content:flex-start}.grn-facts{grid-template-columns:1fr 1fr}.grn-fact{padding:.5rem .6rem}.grn-body{padding:0}.grn-tbl{display:block;overflow-x:auto}}
</style>
</head>
<body>
<div class="grn-doc">
    <div class="grn-hero">
        <div class="grn-doc-type">Purchase Order</div>
        <div class="grn-hero-num"><?= $poNumber ?></div>
        <div class="grn-hero-sub"><?= $companyName ?></div>
        <div class="grn-hero-actions no-print">
            <button type="button" class="grn-hero-btn" onclick="window.print()">Print</button>
            <button type="button" class="grn-hero-btn" onclick="window.close()">Close</button>
        </div>
    </div>

    <div class="grn-facts">
        <div class="grn-fact">
            <div class="grn-fact-lbl">Vendor</div>
            <div class="grn-fact-val"><?= $vendorName ?></div>
        </div>
        <div class="grn-fact">
            <div class="grn-fact-lbl">PO Date</div>
            <div class="grn-fact-val"><?= esc($poDate ?: '-') ?></div>
        </div>
        <div class="grn-fact">
            <div class="grn-fact-lbl">Currency</div>
            <div class="grn-fact-val"><?= esc($currency) ?></div>
        </div>
        <div class="grn-fact">
            <div class="grn-fact-lbl">Lines</div>
            <div class="grn-fact-val"><?= number_format(count($lines), 0) ?></div>
        </div>
    </div>

    <div class="grn-sec">
        <div class="grn-sec-hd">Order Lines<span class="grn-sec-badge"><?= number_format(count($lines), 0) ?></span></div>
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
                    <?php foreach ($lines as $ln): ?>
                    <tr>
                        <td><span class="prod-code"><?= esc($ln['code'] !== '' ? $ln['code'] : '-') ?></span></td>
                        <td>
                            <?php if ($ln['imgSrc']): ?>
                                <img class="prod-thumb" src="<?= $ln['imgSrc'] ?>" alt="">
                            <?php else: ?>
                                <span class="no-img">No Img</span>
                            <?php endif ?>
                        </td>
                        <td><div class="desc-main"><?= esc($ln['desc'] !== '' ? $ln['desc'] : '-') ?></div></td>
                        <td><?= esc($ln['unit'] !== '' ? $ln['unit'] : '-') ?></td>
                        <td class="r"><?= number_format($ln['qty'], 2) ?></td>
                        <td class="r"><?= esc($fmt($ln['price'])) ?></td>
                        <td class="r"><?= esc($fmt($ln['total'])) ?></td>
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

    public function pdf($id = null)
    {
        $poModel = new PurchaseOrderModel();
        $po = $poModel->findByPublicIdOrId($id);
        if (!$po) {
            return redirect()->back()->with('error', 'Purchase order not found');
        }
        $poId = (int)$po['id'];

        $vendor = [];
        $db = Database::connect();
        try {
            $vendor = $db->table('vendors')->where('id', (int)($po['vendor_id'] ?? 0))->get()->getRowArray() ?: [];
        } catch (\Throwable $_) {
            $vendor = [];
        }

        // Single JOIN query: fetch lines enriched with product name, variant art_number, and images.
        // This mirrors the same enrichment the PO view UI performs, avoiding silent lookup failures.
        $rawLines = [];
        try {
            $hasVariantCol = $db->fieldExists('variant_id', 'purchase_order_lines');
            $hasPvTable    = $db->tableExists('product_variants');
            if ($hasVariantCol && $hasPvTable) {
                $rawLines = $db->query(
                    'SELECT pol.*,
                            COALESCE(pv.art_number, p.code, \'\') AS _resolved_code,
                            COALESCE(pv.name, p.name, pol.description, \'\') AS _resolved_desc,
                            COALESCE(pv.image, \'\') AS _variant_image,
                            COALESCE(p.image, \'\') AS _product_image,
                            COALESCE(p.images, \'\') AS _product_images
                     FROM purchase_order_lines pol
                     LEFT JOIN products p ON p.id = pol.product_id
                     LEFT JOIN product_variants pv ON pv.id = pol.variant_id
                     WHERE pol.po_id = ?
                     ORDER BY pol.id ASC',
                    [$poId]
                )->getResultArray();
            } else {
                $rawLines = $db->query(
                    'SELECT pol.*,
                            COALESCE(p.code, \'\') AS _resolved_code,
                            COALESCE(p.name, pol.description, \'\') AS _resolved_desc,
                            COALESCE(p.image, \'\') AS _product_image,
                            COALESCE(p.images, \'\') AS _product_images
                     FROM purchase_order_lines pol
                     LEFT JOIN products p ON p.id = pol.product_id
                     WHERE pol.po_id = ?
                     ORDER BY pol.id ASC',
                    [$poId]
                )->getResultArray();
            }
        } catch (\Throwable $_) {
            // Fallback: plain model read if JOIN fails (e.g. missing columns)
            $rawLines = (new PurchaseOrderLineModel())->where('po_id', $poId)->orderBy('id', 'ASC')->findAll();
        }

        $pdfLines = [];
        foreach ($rawLines as $line) {
            $qty       = (float)($line['qty'] ?? ($line['quantity'] ?? 0));
            $unitPrice = (float)($line['unit_price'] ?? 0);
            $lineTotal = isset($line['line_total']) ? (float)$line['line_total'] : ($qty * $unitPrice);

            $resolvedCode = trim((string)($line['_resolved_code'] ?? ($line['product_code'] ?? '')));
            $resolvedDesc = trim((string)($line['_resolved_desc'] ?? ($line['description'] ?? '')));

            // Resolve image: variant image first, then product image, then product images JSON
            $resolvedImg = '';
            $varImgRaw  = trim((string)($line['_variant_image'] ?? ''));
            $prodImgRaw = trim((string)($line['_product_image'] ?? ''));
            $prodImgsRaw = trim((string)($line['_product_images'] ?? ''));
            $imgRaw = '';
            if ($varImgRaw !== '') {
                $imgRaw = $varImgRaw;
                $imgDir = 'variants';
            } elseif ($prodImgRaw !== '') {
                $imgRaw = $prodImgRaw;
                $imgDir = 'products';
            } elseif ($prodImgsRaw !== '') {
                $arr = json_decode($prodImgsRaw, true);
                if (is_array($arr) && !empty($arr[0])) {
                    $imgRaw = (string)$arr[0];
                    $imgDir = 'products';
                }
            }
            if ($imgRaw !== '') {
                $imgNorm = ltrim((string)$imgRaw, '/\\');
                $candidates = [
                    rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $imgNorm),
                    rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . ($imgDir ?? 'products') . DIRECTORY_SEPARATOR . basename($imgNorm),
                ];
                foreach ($candidates as $abs) {
                    if (is_file($abs)) {
                        $resolvedImg = 'file://' . str_replace('\\', '/', $abs);
                        break;
                    }
                }
            }

            $vid = isset($line['variant_id']) && (int)$line['variant_id'] > 0 ? (int)$line['variant_id'] : null;
            $pid = isset($line['product_id']) && (int)$line['product_id'] > 0 ? (int)$line['product_id'] : null;

            $pdfLines[] = [
                'id'                 => $line['id'] ?? null,
                'product_id'         => $pid,
                'product_variant_id' => $vid,
                'product_code'       => $resolvedCode,
                'product_image_path' => $resolvedImg,
                'description'        => $resolvedDesc,
                'quantity'           => $qty,
                'unit_price'         => $unitPrice,
                'line_total'         => $lineTotal,
            ];
        }

        $vendorParty = [
            'id' => $vendor['vendor_code'] ?? ($vendor['id'] ?? ''),
            'name' => $vendor['name'] ?? 'Vendor',
            'phone' => $vendor['phone'] ?? '',
            'email' => $vendor['email'] ?? '',
        ];
        $vendorAddress = [
            'line1' => trim((string)($vendor['address'] ?? '')),
            'line2' => '',
            'city_name' => '',
            'state_name' => '',
            'postal_code' => '',
        ];

        $company = (new CompanySettingsModel())->orderBy('id', 'DESC')->first() ?: [];
        $payload = [
            'invoice' => [
                'id' => $po['id'] ?? $poId,
                'invoice_number' => $po['po_number'] ?? ('PO-' . $poId),
                'issue_date' => (!empty($po['order_date']) && strpos($po['order_date'], '0000') === false) ? $po['order_date'] : ($po['created_at'] ?? date('Y-m-d')),
                'delivery_date' => $po['delivery_date'] ?? null,
                'subtotal' => (float)($po['subtotal'] ?? 0),
                'tax_total' => (float)($po['tax_total'] ?? 0),
                'total_amount' => (float)($po['total'] ?? 0),
                'currency_code' => $po['currency_code'] ?? ($po['currency'] ?? $this->getDefaultPurchaseCurrency()),
                'status' => $po['status'] ?? 'draft',
            ],
            'lines' => $pdfLines,
            'company' => $company,
            'customer' => $vendorParty,
            'customerAddress' => $vendorAddress,
            'document_title' => 'Purchase Order',
            'document_number_label' => 'PO #',
            'document_date_label' => 'PO Date:',
            'document_prefix' => '',
            'party_label' => 'Vendor',
            'hide_company_logo' => true,
            'hide_company_website' => true,
            'pdf_show_header_address' => (int)($company['pdf_po_show_header'] ?? 1),
            'pdf_show_footer' => (int)($company['pdf_po_show_footer'] ?? 1),
        ];

        $pdf = (new InvoicePdfGenerator())->generateSystemInvoice($payload);
        if (is_array($pdf) && !empty($pdf['path']) && is_file($pdf['path'])) {
            $safeNumber = preg_replace('/[^A-Za-z0-9\-_]/', '_', (string)($po['po_number'] ?? ('PO-' . $poId))) ?: ('PO-' . $poId);
            return $this->response->download($pdf['path'], null)
                ->setFileName('purchase_order_' . $safeNumber . '.pdf')
                ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate')
                ->setHeader('Pragma', 'no-cache')
                ->setHeader('Expires', '0');
        }

        return redirect()->back()->with('error', 'Failed to generate purchase order PDF');
    }

    /**
     * Calculate vendor balance from all bills and payments
     * 
     * Balance = Sum of all confirmed bills - Sum of all confirmed payments
     */
    private function getVendorBalance(?int $vendorId): array
    {
        $balance = 0.0;
        $unpaidAmount = 0.0;

        if (!$vendorId || $vendorId <= 0) {
            return [
                'total_payable' => 0.0,
                'unpaid_bills' => 0.0,
                'last_updated' => date('Y-m-d H:i:s'),
            ];
        }

        try {
            $db = Database::connect();

            // Sum all confirmed vendor bills for this vendor
            $billSum = $db->table('vendor_bills')
                ->selectSum('balance', 'total_balance')
                ->where('vendor_id', $vendorId)
                ->where('status', 'confirmed')
                ->get()
                ->getRowArray();
            $unpaidAmount = (float)($billSum['total_balance'] ?? 0);

            // Also include draft bills in total payable
            $allBillsSum = $db->table('vendor_bills')
                ->selectSum('total_amount', 'total_amount')
                ->where('vendor_id', $vendorId)
                ->whereIn('status', ['confirmed', 'draft'])
                ->get()
                ->getRowArray();
            $balance = (float)($allBillsSum['total_amount'] ?? 0);

        } catch (\Throwable $e) {
            log_message('warning', 'getVendorBalance failed: ' . $e->getMessage());
        }

        return [
            'total_payable' => $balance,
            'unpaid_bills' => $unpaidAmount,
            'last_updated' => date('Y-m-d H:i:s'),
        ];
    }

    // Update a PO and its lines (editable from UI)
    public function update($id = null)
    {
        $payload = $this->request->getJSON(true) ?: $this->request->getPost();
        $poModel = new PurchaseOrderModel();
        $po = $poModel->findByPublicIdOrId($id);
        if (!$po) return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'PO not found']);
        $poId = (int)$po['id'];

        if (!empty($payload['lines']) && is_array($payload['lines'])) {
            $variantIssues = $this->validateVariantSelections($payload['lines']);
            if (!empty($variantIssues['missing']) || !empty($variantIssues['invalid'])) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'error' => $this->buildVariantValidationMessage($variantIssues),
                ]);
            }
        }

        $db = Database::connect();
        $db->transBegin();
        try {
            // Prepare allowed PO header updates
            $update = [];
            if (isset($payload['po_number'])) $update['po_number'] = $payload['po_number'];
            if (isset($payload['vendor_id'])) $update['vendor_id'] = (int)$payload['vendor_id'];
            if (isset($payload['status'])) $update['status'] = $payload['status'];
            if (isset($payload['subtotal'])) $update['subtotal'] = $payload['subtotal'];
            if (isset($payload['total'])) $update['total'] = $payload['total'];
            $update['updated_at'] = date('Y-m-d H:i:s');

            // Filter to actual DB columns
            try { $cols = $db->getFieldNames('purchase_orders'); } catch (\Throwable $e) { $cols = null; }
            if (is_array($cols)) {
                foreach ($update as $k => $v) { if (! in_array($k, $cols)) unset($update[$k]); }
            }

            if (!empty($update)) $poModel->update($poId, $update);

            // Replace lines if provided
            if (!empty($payload['lines']) && is_array($payload['lines'])) {
                $lineModel = new PurchaseOrderLineModel();
                // delete existing lines
                $lineModel->where('po_id', $poId)->delete();
                foreach ($payload['lines'] as $ln) {
                    $qty = isset($ln['qty']) ? (float)$ln['qty'] : (isset($ln['quantity']) ? (float)$ln['quantity'] : 0);
                    if ($qty <= 0) continue;
                    $lineModel->insert([
                        'po_id' => $poId,
                        'product_id' => isset($ln['product_id']) ? (int)$ln['product_id'] : null,
                        'variant_id' => isset($ln['variant_id']) ? (int)$ln['variant_id'] : (isset($ln['product_variant_id']) ? (int)$ln['product_variant_id'] : null),
                        'description' => $ln['description'] ?? null,
                        'qty' => $qty,
                        'unit_price' => isset($ln['unit_price']) ? (float)$ln['unit_price'] : (isset($ln['unit_cost']) ? (float)$ln['unit_cost'] : null),
                        'qty_received' => $ln['qty_received'] ?? 0,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }

            $db->transCommit();
            DocumentLogger::log(DocumentLogger::TYPE_PURCHASE_ORDER, $poId, DocumentLogger::ACTION_UPDATED);
            return $this->response->setJSON(['success' => true]);
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'PO update failed: '.$e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => 'Failed to update PO']);
        }
    }

    // Cancel a PO (soft cancel)
    public function cancel($id = null)
    {
        $payload = $this->request->getJSON(true) ?: $this->request->getPost();
        $reason = $payload['reason'] ?? null;
        $poModel = new PurchaseOrderModel();
        $po = $poModel->findByPublicIdOrId($id);
        if (!$po) return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'PO not found']);
        $poId = (int)$po['id'];

        $poModel->update($poId, ['status' => 'cancelled', 'cancel_reason' => $reason, 'cancelled_at' => date('Y-m-d H:i:s'), 'cancelled_by' => session()->get('user_id')?:null, 'updated_at' => date('Y-m-d H:i:s')]);
        DocumentLogger::log(DocumentLogger::TYPE_PURCHASE_ORDER, $poId, DocumentLogger::ACTION_CANCELLED,
            $reason ? ['reason' => $reason] : []);
        return $this->response->setJSON(['success' => false]);
    }

    /**
     * Force close a PO (prevent further receipts even if pending quantities exist)
     * 
     * This allows manually closing a PO when:
     * - Vendor cannot deliver remaining quantities
     * - Business decides to accept partial delivery
     * - Need to write off pending items
     */
    public function close($id = null)
    {
        $poModel = new PurchaseOrderModel();
        $po = $poModel->findByPublicIdOrId($id);
        
        if (!$po) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false, 
                'error' => 'PO not found'
            ]);
        }
        $poId = (int)$po['id'];
        if ($po['status'] === 'cancelled') {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false, 
                'error' => 'Cannot close a cancelled PO'
            ]);
        }

        // Use the PurchaseOrderStatusService to force close
        $statusService = new \App\Services\PurchaseOrderStatusService();
        $result = $statusService->forceClose($poId);

        if ($result) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'PO closed successfully'
            ]);
        } else {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error' => 'Failed to close PO'
            ]);
        }
    }

    /**
     * POST new-purchase-orders/{id}/delete
     * Deletes a draft/pending PO and its lines.
     */
    public function delete($id = null)
    {
        $poId = (int)$id;
        if ($poId <= 0) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'error'   => 'Invalid PO id',
            ]);
        }

        $poModel = new PurchaseOrderModel();
        $po = $poModel->find($poId);
        if (!$po) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'error'   => 'PO not found',
            ]);
        }

        $status = strtolower((string)($po['status'] ?? ''));
        if (!in_array($status, ['draft', 'pending'], true)) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'error'   => 'Only draft or pending POs can be deleted',
            ]);
        }

        $db = Database::connect();
        $db->transBegin();
        try {
            $lineModel = new PurchaseOrderLineModel();
            $lineModel->where('po_id', $poId)->delete();
            $poModel->delete($poId);
            $db->transCommit();

            return $this->response->setJSON([
                'success' => true,
                'message' => 'PO deleted successfully',
            ]);
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'PO delete failed: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error'   => 'Failed to delete PO',
            ]);
        }
    }

    /**
     * Pay Advance from Purchase Order
     * 
     * STRICT RULES:
     * - Only allowed if PO status = 'confirmed'
     * - Total advance payments cannot exceed PO total
     * - Creates vendor_payment with payment_type = 'advance'
     * - Posts to Vendor Advance account (NOT Accounts Payable)
     * - Does NOT modify PO total or status
     * - Does NOT affect stock
     * 
     * @param int $poId
     * @return Response JSON
     */
    public function payAdvance($poId = null)
    {
        $poId = (int)$poId;
        if ($poId <= 0) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'error' => 'Invalid PO ID'
            ]);
        }

        if ($this->request->getMethod() !== 'post') {
            return $this->response->setStatusCode(405)->setJSON([
                'success' => false,
                'error' => 'Method not allowed'
            ]);
        }

        $payload = $this->request->getJSON(true) ?: $this->request->getPost();
        $amount = isset($payload['amount']) ? (float)$payload['amount'] : 0;
        $paymentMethod = $payload['payment_method'] ?? 'cash';
        $sourceAccountId = isset($payload['source_account_id']) ? (int)$payload['source_account_id'] : null;
        $memo = $payload['memo'] ?? null;
        $notes = $payload['notes'] ?? null;

        $db = Database::connect();
        $db->transBegin();

        try {
            // 1. Validate PO exists and is confirmed
            $poModel = new PurchaseOrderModel();
            $po = $poModel->find($poId);
            
            if (!$po) {
                throw new \RuntimeException('Purchase Order not found');
            }

            if ($po['status'] !== 'confirmed') {
                throw new \RuntimeException('Advance payment only allowed for confirmed POs (current status: ' . ($po['status'] ?? 'unknown') . ')');
            }

            $poTotal = (float)($po['total'] ?? 0);
            if ($poTotal <= 0) {
                throw new \RuntimeException('PO total is zero or invalid');
            }

            // 2. Calculate total advances already paid for this PO
            $paymentModel = new \App\Models\VendorPaymentModel();
            $existingPayments = $paymentModel
                ->where('po_id', $poId)
                ->where('payment_type', 'advance')
                ->whereIn('status', ['pending', 'posted'])
                ->findAll();

            $totalPaidAdvance = 0;
            foreach ($existingPayments as $payment) {
                $totalPaidAdvance += (float)($payment['amount'] ?? 0);
            }

            // 3. Validate advance amount
            if ($amount <= 0) {
                throw new \RuntimeException('Advance amount must be greater than zero');
            }

            $remainingPayable = $poTotal - $totalPaidAdvance;
            if ($amount > $remainingPayable) {
                throw new \RuntimeException(sprintf(
                    'Advance amount (%.2f) exceeds remaining payable (%.2f). PO Total: %.2f, Already Paid: %.2f',
                    $amount,
                    $remainingPayable,
                    $poTotal,
                    $totalPaidAdvance
                ));
            }

            // 4. Validate payment method and source account
            if (!in_array($paymentMethod, ['cash', 'bank', 'cheque', 'online_transfer'], true)) {
                throw new \RuntimeException('Invalid payment method');
            }

            if (in_array($paymentMethod, ['cash', 'bank', 'online_transfer']) && !$sourceAccountId) {
                throw new \RuntimeException('Source account is required for ' . $paymentMethod . ' payments');
            }

            // 5. Insert vendor_payment record
            $paymentData = [
                'vendor_id' => (int)$po['vendor_id'],
                'po_id' => $poId,
                'payment_date' => date('Y-m-d'),
                'payment_method' => $paymentMethod,
                'payment_type' => 'advance',
                'currency_code' => $po['currency'] ?? 'PKR',
                'amount' => $amount,
                'source_account_id' => $sourceAccountId,
                'memo' => $memo ?? 'Advance payment for PO #' . ($po['po_number'] ?? $poId),
                'notes' => $notes,
                'status' => 'pending', // Will be 'posted' after accounting entry
                'created_by' => session()->get('user_id') ?: null,
                'created_at' => date('Y-m-d H:i:s'),
            ];

            $paymentId = $paymentModel->insert($paymentData, true);
            if (!$paymentId) {
                throw new \RuntimeException('Failed to create payment record: ' . json_encode($paymentModel->errors()));
            }

            // 6. Post accounting entry (if AccountingPostingService available)
            $posted = false;
            if (class_exists('App\\Services\\AccountingPostingService')) {
                $accountingService = new \App\Services\AccountingPostingService();
                $postResult = $accountingService->postVendorPayment($paymentId);
                
                if ($postResult['success']) {
                    $posted = true;
                    log_message('info', 'PO advance payment posted: payment_id=' . $paymentId . ', je_id=' . ($postResult['journal_entry_id'] ?? 'N/A'));
                } else {
                    log_message('warning', 'PO advance payment created but not posted: ' . ($postResult['message'] ?? 'Unknown error'));
                }
            }

            $db->transCommit();

            return $this->response->setJSON([
                'success' => true,
                'payment_id' => $paymentId,
                'posted' => $posted,
                'message' => sprintf(
                    'Advance payment of %.2f recorded successfully. Total advance paid: %.2f / %.2f',
                    $amount,
                    $totalPaidAdvance + $amount,
                    $poTotal
                ),
                'total_advance_paid' => $totalPaidAdvance + $amount,
                'po_total' => $poTotal,
                'remaining_payable' => $remainingPayable - $amount,
            ]);

        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'PO advance payment failed: po_id=' . $poId . ', error=' . $e->getMessage());
            
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get Advance Payment Info for a PO
     * 
     * Returns summary of advance payments for display on PO page
     * 
     * @param int $id PO ID
     * @return Response JSON
     */
    public function getAdvanceInfo($id = null)
    {
        $poId = (int)$id;
        if ($poId <= 0) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'error' => 'Invalid PO ID'
            ]);
        }

        try {
            $poModel = new PurchaseOrderModel();
            $po = $poModel->find($poId);
            
            if (!$po) {
                return $this->response->setStatusCode(404)->setJSON([
                    'success' => false,
                    'error' => 'PO not found'
                ]);
            }

            $poTotal = (float)($po['total'] ?? 0);

            // Get all advance payments for this PO
            $db = Database::connect();
            $payments = $db->table('vendor_payments')
                ->where('po_id', $poId)
                ->where('payment_type', 'advance')
                ->where('status !=', 'cancelled')
                ->orderBy('payment_date', 'DESC')
                ->get()
                ->getResultArray();

            $totalPaid = 0;
            $paymentList = [];

            foreach ($payments as $payment) {
                $amount = (float)($payment['amount'] ?? 0);
                $totalPaid += $amount;
                
                $paymentList[] = [
                    'id' => $payment['id'],
                    'payment_date' => $payment['payment_date'] ?? null,
                    'amount' => $amount,
                    'payment_method' => $payment['payment_method'] ?? 'Unknown',
                    'status' => $payment['status'] ?? 'pending',
                    'reference_no' => $payment['reference_no'] ?? null,
                    'created_at' => $payment['created_at'] ?? null,
                ];
            }

            $remainingPayable = $poTotal - $totalPaid;
            $advancePercentage = $poTotal > 0 ? ($totalPaid / $poTotal) * 100 : 0;

            return $this->response->setJSON([
                'success' => true,
                'po_id' => $poId,
                'po_total' => $poTotal,
                'total_advance_paid' => $totalPaid,
                'remaining_payable' => $remainingPayable,
                'advance_percentage' => round($advancePercentage, 2),
                'can_pay_advance' => ($po['status'] === 'confirmed' && $remainingPayable > 0),
                'payments' => $paymentList,
                'currency' => $po['currency'] ?? 'PKR',
            ]);

        } catch (\Throwable $e) {
            log_message('error', 'Get advance info failed: po_id=' . $poId . ', error=' . $e->getMessage());
            
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error' => 'Failed to retrieve advance payment information'
            ]);
        }
    }

    /**
     * Create a vendor bill from a purchase order
     * 
     * Visible only when PO status = confirmed or partial
     * Calculates bill amount based on received qty or full qty
     * Creates bill header and line items
     */
    public function createBill($poId = null)
    {
        $poId = (int)$poId;
        if ($poId <= 0) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'Invalid PO id']);
        }

        $db = Database::connect();
        $poModel = new PurchaseOrderModel();
        $po = $poModel->find($poId);
        if (!$po) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'PO not found']);
        }

        // Check PO status - allow active and receipt-derived statuses used across environments
        $poStatus = strtolower($po['status'] ?? '');
        if (!in_array($poStatus, ['confirmed', 'partial_received', 'partial', 'completed', 'open', 'received'], true)) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false, 
                'error' => 'Bill can only be created from active PO statuses (confirmed/partial/completed/open)'
            ]);
        }

        $billModel = new \App\Models\VendorBillModel();
        $processingRecordId = (int) ($po['processing_record_id'] ?? 0);
        $processingBillingService = new ProcessingBillingValidationService();

        $db->transBegin();
        try {
            // Serialize bill creation per PO to prevent duplicate billing under concurrent requests.
            $db->query('SELECT id FROM purchase_order_lines WHERE po_id = ? FOR UPDATE', [$poId]);

            // Load PO lines
            $lineModel = new PurchaseOrderLineModel();
            $poLines = $lineModel->where('po_id', $poId)->findAll();
            if (empty($poLines)) {
                throw new \RuntimeException('No lines found in PO');
            }

            // Calculate bill amount and prepare line data.
            // If previous bills already exist, create only for remaining unbilled qty.
            $totalAmount = 0.0;
            $billLines = [];

            $billedQtyByPoLine = $this->getBaseBilledQtyByPoLine($db, $poId);

            foreach ($poLines as $line) {
                $qty = isset($line['qty']) ? (float)$line['qty'] : 0;
                $qtyReceived = isset($line['qty_received']) ? (float)$line['qty_received'] : 0;
                $unitPrice = isset($line['unit_price']) ? (float)$line['unit_price'] : 0;

                // Base bill only covers ordered receipts. Payable over-receipts are billed separately.
                $basePayableQty = min($qtyReceived, $qty);
                $sourceQty = max(0.0, round($basePayableQty, 4));
                $alreadyBilledQty = (float)($billedQtyByPoLine[(int)($line['id'] ?? 0)] ?? 0);
                $billQty = max(0.0, round($sourceQty - $alreadyBilledQty, 4));
                if ($billQty <= 0.0001) {
                    continue;
                }

                $lineTotal = $billQty * $unitPrice;
                $totalAmount += $lineTotal;

                $billLines[] = [
                    'po_line_id' => $line['id'],
                    'processing_record_id' => $processingRecordId > 0 ? $processingRecordId : null,
                    'product_id' => $line['product_id'] ?? null,
                    'variant_id' => $line['variant_id'] ?? null,
                    'qty' => $billQty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            if ($totalAmount <= 0 || empty($billLines)) {
                $latestBill = $billModel->where('po_id', $poId)->where('status !=', 'cancelled')->orderBy('id', 'DESC')->first();
                return $this->response->setJSON([
                    'success' => true,
                    'bill_id' => (int)($latestBill['id'] ?? 0),
                    'message' => 'No remaining unbilled quantity for this PO.',
                ]);
            }

            if ($processingRecordId > 0) {
                $proposedQty = 0.0;
                foreach ($billLines as $billLine) {
                    $proposedQty += (float) ($billLine['qty'] ?? 0);
                }

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

            // Validate bill amount does not exceed PO total
            $poTotal = $this->calculateOrderedPoTotal($poLines);
            if ($totalAmount > $poTotal && $poTotal > 0) {
                throw new \RuntimeException('Bill amount (' . $totalAmount . ') exceeds PO total (' . $poTotal . ')');
            }

            // Create vendor bill
            $currency = strtoupper(trim((string)($po['currency'] ?? 'PKR')));
            if ($currency === '') {
                $currency = 'PKR';
            }

            $billData = [
                'vendor_id' => $po['vendor_id'] ?? null,
                'po_id' => $poId,
                'bill_date' => date('Y-m-d'),
                'total_amount' => $totalAmount,
                'balance' => $totalAmount,
                'status' => 'draft',
                'based_on' => !empty($billedQtyByPoLine) ? 'po_qty_adjustment' : 'po_qty',
                'currency_code' => $currency,
                'created_by' => session()->get('user_id') ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ];

            $billId = $billModel->insert($billData, true);
            if (!$billId) {
                throw new \RuntimeException('Failed to create vendor bill: ' . json_encode($billModel->errors()));
            }

            // Create bill lines
            $billLineModel = new \App\Models\VendorBillLineModel();
            foreach ($billLines as $billLine) {
                $billLine['vendor_bill_id'] = $billId;
                $billLine['created_at'] = date('Y-m-d H:i:s');
                $billLineModel->insert($billLine);
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('DB transaction failed');
            }

            $extraReceiptData = $this->getBillableExtraReceiptData($db, $poId);
            $extraBillAvailable = !empty($extraReceiptData['lines']);

            $db->transCommit();
            log_message('info', 'Vendor bill created: bill_id=' . $billId . ', po_id=' . $poId . ', amount=' . $totalAmount);

            return $this->response->setJSON([
                'success' => true,
                'bill_id' => $billId,
                'extra_bill_available' => $extraBillAvailable,
                'message' => 'Vendor bill created successfully',
            ]);
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Failed to create vendor bill: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function createExtraBill($poId = null)
    {
        $poId = (int)$poId;
        if ($poId <= 0) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'Invalid PO id']);
        }

        $db = Database::connect();
        $poModel = new PurchaseOrderModel();
        $po = $poModel->find($poId);
        if (!$po) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'PO not found']);
        }

        $db->transBegin();
        try {
            $db->query('SELECT id FROM purchase_order_lines WHERE po_id = ? FOR UPDATE', [$poId]);
            $extraReceiptData = $this->getBillableExtraReceiptData($db, $poId);
            if (empty($extraReceiptData['lines'])) {
                $latestBill = $db->table('vendor_bills')
                    ->select('id')
                    ->where('po_id', $poId)
                    ->where('based_on', 'po_over_receipt')
                    ->where('status !=', 'cancelled')
                    ->orderBy('id', 'DESC')
                    ->get()
                    ->getRowArray();
                $db->transCommit();
                return $this->response->setJSON([
                    'success' => true,
                    'bill_id' => (int)($latestBill['id'] ?? 0),
                    'message' => 'No remaining unbilled extra quantity for this PO.',
                ]);
            }

            $billId = $this->createPoOverReceiptBill($db, $po, $poId, $extraReceiptData['lines']);

            $db->transCommit();
            return $this->response->setJSON([
                'success' => true,
                'bill_id' => $billId,
                'message' => 'Extra vendor bill created successfully',
            ]);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

}


