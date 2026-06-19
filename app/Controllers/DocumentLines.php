<?php

namespace App\Controllers;

use App\Libraries\RoleDataAccess;
use App\Models\PurchaseOrderLineModel;
use App\Models\PurchaseOrderModel;
use App\Models\PurchaseRfqLineModel;
use App\Models\PurchaseRfqModel;
use App\Models\CustomerInvoiceLineModel;
use App\Models\CustomerInvoiceModel;
use App\Models\ProductModel;
use App\Models\ProductVariantModel;
use App\Models\QuotationLineModel;
use App\Models\QuotationModel;
use App\Models\SalesOrderLineModel;
use App\Models\SalesOrderModel;
use Config\Database;

class DocumentLines extends BaseController
{
    private function configByType(string $docType): ?array
    {
        $map = [
            'quotation' => [
                'doc_table' => 'quotations',
                'line_table' => 'quotation_lines',
                'doc_fk' => 'quotation_id',
                'doc_number' => 'quote_number',
                'doc_public' => 'public_id',
                'line_model' => new QuotationLineModel(),
                'doc_model' => new QuotationModel(),
                'isolate_key' => 'isolate_quotations',
            ],
            'sales_order' => [
                'doc_table' => 'sales_orders',
                'line_table' => 'sales_order_lines',
                'doc_fk' => 'sales_order_id',
                'doc_number' => 'order_number',
                'doc_public' => 'public_id',
                'line_model' => new SalesOrderLineModel(),
                'doc_model' => new SalesOrderModel(),
                'isolate_key' => 'isolate_sales_orders',
            ],
            'purchase_order' => [
                'doc_table' => 'purchase_orders',
                'line_table' => 'purchase_order_lines',
                'doc_fk' => 'po_id',
                'doc_number' => 'po_number',
                'doc_public' => 'public_id',
                'line_model' => new PurchaseOrderLineModel(),
                'doc_model' => new PurchaseOrderModel(),
                'isolate_key' => 'isolate_purchase_orders',
            ],
            'purchase_rfq' => [
                'doc_table' => 'purchase_rfqs',
                'line_table' => 'purchase_rfq_lines',
                'doc_fk' => 'rfq_id',
                'doc_number' => 'rfq_number',
                'doc_public' => 'public_id',
                'line_model' => new PurchaseRfqLineModel(),
                'doc_model' => new PurchaseRfqModel(),
                'isolate_key' => 'isolate_purchase_rfqs',
            ],
            'customer_invoice' => [
                'doc_table' => 'customer_invoices',
                'line_table' => 'customer_invoice_lines',
                'doc_fk' => 'invoice_id',
                'doc_number' => 'invoice_number',
                'doc_public' => 'public_id',
                'line_model' => new CustomerInvoiceLineModel(),
                'doc_model' => new CustomerInvoiceModel(),
                'isolate_key' => 'isolate_sales_orders',
            ],
        ];

        return $map[$docType] ?? null;
    }

    private function ensureLineSchema(string $table): void
    {
        $db = Database::connect();
        if (!$db->tableExists($table)) {
            return;
        }

        try {
            if (!$db->fieldExists('display_type', $table)) {
                $db->query("ALTER TABLE `{$table}` ADD COLUMN `display_type` VARCHAR(20) NOT NULL DEFAULT 'line'");
            }
        } catch (\Throwable $_) {}

        try {
            if (!$db->fieldExists('section_title', $table)) {
                $db->query("ALTER TABLE `{$table}` ADD COLUMN `section_title` VARCHAR(255) NULL");
            }
        } catch (\Throwable $_) {}

        try {
            if (!$db->fieldExists('sort_order', $table)) {
                $db->query("ALTER TABLE `{$table}` ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0");
                $db->query("UPDATE `{$table}` SET sort_order = id WHERE sort_order = 0 OR sort_order IS NULL");
            }
        } catch (\Throwable $_) {}

        try {
            if (!$db->fieldExists('updated_at', $table)) {
                $db->query("ALTER TABLE `{$table}` ADD COLUMN `updated_at` DATETIME NULL");
            }
        } catch (\Throwable $_) {}
    }

    private function resolveDocument(string $docType, string $identifier): ?array
    {
        $cfg = $this->configByType($docType);
        if (!$cfg) return null;

        $model = $cfg['doc_model'];
        if (method_exists($model, 'findByPublicIdOrId')) {
            $doc = $model->findByPublicIdOrId($identifier);
            return is_array($doc) ? $doc : null;
        }

        if (is_numeric($identifier)) {
            $doc = $model->find((int)$identifier);
            if ($doc) return $doc;
        }

        $db = Database::connect();
        if ($db->fieldExists($cfg['doc_public'], $cfg['doc_table'])) {
            $row = $db->table($cfg['doc_table'])->where($cfg['doc_public'], $identifier)->get()->getRowArray();
            if ($row) return $row;
        }

        return null;
    }

    private function authorizeDocument(string $docType, array $doc): bool
    {
        $db = Database::connect();
        $cfg = $this->configByType($docType);
        if (!$cfg) return false;

        $userId = (int)(session()->get('user_id') ?? 0);
        if ($userId <= 0) {
            return false;
        }

        $isAdmin = service('policy')->isAdmin();
        if ($isAdmin) {
            return true;
        }

        if ($db->fieldExists('created_by', $cfg['doc_table'])) {
            $ownerId = (int)($doc['created_by'] ?? 0);
            if ($ownerId > 0 && $ownerId !== $userId) {
                $rda = new RoleDataAccess();
                $access = $rda->resolveForUser($userId);
                if (!empty($access[$cfg['isolate_key']])) {
                    return false;
                }

                $privateUserIds = $rda->getPrivateUserIds($userId, false);
                if (in_array($ownerId, $privateUserIds, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function lineType(array $line): string
    {
        $type = strtolower((string)($line['display_type'] ?? 'line'));
        return $type === 'section' ? 'section' : 'line';
    }

    private function getLines(string $docType, int $docId): array
    {
        $cfg = $this->configByType($docType);
        if (!$cfg) return [];

        $lineModel = $cfg['line_model'];
        $lines = $lineModel
            ->where($cfg['doc_fk'], $docId)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();

        if (empty($lines)) return [];

        // Section feature is deprecated: only return product lines for all document types.
        $lines = array_values(array_filter($lines, function ($ln) {
            return strtolower((string)($ln['display_type'] ?? 'line')) !== 'section';
        }));
        if (empty($lines)) return [];

        $productIds = [];
        $variantIds = [];
        foreach ($lines as $ln) {
            if (!empty($ln['product_id'])) $productIds[] = (int)$ln['product_id'];
            if (!empty($ln['product_variant_id'])) $variantIds[] = (int)$ln['product_variant_id'];
            if (!empty($ln['variant_id'])) $variantIds[] = (int)$ln['variant_id'];
        }
        $productIds = array_values(array_unique(array_filter($productIds)));
        $variantIds = array_values(array_unique(array_filter($variantIds)));

        $products = [];
        $variants = [];
        if (!empty($productIds)) {
            foreach ((new ProductModel())->whereIn('id', $productIds)->findAll() as $p) {
                $products[(int)$p['id']] = $p;
            }
        }
        if (!empty($variantIds)) {
            foreach ((new ProductVariantModel())->whereIn('id', $variantIds)->findAll() as $v) {
                $variants[(int)$v['id']] = $v;
            }
        }

        foreach ($lines as &$ln) {
            $pid = (int)($ln['product_id'] ?? 0);
            if ($pid > 0 && isset($products[$pid])) {
                $p = $products[$pid];
                if (empty($ln['product_name'])) $ln['product_name'] = $p['name'] ?? '';
                if (empty($ln['product_code'])) $ln['product_code'] = $p['code'] ?? ($p['sku'] ?? '');
                if (empty($ln['unit'])) $ln['unit'] = $p['unit'] ?? 'pcs';
                if (empty($ln['product_image_url'])) {
                    $imgUrl = '';
                    if (!empty($p['image'])) {
                        $imgUrl = base_url('/uploads/products/' . ltrim((string)$p['image'], '/'));
                    } elseif (!empty($p['images'])) {
                        $imgs = is_string($p['images']) ? json_decode($p['images'], true) : $p['images'];
                        if (is_array($imgs) && !empty($imgs[0])) {
                            $imgUrl = base_url('/uploads/products/' . ltrim((string)$imgs[0], '/'));
                        }
                    }
                    if ($imgUrl !== '') {
                        $ln['product_image_url'] = $imgUrl;
                    }
                }
            }

            $vid = (int)($ln['product_variant_id'] ?? ($ln['variant_id'] ?? 0));
            if ($vid > 0 && isset($variants[$vid])) {
                $v = $variants[$vid];
                if (!empty($v['art_number'])) $ln['product_code'] = $v['art_number'];
                $ln['variant_name'] = $v['name'] ?? ($ln['variant_name'] ?? '');
                if (empty($ln['variant_image_url']) && !empty($v['image'])) {
                    $ln['variant_image_url'] = base_url('/uploads/variants/' . ltrim((string)$v['image'], '/'));
                }
                if (empty($ln['product_image_url']) && !empty($ln['variant_image_url'])) {
                    $ln['product_image_url'] = $ln['variant_image_url'];
                }
            }

            if (empty($ln['product_image_url'])) {
                $ln['product_image_url'] = base_url('assets/images/no-image.png');
            }

        }
        unset($ln);

        return $lines;
    }

    private function normalizeUpdatedAt($v): string
    {
        $s = trim((string)$v);
        if ($s === '' || $s === '0000-00-00 00:00:00') return '';
        return $s;
    }

    private function checkConflict(array $currentLines, array $clientVersionMap): ?string
    {
        foreach ($currentLines as $ln) {
            $id = (int)($ln['id'] ?? 0);
            if ($id <= 0) continue;
            if (!array_key_exists((string)$id, $clientVersionMap)) continue;
            $client = $this->normalizeUpdatedAt($clientVersionMap[(string)$id]);
            $server = $this->normalizeUpdatedAt($ln['updated_at'] ?? '');
            if ($client !== $server) {
                return 'Line conflict detected. Please refresh before applying changes.';
            }
        }
        return null;
    }

    private function computeLineAmount(string $docType, array $line): float
    {
        if ($this->lineType($line) === 'section') return 0.0;

        if ($docType === 'quotation' || $docType === 'sales_order') {
            if (isset($line['line_total'])) {
                return (float)$line['line_total'];
            }
            $qty = (float)($line['quantity'] ?? 0);
            $price = (float)($line['unit_price'] ?? 0);
            $disc = (float)($line['discount_amount'] ?? 0);
            $tax = (float)($line['tax_amount'] ?? 0);
            return max(0.0, ($qty * $price) - $disc + $tax);
        }

        $qty = (float)($line['qty'] ?? ($line['quantity'] ?? 0));
        $price = (float)($line['unit_price'] ?? ($line['unit_cost'] ?? 0));
        $disc = (float)($line['discount_amount'] ?? 0);
        $tax = (float)($line['tax_amount'] ?? 0);
        return max(0.0, ($qty * $price) - $disc + $tax);
    }

    private function buildSectionSummary(array $lines, string $docType): array
    {
        $sectionSubtotalBySectionId = [];
        $lineSectionMap = [];
        $currentSectionId = 0;
        $grandTotal = 0.0;

        foreach ($lines as $ln) {
            $lineId = (int)($ln['id'] ?? 0);
            if ($lineId <= 0) continue;
            if ($this->lineType($ln) === 'section') {
                $currentSectionId = $lineId;
                $sectionSubtotalBySectionId[$currentSectionId] = 0.0;
                $lineSectionMap[$lineId] = 0;
                continue;
            }

            $lineSectionMap[$lineId] = $currentSectionId;
            $amt = $this->computeLineAmount($docType, $ln);
            $grandTotal += $amt;
            if ($currentSectionId > 0) {
                if (!isset($sectionSubtotalBySectionId[$currentSectionId])) {
                    $sectionSubtotalBySectionId[$currentSectionId] = 0.0;
                }
                $sectionSubtotalBySectionId[$currentSectionId] += $amt;
            }
        }

        return [
            'section_subtotals' => $sectionSubtotalBySectionId,
            'line_section_map' => $lineSectionMap,
            'grand_total' => round($grandTotal, 2),
        ];
    }

    private function recalcDocumentTotals(string $docType, int $docId): void
    {
        if ($docType === 'customer_invoice') {
            return;
        }

        $cfg = $this->configByType($docType);
        if (!$cfg) return;

        $lines = $this->getLines($docType, $docId);
        $sum = $this->buildSectionSummary($lines, $docType);
        $grand = (float)$sum['grand_total'];

        $db = Database::connect();
        $table = $cfg['doc_table'];
        $builder = $db->table($table);
        $cols = $db->getFieldNames($table);
        $upd = [];

        if (in_array('total', $cols, true)) $upd['total'] = $grand;
        if (in_array('grand_total', $cols, true)) $upd['grand_total'] = $grand;
        if (in_array('updated_at', $cols, true)) $upd['updated_at'] = date('Y-m-d H:i:s');

        if (!empty($upd)) {
            $builder->where('id', $docId)->update($upd);
        }
    }

    private function renderRows(string $docType, array $lines): string
    {
        $summary = $this->buildSectionSummary($lines, $docType);
        return (string)view('partials/document_lines/rows', [
            'docType' => $docType,
            'lines' => $lines,
            'sectionSubtotals' => $summary['section_subtotals'],
        ]);
    }

    private function orderSignature(array $line): string
    {
        $pid = (int)($line['product_id'] ?? 0);
        $vid = (int)($line['product_variant_id'] ?? ($line['variant_id'] ?? 0));
        $code = strtolower(trim((string)($line['product_code'] ?? '')));
        $desc = strtolower(trim((string)($line['description'] ?? ($line['product_name'] ?? ''))));
        $qty = (float)($line['quantity'] ?? ($line['qty'] ?? 0));
        $unitPrice = (float)($line['unit_price'] ?? ($line['unit_cost'] ?? 0));

        return implode('|', [
            (string)$pid,
            (string)$vid,
            $code,
            $desc,
            number_format($qty, 4, '.', ''),
            number_format($unitPrice, 4, '.', ''),
        ]);
    }

    private function orderedIdsForTargetBySource(array $sourceLines, array $targetLines): array
    {
        $targetBuckets = [];
        foreach ($targetLines as $tl) {
            $tid = (int)($tl['id'] ?? 0);
            if ($tid <= 0) continue;
            $sig = $this->orderSignature($tl);
            if (!isset($targetBuckets[$sig])) {
                $targetBuckets[$sig] = [];
            }
            $targetBuckets[$sig][] = $tid;
        }

        $ordered = [];
        foreach ($sourceLines as $sl) {
            $sig = $this->orderSignature($sl);
            if (!empty($targetBuckets[$sig])) {
                $ordered[] = array_shift($targetBuckets[$sig]);
            }
        }

        // Keep any unmatched target rows at the end in existing relative order.
        foreach ($targetLines as $tl) {
            $tid = (int)($tl['id'] ?? 0);
            if ($tid > 0 && !in_array($tid, $ordered, true)) {
                $ordered[] = $tid;
            }
        }

        return $ordered;
    }

    private function applyOrderedIdsToDoc(string $targetDocType, int $targetDocId, array $orderedIds): void
    {
        if ($targetDocId <= 0 || empty($orderedIds)) {
            return;
        }

        $cfg = $this->configByType($targetDocType);
        if (!$cfg) {
            return;
        }

        $db = Database::connect();
        $builder = $db->table($cfg['line_table']);
        $now = date('Y-m-d H:i:s');

        $db->transBegin();
        try {
            $sort = 1;
            foreach ($orderedIds as $lineId) {
                $lineId = (int)$lineId;
                if ($lineId <= 0) continue;
                $builder->where('id', $lineId)->where($cfg['doc_fk'], $targetDocId)->update([
                    'sort_order' => $sort,
                    'updated_at' => $now,
                ]);
                $sort++;
            }
            $db->transCommit();
        } catch (\Throwable $_) {
            $db->transRollback();
        }
    }

    private function syncLinkedDocumentOrder(string $sourceDocType, int $sourceDocId): void
    {
        if ($sourceDocId <= 0) {
            return;
        }

        $db = Database::connect();
        $sourceLines = $this->getLines($sourceDocType, $sourceDocId);
        if (empty($sourceLines)) {
            return;
        }

        if ($sourceDocType === 'sales_order') {
            $targets = $db->table('customer_invoices')->select('id')->where('sales_order_id', $sourceDocId)->get()->getResultArray();
            foreach ($targets as $t) {
                $tid = (int)($t['id'] ?? 0);
                if ($tid <= 0) continue;
                $targetLines = $this->getLines('customer_invoice', $tid);
                $orderedIds = $this->orderedIdsForTargetBySource($sourceLines, $targetLines);
                $this->applyOrderedIdsToDoc('customer_invoice', $tid, $orderedIds);
            }
            return;
        }

        if ($sourceDocType === 'customer_invoice') {
            $inv = $db->table('customer_invoices')->select('id,sales_order_id')->where('id', $sourceDocId)->get()->getRowArray();
            $salesOrderId = (int)($inv['sales_order_id'] ?? 0);
            if ($salesOrderId <= 0) return;

            $soLines = $this->getLines('sales_order', $salesOrderId);
            $orderedIdsSo = $this->orderedIdsForTargetBySource($sourceLines, $soLines);
            $this->applyOrderedIdsToDoc('sales_order', $salesOrderId, $orderedIdsSo);

            // Fan out to all invoices linked to this SO to keep everything consistent.
            $targets = $db->table('customer_invoices')->select('id')->where('sales_order_id', $salesOrderId)->get()->getResultArray();
            $soLinesAfter = $this->getLines('sales_order', $salesOrderId);
            foreach ($targets as $t) {
                $tid = (int)($t['id'] ?? 0);
                if ($tid <= 0) continue;
                $targetLines = $this->getLines('customer_invoice', $tid);
                $orderedIds = $this->orderedIdsForTargetBySource($soLinesAfter, $targetLines);
                $this->applyOrderedIdsToDoc('customer_invoice', $tid, $orderedIds);
            }
            return;
        }

        if ($sourceDocType === 'purchase_rfq') {
            $targets = $db->table('purchase_orders')->select('id')->where('rfq_id', $sourceDocId)->get()->getResultArray();
            foreach ($targets as $t) {
                $tid = (int)($t['id'] ?? 0);
                if ($tid <= 0) continue;
                $targetLines = $this->getLines('purchase_order', $tid);
                $orderedIds = $this->orderedIdsForTargetBySource($sourceLines, $targetLines);
                $this->applyOrderedIdsToDoc('purchase_order', $tid, $orderedIds);
            }
            return;
        }

        if ($sourceDocType === 'purchase_order') {
            $po = $db->table('purchase_orders')->select('id,rfq_id')->where('id', $sourceDocId)->get()->getRowArray();
            $rfqId = (int)($po['rfq_id'] ?? 0);
            if ($rfqId <= 0) return;

            $rfqLines = $this->getLines('purchase_rfq', $rfqId);
            $orderedIdsRfq = $this->orderedIdsForTargetBySource($sourceLines, $rfqLines);
            $this->applyOrderedIdsToDoc('purchase_rfq', $rfqId, $orderedIdsRfq);

            // Fan out to all POs from this RFQ.
            $targets = $db->table('purchase_orders')->select('id')->where('rfq_id', $rfqId)->get()->getResultArray();
            $rfqLinesAfter = $this->getLines('purchase_rfq', $rfqId);
            foreach ($targets as $t) {
                $tid = (int)($t['id'] ?? 0);
                if ($tid <= 0) continue;
                $targetLines = $this->getLines('purchase_order', $tid);
                $orderedIds = $this->orderedIdsForTargetBySource($rfqLinesAfter, $targetLines);
                $this->applyOrderedIdsToDoc('purchase_order', $tid, $orderedIds);
            }
        }
    }

    private function sectionLayoutFromLines(array $lines): array
    {
        $layout = [];
        $productsSeen = 0;

        foreach ($lines as $ln) {
            if ($this->lineType($ln) === 'section') {
                $title = trim((string)($ln['section_title'] ?? ($ln['description'] ?? 'Section')));
                $layout[] = [
                    'after_products' => $productsSeen,
                    'title' => $title !== '' ? $title : 'Section',
                ];
                continue;
            }
            $productsSeen++;
        }

        return $layout;
    }

    private function applySectionLayout(string $docType, int $docId, array $layout): void
    {
        $cfg = $this->configByType($docType);
        if (!$cfg) return;

        $this->ensureLineSchema($cfg['line_table']);
        $db = Database::connect();
        $table = $db->table($cfg['line_table']);
        $lineModel = $cfg['line_model'];

        $productLines = $table
            ->where($cfg['doc_fk'], $docId)
            ->where('display_type !=', 'section')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $productCount = count($productLines);
        foreach ($layout as &$layoutEntry) {
            $anchor = (int)($layoutEntry['after_products'] ?? 0);
            $layoutEntry['after_products'] = max(0, min($productCount, $anchor));
        }
        unset($layoutEntry);

        $db->transBegin();
        try {
            $table->where($cfg['doc_fk'], $docId)->where('display_type', 'section')->delete();

            $layoutIdx = 0;
            $layoutCount = count($layout);
            $productsSeen = 0;
            $sort = 1;

            $insertSections = function (int $afterProducts) use (&$layoutIdx, $layoutCount, &$layout, $cfg, $docType, $docId, $lineModel, &$sort) {
                while ($layoutIdx < $layoutCount && (int)($layout[$layoutIdx]['after_products'] ?? -1) === $afterProducts) {
                    $title = trim((string)($layout[$layoutIdx]['title'] ?? 'Section'));
                    if ($title === '') $title = 'Section';

                    $section = [
                        $cfg['doc_fk'] => $docId,
                        'display_type' => 'section',
                        'section_title' => $title,
                        'description' => $title,
                        'sort_order' => $sort,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ];

                    if ($docType === 'quotation' || $docType === 'sales_order' || $docType === 'customer_invoice') {
                        $section['quantity'] = 0;
                        $section['unit_price'] = 0;
                        $section['line_total'] = 0;
                        $section['discount_value'] = 0;
                        $section['discount_amount'] = 0;
                        $section['tax_rate'] = 0;
                        $section['tax_amount'] = 0;
                        if ($docType === 'customer_invoice') {
                            $section['product_code'] = null;
                            $section['product_name'] = $title;
                            $section['unit'] = null;
                            $section['product_id'] = null;
                            $section['product_variant_id'] = null;
                        }
                    } else {
                        $section['qty'] = 0;
                        $section['quantity'] = 0;
                        $section['unit_price'] = 0;
                        $section['unit_cost'] = 0;
                        $section['line_total'] = 0;
                    }

                    $lineModel->insert($section);
                    $sort++;
                    $layoutIdx++;
                }
            };

            $insertSections(0);

            foreach ($productLines as $pl) {
                $table->where('id', (int)$pl['id'])->where($cfg['doc_fk'], $docId)->update([
                    'sort_order' => $sort,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $sort++;
                $productsSeen++;
                $insertSections($productsSeen);
            }

            while ($layoutIdx < $layoutCount) {
                $before = $layoutIdx;
                $insertSections($productsSeen);
                if ($layoutIdx === $before) {
                    break;
                }
            }

            $db->transCommit();
        } catch (\Throwable $_) {
            $db->transRollback();
        }
    }

    private function syncSectionsAcrossSalesAndInvoices(string $docType, int $docId): void
    {
        $db = Database::connect();

        if ($docType === 'sales_order') {
            $salesOrderLines = $this->getLines('sales_order', $docId);
            $layout = $this->sectionLayoutFromLines($salesOrderLines);
            if (empty($layout)) {
                $layout = [];
            }

            $invoiceRows = $db->table('customer_invoices')
                ->select('id')
                ->where('sales_order_id', $docId)
                ->get()
                ->getResultArray();

            foreach ($invoiceRows as $inv) {
                $invoiceId = (int)($inv['id'] ?? 0);
                if ($invoiceId <= 0) continue;
                $this->applySectionLayout('customer_invoice', $invoiceId, $layout);
            }
            return;
        }

        if ($docType === 'customer_invoice') {
            $invoice = $db->table('customer_invoices')->where('id', $docId)->get()->getRowArray();
            $salesOrderId = (int)($invoice['sales_order_id'] ?? 0);
            if ($salesOrderId <= 0) {
                return;
            }

            $invoiceLines = $this->getLines('customer_invoice', $docId);
            $layout = $this->sectionLayoutFromLines($invoiceLines);
            $this->applySectionLayout('sales_order', $salesOrderId, $layout);

            $this->syncSectionsAcrossSalesAndInvoices('sales_order', $salesOrderId);
        }
    }

    public function reorder()
    {
        if (strtolower($this->request->getMethod()) !== 'post') {
            return $this->response->setStatusCode(405)->setJSON(['success' => false, 'error' => 'Method not allowed']);
        }

        $payload = $this->request->getJSON(true) ?: $this->request->getPost();
        $docType = strtolower(trim((string)($payload['doc_type'] ?? '')));
        $identifier = trim((string)($payload['document_id'] ?? ''));
        $lineIds = $payload['line_ids'] ?? [];
        $versionMap = $payload['line_versions'] ?? [];

        if ($docType === '' || $identifier === '' || !is_array($lineIds)) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'error' => 'Invalid request payload']);
        }

        $cfg = $this->configByType($docType);
        if (!$cfg) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'error' => 'Unsupported document type']);
        }

        $this->ensureLineSchema($cfg['line_table']);
        $doc = $this->resolveDocument($docType, $identifier);
        if (!$doc) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'Document not found']);
        }
        $docId = (int)$doc['id'];

        if (!$this->authorizeDocument($docType, $doc)) {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'error' => 'Not allowed to modify this document']);
        }

        $lines = $this->getLines($docType, $docId);
        if (count($lines) <= 1) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Nothing to reorder',
                'rows_html' => $this->renderRows($docType, $lines),
                'line_count' => count($lines),
            ]);
        }

        $conflict = $this->checkConflict($lines, is_array($versionMap) ? $versionMap : []);
        if ($conflict !== null) {
            return $this->response->setStatusCode(409)->setJSON(['success' => false, 'error' => $conflict]);
        }

        $lineSet = [];
        foreach ($lines as $ln) {
            $lineSet[(int)$ln['id']] = true;
        }

        $filteredOrder = [];
        foreach ($lineIds as $id) {
            $lineId = (int)$id;
            if ($lineId > 0 && isset($lineSet[$lineId])) {
                $filteredOrder[] = $lineId;
                unset($lineSet[$lineId]);
            }
        }
                if (empty($ln['product_image_url'])) {
                    $imgUrl = '';
                    if (!empty($p['image'])) {
                        $imgUrl = base_url('/uploads/products/' . ltrim((string)$p['image'], '/'));
                    } elseif (!empty($p['images'])) {
                        $imgs = is_string($p['images']) ? json_decode($p['images'], true) : $p['images'];
                        if (is_array($imgs) && !empty($imgs[0])) {
                            $imgUrl = base_url('/uploads/products/' . ltrim((string)$imgs[0], '/'));
                        }
                    }
                    if ($imgUrl !== '') {
                        $ln['product_image_url'] = $imgUrl;
                    }
                }
        foreach (array_keys($lineSet) as $remainingId) {
            $filteredOrder[] = (int)$remainingId;
        }

        $db = Database::connect();
        $builder = $db->table($cfg['line_table']);
        $now = date('Y-m-d H:i:s');
                if (empty($ln['variant_image_url']) && !empty($v['image'])) {
                    $ln['variant_image_url'] = base_url('/uploads/variants/' . ltrim((string)$v['image'], '/'));
                }
                if (empty($ln['product_image_url']) && !empty($ln['variant_image_url'])) {
                    $ln['product_image_url'] = $ln['variant_image_url'];
                }
        $db->transBegin();
        try {
            $sort = 1;
            foreach ($filteredOrder as $lineId) {
                $builder->where('id', $lineId)->where($cfg['doc_fk'], $docId)->update([
                    'sort_order' => $sort,
                    'updated_at' => $now,
                ]);
                $sort++;
            }
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => $e->getMessage()]);
        }

        $lines = $this->getLines($docType, $docId);
        $this->syncLinkedDocumentOrder($docType, $docId);
        $lines = $this->getLines($docType, $docId);
        $orderedIds = array_map(static function ($ln) { return (int)($ln['id'] ?? 0); }, $lines);
        return $this->response->setJSON([
            'success' => true,
            'message' => 'Line order updated',
            'rows_html' => $this->renderRows($docType, $lines),
            'ordered_line_ids' => $orderedIds,
            'line_count' => count($lines),
        ]);
    }

    private function compareNullableStringAsc(string $a, string $b, bool $asc): int
    {
        $aEmpty = trim($a) === '';
        $bEmpty = trim($b) === '';
        if ($aEmpty && !$bEmpty) return 1;
        if (!$aEmpty && $bEmpty) return -1;
        $cmp = strcasecmp($a, $b);
        return $asc ? $cmp : -$cmp;
    }

    public function sort()
    {
        if (strtolower($this->request->getMethod()) !== 'post') {
            return $this->response->setStatusCode(405)->setJSON(['success' => false, 'error' => 'Method not allowed']);
        }

        $payload = $this->request->getJSON(true) ?: $this->request->getPost();
        $docType = strtolower(trim((string)($payload['doc_type'] ?? '')));
        $identifier = trim((string)($payload['document_id'] ?? ''));
        $criteria = trim((string)($payload['sort_key'] ?? ''));
        $versionMap = $payload['line_versions'] ?? [];

        $cfg = $this->configByType($docType);
        if (!$cfg || $identifier === '' || $criteria === '') {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'error' => 'Invalid request payload']);
        }

        $this->ensureLineSchema($cfg['line_table']);
        $doc = $this->resolveDocument($docType, $identifier);
        if (!$doc) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'Document not found']);
        }
        $docId = (int)$doc['id'];

        if (!$this->authorizeDocument($docType, $doc)) {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'error' => 'Not allowed to modify this document']);
        }

        $lines = $this->getLines($docType, $docId);
        if (count($lines) <= 1) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'No sorting needed',
                'rows_html' => $this->renderRows($docType, $lines),
                'line_count' => count($lines),
            ]);
        }

        $conflict = $this->checkConflict($lines, is_array($versionMap) ? $versionMap : []);
        if ($conflict !== null) {
            return $this->response->setStatusCode(409)->setJSON(['success' => false, 'error' => $conflict]);
        }

        $groups = [0 => ['section' => null, 'products' => $lines]];

        $sorter = function(array $a, array $b) use ($criteria) {
            $aCode = trim((string)($a['product_code'] ?? ''));
            $bCode = trim((string)($b['product_code'] ?? ''));
            $aName = trim((string)($a['product_name'] ?? ($a['description'] ?? '')));
            $bName = trim((string)($b['product_name'] ?? ($b['description'] ?? '')));
            $aPrice = (float)($a['unit_price'] ?? ($a['unit_cost'] ?? 0));
            $bPrice = (float)($b['unit_price'] ?? ($b['unit_cost'] ?? 0));
            $aTs = strtotime((string)($a['created_at'] ?? '')) ?: 0;
            $bTs = strtotime((string)($b['created_at'] ?? '')) ?: 0;
            $aId = (int)($a['id'] ?? 0);
            $bId = (int)($b['id'] ?? 0);

            switch ($criteria) {
                case 'created_oldest':
                    return ($aTs <=> $bTs) ?: ($aId <=> $bId);
                case 'created_newest':
                    return ($bTs <=> $aTs) ?: ($bId <=> $aId);
                case 'name_asc':
                    return strcasecmp($aName, $bName) ?: ($aId <=> $bId);
                case 'name_desc':
                    return strcasecmp($bName, $aName) ?: ($aId <=> $bId);
                case 'code_asc': {
                    $aEmpty = $aCode === '';
                    $bEmpty = $bCode === '';
                    if ($aEmpty && !$bEmpty) return 1;
                    if (!$aEmpty && $bEmpty) return -1;
                    return strcasecmp($aCode, $bCode) ?: ($aId <=> $bId);
                }
                case 'code_desc': {
                    $aEmpty = $aCode === '';
                    $bEmpty = $bCode === '';
                    if ($aEmpty && !$bEmpty) return 1;
                    if (!$aEmpty && $bEmpty) return -1;
                    return strcasecmp($bCode, $aCode) ?: ($aId <=> $bId);
                }
                case 'price_asc':
                    return ($aPrice <=> $bPrice) ?: ($aId <=> $bId);
                case 'price_desc':
                    return ($bPrice <=> $aPrice) ?: ($aId <=> $bId);
                default:
                    return ($aId <=> $bId);
            }
        };

        foreach ($groups as &$grp) {
            if (count($grp['products']) > 1) {
                usort($grp['products'], $sorter);
            }
        }
        unset($grp);

        $orderedIds = [];
        foreach ($groups as $grp) {
            foreach ($grp['products'] as $ln) {
                $orderedIds[] = (int)$ln['id'];
            }
        }

        $db = Database::connect();
        $builder = $db->table($cfg['line_table']);
        $now = date('Y-m-d H:i:s');
        $db->transBegin();
        try {
            $sort = 1;
            foreach ($orderedIds as $lineId) {
                $builder->where('id', $lineId)->where($cfg['doc_fk'], $docId)->update([
                    'sort_order' => $sort,
                    'updated_at' => $now,
                ]);
                $sort++;
            }
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => $e->getMessage()]);
        }

        $lines = $this->getLines($docType, $docId);
        $this->syncLinkedDocumentOrder($docType, $docId);
        $lines = $this->getLines($docType, $docId);
        $orderedIds = array_map(static function ($ln) { return (int)($ln['id'] ?? 0); }, $lines);
        return $this->response->setJSON([
            'success' => true,
            'message' => 'Lines sorted successfully',
            'rows_html' => $this->renderRows($docType, $lines),
            'ordered_line_ids' => $orderedIds,
            'line_count' => count($lines),
        ]);
    }

    public function addSection()
    {
        return $this->response->setStatusCode(410)->setJSON(['success' => false, 'error' => 'Section feature has been removed']);

        $payload = $this->request->getJSON(true) ?: $this->request->getPost();
        $docType = strtolower(trim((string)($payload['doc_type'] ?? '')));
        $identifier = trim((string)($payload['document_id'] ?? ''));
        $title = trim((string)($payload['section_title'] ?? 'New Section'));
        $afterLineId = (int)($payload['after_line_id'] ?? 0);

        $cfg = $this->configByType($docType);
        if (!$cfg || $identifier === '') {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'error' => 'Invalid request payload']);
        }

        $this->ensureLineSchema($cfg['line_table']);
        $doc = $this->resolveDocument($docType, $identifier);
        if (!$doc) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'Document not found']);
        }
        $docId = (int)$doc['id'];

        if (!$this->authorizeDocument($docType, $doc)) {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'error' => 'Not allowed to modify this document']);
        }

        $lineModel = $cfg['line_model'];
        $lines = $this->getLines($docType, $docId);

        $insertSort = count($lines) + 1;
        if ($afterLineId > 0) {
            foreach ($lines as $idx => $ln) {
                if ((int)$ln['id'] === $afterLineId) {
                    $insertSort = $idx + 2;
                    break;
                }
            }
        }

        $db = Database::connect();
        $db->transBegin();
        try {
            $table = $db->table($cfg['line_table']);
            $table->where($cfg['doc_fk'], $docId)->where('sort_order >=', $insertSort)->set('sort_order', 'sort_order+1', false)->update();

            $section = [
                $cfg['doc_fk'] => $docId,
                'display_type' => 'section',
                'section_title' => $title !== '' ? $title : 'New Section',
                'description' => $title !== '' ? $title : 'New Section',
                'sort_order' => $insertSort,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if ($docType === 'quotation' || $docType === 'sales_order') {
                $section['quantity'] = 0;
                $section['unit_price'] = 0;
                $section['line_total'] = 0;
                $section['discount_value'] = 0;
                $section['discount_amount'] = 0;
                $section['tax_rate'] = 0;
                $section['tax_amount'] = 0;
            } else {
                $section['qty'] = 0;
                $section['quantity'] = 0;
                $section['unit_price'] = 0;
                $section['unit_cost'] = 0;
                if ($db->fieldExists('discount_value', $cfg['line_table'])) $section['discount_value'] = 0;
                if ($db->fieldExists('discount_amount', $cfg['line_table'])) $section['discount_amount'] = 0;
                if ($db->fieldExists('tax_amount', $cfg['line_table'])) $section['tax_amount'] = 0;
                if ($db->fieldExists('line_total', $cfg['line_table'])) $section['line_total'] = 0;
            }

            $lineModel->insert($section);
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => $e->getMessage()]);
        }

        $lines = $this->getLines($docType, $docId);
        return $this->response->setJSON([
            'success' => true,
            'message' => 'Section added',
            'rows_html' => $this->renderRows($docType, $lines),
            'line_count' => count($lines),
        ]);
    }

    public function updateSection()
    {
        return $this->response->setStatusCode(410)->setJSON(['success' => false, 'error' => 'Section feature has been removed']);

        $payload = $this->request->getJSON(true) ?: $this->request->getPost();
        $docType = strtolower(trim((string)($payload['doc_type'] ?? '')));
        $identifier = trim((string)($payload['document_id'] ?? ''));
        $lineId = (int)($payload['line_id'] ?? 0);
        $title = trim((string)($payload['section_title'] ?? ''));

        $cfg = $this->configByType($docType);
        if (!$cfg || $identifier === '' || $lineId <= 0) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'error' => 'Invalid request payload']);
        }

        $doc = $this->resolveDocument($docType, $identifier);
        if (!$doc) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'Document not found']);
        }
        $docId = (int)$doc['id'];

        if (!$this->authorizeDocument($docType, $doc)) {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'error' => 'Not allowed to modify this document']);
        }

        $this->ensureLineSchema($cfg['line_table']);

        $lineModel = $cfg['line_model'];
        $line = $lineModel->find($lineId);
        if (!$line || (int)($line[$cfg['doc_fk']] ?? 0) !== $docId) {
            // Direct DB fallback
            $db = Database::connect();
            $line = $db->table($cfg['line_table'])
                ->where('id', $lineId)->where($cfg['doc_fk'], $docId)
                ->get()->getRowArray();
            if (!$line) {
                return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'Section line not found']);
            }
        }
        if ($this->lineType($line) !== 'section') {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'error' => 'Line is not a section']);
        }

        $lineModel->update($lineId, [
            'section_title' => $title !== '' ? $title : 'Section',
            'description' => $title !== '' ? $title : 'Section',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $lines = $this->getLines($docType, $docId);
        return $this->response->setJSON([
            'success' => true,
            'message' => 'Section updated',
            'rows_html' => $this->renderRows($docType, $lines),
        ]);
    }

    public function deleteSection()
    {
        return $this->response->setStatusCode(410)->setJSON(['success' => false, 'error' => 'Section feature has been removed']);

        $payload = $this->request->getJSON(true) ?: $this->request->getPost();
        $docType = strtolower(trim((string)($payload['doc_type'] ?? '')));
        $identifier = trim((string)($payload['document_id'] ?? ''));
        $lineId = (int)($payload['line_id'] ?? 0);
        $mode = strtolower(trim((string)($payload['mode'] ?? 'header_only')));

        $cfg = $this->configByType($docType);
        if (!$cfg || $identifier === '' || $lineId <= 0) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'error' => 'Invalid request payload']);
        }

        $doc = $this->resolveDocument($docType, $identifier);
        if (!$doc) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'Document not found']);
        }
        $docId = (int)$doc['id'];

        if (!$this->authorizeDocument($docType, $doc)) {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'error' => 'Not allowed to modify this document']);
        }

        $this->ensureLineSchema($cfg['line_table']);

        $lineModel = $cfg['line_model'];
        $lines = $this->getLines($docType, $docId);
        $start = -1;
        for ($i = 0; $i < count($lines); $i++) {
            if ((int)$lines[$i]['id'] === $lineId) {
                $start = $i;
                break;
            }
        }

        // Fallback: if not found via getLines (e.g. different line source on the page),
        // look up the line directly from DB to verify it exists and is a section.
        if ($start < 0 || $this->lineType($lines[$start]) !== 'section') {
            $db = Database::connect();
            $directLine = $db->table($cfg['line_table'])
                ->where('id', $lineId)
                ->where($cfg['doc_fk'], $docId)
                ->get()->getRowArray();

            if ($directLine && strtolower(trim((string)($directLine['display_type'] ?? ''))) === 'section') {
                // Found it via direct DB lookup — add it to the lines array so downstream logic works
                $start = count($lines);
                $lines[] = $directLine;
            } else {
                $errDetail = $start < 0 ? 'line id not in document' : 'line exists but is not a section';
                return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'Section not found (' . $errDetail . ')']);
            }
        }

        $deleteIds = [$lineId];
        if ($mode === 'with_children') {
            for ($j = $start + 1; $j < count($lines); $j++) {
                if ($this->lineType($lines[$j]) === 'section') break;
                $deleteIds[] = (int)$lines[$j]['id'];
            }
        }

        $db = Database::connect();
        $db->transBegin();
        try {
            foreach ($deleteIds as $id) {
                $lineModel->delete($id);
            }

            $remaining = $this->getLines($docType, $docId);
            $sort = 1;
            foreach ($remaining as $ln) {
                $db->table($cfg['line_table'])->where('id', (int)$ln['id'])->where($cfg['doc_fk'], $docId)->update([
                    'sort_order' => $sort,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $sort++;
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => $e->getMessage()]);
        }

        $this->recalcDocumentTotals($docType, $docId);
        $lines = $this->getLines($docType, $docId);
        return $this->response->setJSON([
            'success' => true,
            'message' => 'Section deleted',
            'rows_html' => $this->renderRows($docType, $lines),
        ]);
    }

    public function recalculate()
    {
        if (strtolower($this->request->getMethod()) !== 'post') {
            return $this->response->setStatusCode(405)->setJSON(['success' => false, 'error' => 'Method not allowed']);
        }

        $payload = $this->request->getJSON(true) ?: $this->request->getPost();
        $docType = strtolower(trim((string)($payload['doc_type'] ?? '')));
        $identifier = trim((string)($payload['document_id'] ?? ''));

        $cfg = $this->configByType($docType);
        if (!$cfg || $identifier === '') {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'error' => 'Invalid request payload']);
        }

        $doc = $this->resolveDocument($docType, $identifier);
        if (!$doc) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'Document not found']);
        }
        $docId = (int)$doc['id'];

        if (!$this->authorizeDocument($docType, $doc)) {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'error' => 'Not allowed to modify this document']);
        }

        $lineId = (int)($payload['line_id'] ?? 0);
        if ($lineId > 0) {
            $lineModel = $cfg['line_model'];
            $line = $lineModel->find($lineId);
            if ($line && (int)($line[$cfg['doc_fk']] ?? 0) === $docId && $this->lineType($line) !== 'section') {
                $upd = ['updated_at' => date('Y-m-d H:i:s')];
                if ($docType === 'quotation' || $docType === 'sales_order') {
                    if (array_key_exists('quantity', $payload)) $upd['quantity'] = (float)$payload['quantity'];
                    if (array_key_exists('unit_price', $payload)) $upd['unit_price'] = (float)$payload['unit_price'];
                    if (array_key_exists('tax_rate', $payload)) $upd['tax_rate'] = (float)$payload['tax_rate'];
                    if (array_key_exists('discount_value', $payload)) $upd['discount_value'] = (float)$payload['discount_value'];

                    $qty = (float)($upd['quantity'] ?? $line['quantity'] ?? 0);
                    $price = (float)($upd['unit_price'] ?? $line['unit_price'] ?? 0);
                    $disc = (float)($upd['discount_value'] ?? $line['discount_value'] ?? 0);
                    $discType = strtolower((string)($line['discount_type'] ?? 'percent'));
                    $taxRate = (float)($upd['tax_rate'] ?? $line['tax_rate'] ?? 0);
                    $base = $qty * $price;
                    $discAmt = $discType === 'fixed' ? $disc : ($base * ($disc / 100));
                    $taxable = max(0, $base - $discAmt);
                    $taxAmt = $taxable * ($taxRate / 100);
                    $lineTotal = $taxable + $taxAmt;
                    $upd['discount_amount'] = round($discAmt, 2);
                    $upd['tax_amount'] = round($taxAmt, 2);
                    $upd['line_total'] = round($lineTotal, 2);
                } else {
                    if (array_key_exists('qty', $payload)) $upd['qty'] = (float)$payload['qty'];
                    if (array_key_exists('quantity', $payload)) $upd['quantity'] = (float)$payload['quantity'];
                    if (array_key_exists('unit_price', $payload)) $upd['unit_price'] = (float)$payload['unit_price'];
                    if (array_key_exists('unit_cost', $payload)) $upd['unit_cost'] = (float)$payload['unit_cost'];
                    if (array_key_exists('tax_amount', $payload)) $upd['tax_amount'] = (float)$payload['tax_amount'];
                }

                $lineModel->update($lineId, $upd);
            }
        }

        $this->recalcDocumentTotals($docType, $docId);
        $lines = $this->getLines($docType, $docId);
        $summary = $this->buildSectionSummary($lines, $docType);

        return $this->response->setJSON([
            'success' => true,
            'rows_html' => $this->renderRows($docType, $lines),
            'section_subtotals' => $summary['section_subtotals'],
            'grand_total' => $summary['grand_total'],
        ]);
    }
}
