<?php

namespace App\Controllers;

use App\Models\VendorBillModel;
use App\Models\VendorBillLineModel;
use App\Services\AccountingPostingService;
use App\Services\ProcessingBillingValidationService;
use Config\Database;

class VendorBills extends BaseController
{
    /**
     * Get vendor bill details
     * 
     * Route: GET /vendor-bills/{bill_id}
     */
    public function show($billId = null)
    {
        $bill = $billModel->findByPublicIdOrId($billId);
        if (!$bill) {
            if ($this->request->isAJAX() || strpos($this->request->getHeaderLine('Accept'), 'application/json') !== false) {
                return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'Bill not found']);
            }
            return redirect()->to(site_url('purchases'))->with('error', 'Bill not found');
        }
        public function show($billId = null)
        {
            try {
                $billModel = new VendorBillModel();
                $bill = $billModel->findByPublicIdOrId($billId);
                if (!$bill) {
                    if ($this->request->isAJAX() || strpos($this->request->getHeaderLine('Accept'), 'application/json') !== false) {
                        return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'Bill not found']);
                    }
                    return redirect()->to(site_url('purchases'))->with('error', 'Bill not found');
                }@@
        $billId = (int)$bill['id'];

        // Calculate paid amount from allocations and update balance dynamically
        $db = Database::connect();
        $paidResult = $db->query("
            SELECT COALESCE(SUM(COALESCE(NULLIF(vpa.amount_allocated, 0), vpa.amount, 0)), 0) as paid_amount
            FROM vendor_payment_allocations vpa
            JOIN vendor_payments vp ON vp.id = vpa.payment_id
            WHERE vpa.vendor_bill_id = ? AND vp.status = 'posted'
        ", [$billId])->getRowArray();

        $paid = (float)($paidResult['paid_amount'] ?? 0);
        $total = (float)($bill['total_amount'] ?? 0);
        $bill['paid'] = $paid;
        $bill['balance'] = max(0.0, $total - $paid);
        $bill['is_paid'] = $bill['balance'] <= 0.0001;

        // Load bill lines
        $lineModel = new VendorBillLineModel();
        $lines = $lineModel->where('vendor_bill_id', $billId)->findAll();

        // Initialize image_urls for all lines (ensures field exists even if exception occurs)
        foreach ($lines as &$line) {
            if (!isset($line['image_urls'])) {
                $line['image_urls'] = [];
            }
        }
        unset($line);
        
        // DEBUG: Log what lines were loaded
        log_message('debug', "Bill {$billId}: Loaded " . count($lines) . " lines");
        foreach ($lines as $ln) {
            log_message('debug', "  Line: product_id={$ln['product_id']}, variant_id={$ln['variant_id']}, po_line_id={$ln['po_line_id']}");
        }

        // Enrich with product/variant info and vendor info
        try {
            $db = Database::connect();
            // Collect product IDs from bill lines directly
            $productIds = array_values(array_filter(array_unique(array_map(function($l){ return $l['product_id'] ?? null; }, $lines))));
            $variantIds = array_values(array_filter(array_unique(array_map(function($l){ return $l['variant_id'] ?? null; }, $lines))));
            
            // DEBUG: Log collected IDs
            log_message('debug', "Collected productIds: " . json_encode($productIds));
            log_message('debug', "Collected variantIds: " . json_encode($variantIds));
            $poLineIds = array_values(array_filter(array_unique(array_map(function($l){ return !empty($l['product_id']) ? null : ($l['po_line_id'] ?? null); }, $lines))));
            if (!empty($poLineIds)) {
                try {
                    $poLineProducts = $db->table('purchase_order_lines')
                        ->select('product_id')
                        ->whereIn('id', $poLineIds)
                        ->where('product_id IS NOT NULL')
                        ->get()->getResultArray();
                    foreach ($poLineProducts as $plp) {
                        if (!empty($plp['product_id'])) $productIds[] = (int)$plp['product_id'];
                    }
                    $productIds = array_values(array_unique(array_filter($productIds)));
                } catch (\Throwable $_) {}
            }
            
            $prodMap = [];
            if (!empty($productIds)) {
                $products = $db->table('products')
                    ->select('id, name, code, sku, description, images, detailed_type, unit')
                    ->whereIn('id', $productIds)
                    ->get()
                    ->getResultArray();
                
                // DEBUG: Log what products were fetched
                log_message('debug', "Querying products with IDs: " . json_encode($productIds));
                log_message('debug', "Found " . count($products) . " products");
                foreach ($products as $p) {
                    $imagesData = json_encode($p['images']);
                    log_message('debug', "  Product ID {$p['id']}: code={$p['code']}, name={$p['name']}, images_data={$imagesData}");
                }
                
                foreach ($products as $p) {
                    $prodMap[(int)$p['id']] = $p;
                }
            } else {
                log_message('debug', "No productIds to fetch!");
            }

            $variantMap = [];
            if (!empty($variantIds)) {
                $variants = $db->table('product_variants')
                    ->select('id, product_id, art_number, name, attributes, image')
                    ->whereIn('id', $variantIds)
                    ->get()
                    ->getResultArray();
                foreach ($variants as $v) {
                    $variantMap[(int)$v['id']] = $v;
                    if (!empty($v['product_id'])) {
                        $productIds[] = (int)$v['product_id'];
                }

                $productIds = array_values(array_unique(array_filter($productIds)));
                if (!empty($productIds)) {
                    $products = $db->table('products')
                        ->select('id, name, code, sku, description, images, detailed_type, unit')
                        ->whereIn('id', $productIds)
                        ->get()
                        ->getResultArray();
                    foreach ($products as $p) {
                        $prodMap[(int)$p['id']] = $p;
                    }
                }
            }

            $poLineMap = [];
            if (!empty($poLineIds)) {
                try {
                    $poRows = $db->table('purchase_order_lines')
                        ->select('id, product_id, variant_id, description')
                        ->whereIn('id', $poLineIds)
                        ->get()
                        ->getResultArray();
                    foreach ($poRows as $pl) {
                        $poLineMap[(int)$pl['id']] = $pl;
                    }
                } catch (\Throwable $_) {}
            }

            $processingSummaryMap = [];
            $processingBillingService = new ProcessingBillingValidationService();

            $buildImageCandidates = static function (string $fileName, string $folder): array {
                $fileName = trim($fileName);
                if ($fileName === '') {
                    return [];
                }
                if (preg_match('#^https?://#i', $fileName)) {
                    return [$fileName];
                }
                $fileName = ltrim(str_replace('\\', '/', $fileName), '/');
                $fileName = preg_replace('#^(public/)?uploads/' . preg_quote($folder, '#') . '/#i', '', $fileName);
                $fileName = ltrim((string)$fileName, '/');
                if ($fileName === '') {
                    return [];
                }

                $filePath = str_replace('/', DIRECTORY_SEPARATOR, $fileName);
                $diskPrimary = rtrim((string)FCPATH, '\\/') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $filePath;
                $diskPublic = rtrim((string)ROOTPATH, '\\/') . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $filePath;

                $candidates = [];
                if (is_file($diskPrimary)) {
                    $candidates[] = base_url('uploads/' . $folder . '/' . $fileName);
                }
                if (is_file($diskPublic)) {
                    $candidates[] = base_url('public/uploads/' . $folder . '/' . $fileName);
                }

                if (empty($candidates)) {
                    // Fallback: return URLs anyway, they will be tried in browser
                    $candidates[] = base_url('uploads/' . $folder . '/' . $fileName);
                    $candidates[] = base_url('public/uploads/' . $folder . '/' . $fileName);
                }
                return array_values(array_unique(array_filter($candidates)));
            };

            foreach ($lines as &$line) {
                if (!empty($line['po_line_id']) && isset($poLineMap[(int)$line['po_line_id']])) {
                    $poLine = $poLineMap[(int)$line['po_line_id']];
                    if (empty($line['product_id']) && !empty($poLine['product_id'])) {
                        $line['product_id'] = (int)$poLine['product_id'];
                    }
                    if (empty($line['variant_id']) && !empty($poLine['variant_id'])) {
                        $line['variant_id'] = (int)$poLine['variant_id'];
                    }
                    if (empty($line['product_description']) && !empty($poLine['description'])) {
                        $line['product_description'] = $poLine['description'];
                    }
                }

                if (!empty($line['variant_id']) && isset($variantMap[(int)$line['variant_id']])) {
                    $v = $variantMap[(int)$line['variant_id']];
                    $line['variant_art_number'] = $v['art_number'] ?? null;
                    $line['variant_name'] = $v['name'] ?? null;
                    $line['variant_attributes'] = $v['attributes'] ?? null;
                    $line['variant_image'] = $v['image'] ?? null;
                    if (empty($line['product_id']) && !empty($v['product_id'])) {
                        $line['product_id'] = (int)$v['product_id'];
                    }
                }

                if (!empty($line['product_id']) && isset($prodMap[(int)$line['product_id']])) {
                    $p = $prodMap[(int)$line['product_id']];
                    $line['product_name'] = $p['name'] ?? null;
                    $line['product_code'] = $p['code'] ?? $p['sku'] ?? null;
                    if (empty($line['product_description'])) {
                        $line['product_description'] = $p['description'] ?? null;
                    }
                    $line['product_images'] = $p['images'] ?? null;
                    $line['product_detailed_type'] = $p['detailed_type'] ?? null;
                    $line['product_unit'] = $p['unit'] ?? null;
                    
                    // DEBUG: Log product enrichment
                    log_message('debug', "Enriched line with product ID {$line['product_id']}: images_set=" . (isset($line['product_images']) ? 'YES' : 'NO') . ", images_value=" . json_encode($line['product_images']));
                } elseif (empty($line['product_id']) && !empty($line['po_line_id'])) {
                    // Fallback: use PO line description when no product linked
                    try {
                        $poLine = $db->table('purchase_order_lines')
                            ->select('description, product_id')
                            ->where('id', (int)$line['po_line_id'])
                            ->get()->getRowArray();
                        if ($poLine) {
                            if (!empty($poLine['description'])) {
                                $line['product_name'] = $poLine['description'];
                            }
                            // Also try to get product info from PO line's product_id
                            if (!empty($poLine['product_id']) && isset($prodMap[(int)$poLine['product_id']])) {
                                $p = $prodMap[(int)$poLine['product_id']];
                                $line['product_name'] = $p['name'] ?? $line['product_name'];
                                $line['product_code'] = $p['code'] ?? $p['sku'] ?? null;
                                $line['product_images'] = $p['images'] ?? null;
                                $line['product_detailed_type'] = $p['detailed_type'] ?? null;
                                $line['product_unit'] = $p['unit'] ?? null;
                            }
                        }
                    } catch (\Throwable $_) {}
                }

                $imageCandidates = [];
                $variantImage = trim((string)($line['variant_image'] ?? ''));
                if ($variantImage !== '') {
                    $imageCandidates = array_merge($imageCandidates, $buildImageCandidates($variantImage, 'variants'));
                    log_message('debug', "  Used variant_image: {$variantImage}, got " . count($imageCandidates) . " candidates");
                }

                $productImageFile = '';
                if (!empty($line['product_images'])) {
                    $raw = $line['product_images'];
                    log_message('debug', "  product_images is set, type=" . gettype($raw) . ", value=" . json_encode($raw));
                    if (is_string($raw)) {
                        $decoded = json_decode($raw, true);
                        log_message('debug', "    Decoded JSON, valid=" . (json_last_error() === JSON_ERROR_NONE ? 'YES' : 'NO'));
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && !empty($decoded[0])) {
                            $first = $decoded[0];
                            if (is_array($first)) {
                                $productImageFile = (string)($first['path'] ?? $first['file'] ?? $first['url'] ?? $first['name'] ?? '');
                                log_message('debug', "    Extracted path from array: {$productImageFile}");
                            } else {
                                $productImageFile = (string)$first;
                                log_message('debug', "    Used first element as string: {$productImageFile}");
                            }
                        } else {
                            $productImageFile = trim($raw);
                            log_message('debug', "    Unable to decode, using trimmed raw: {$productImageFile}");
                        }
                    } elseif (is_array($raw) && !empty($raw[0])) {
                        log_message('debug', "    product_images is already array");
                        $first = $raw[0];
                        if (is_array($first)) {
                            $productImageFile = (string)($first['path'] ?? $first['file'] ?? $first['url'] ?? $first['name'] ?? '');
                            log_message('debug', "    Extracted path from nested array: {$productImageFile}");
                        } else {
                            $productImageFile = (string)$first;
                            log_message('debug', "    Used first element: {$productImageFile}");
                        }
                    }
                } else {
                    log_message('debug', "  product_images is NOT set or empty");
                }
                if ($productImageFile !== '') {
                    $result = $buildImageCandidates($productImageFile, 'products');
                    log_message('debug', "  buildImageCandidates('{$productImageFile}', 'products') returned " . count($result) . " URLs: " . json_encode($result));
                    $imageCandidates = array_merge($imageCandidates, $result);
                }
                $line['image_urls'] = array_values(array_unique(array_filter($imageCandidates)));
                
                log_message('debug', "  Final image_urls for line: " . json_encode($line['image_urls']));
                
                // DEBUG: Log image URLs for this line
                $productCode = (string)($line['product_code'] ?? $line['product_id'] ?? 'unknown');
                if (!empty($line['image_urls'])) {
                    log_message('debug', "Bill line {$productCode}: Generated " . count($line['image_urls']) . " image URLs: " . json_encode($line['image_urls']));
                } else {
                    log_message('debug', "Bill line {$productCode}: NO image URLs generated");
                }

                $processingRecordId = (int) ($line['processing_record_id'] ?? 0);
                if ($processingRecordId > 0) {
                    if (!isset($processingSummaryMap[$processingRecordId])) {
                        $processingSummaryMap[$processingRecordId] = $processingBillingService->getSummary($processingRecordId, $billId);
                    }
                    $summary = $processingSummaryMap[$processingRecordId] ?? [];
                    $line['processing_total_processed_qty'] = (float) ($summary['total_processed_qty'] ?? 0);
                    $line['processing_total_billed_qty'] = (float) ($summary['total_billed_qty'] ?? 0);
                    $line['processing_remaining_qty'] = (float) ($summary['remaining_qty'] ?? 0);
                } else {
                    $line['processing_total_processed_qty'] = null;
                    $line['processing_total_billed_qty'] = null;
                    $line['processing_remaining_qty'] = null;
                }
            }
            unset($line);

            // Get vendor and PO info
            if (isset($bill['vendor_id']) && $bill['vendor_id'] !== null) {
                $vendor = $db->table('vendors')->where('id', $bill['vendor_id'])->get()->getRowArray();
                if ($vendor) {
                    $bill['vendor_name'] = $vendor['name'] ?? null;
                }
            }
            if ($bill['po_id']) {
                $po = $db->table('purchase_orders')->where('id', $bill['po_id'])->get()->getRowArray();
                if ($po) {
                    $bill['po_number'] = $po['po_number'] ?? null;
                }
            }

            // Shipping/service context when this bill belongs to a shipping PO.
            $bill['shipping_context'] = null;
            if (!empty($bill['po_id']) && $db->tableExists('delivery_orders')) {
                try {
                    $doRow = $db->table('delivery_orders')
                        ->select('id, public_id, do_number, final_weight_kg, shipped_at, estimated_delivery_days, destination_country, tracking_number, tracking_url')
                        ->where('shipping_po_id', (int)$bill['po_id'])
                        ->orderBy('id', 'DESC')
                        ->get(1)
                        ->getRowArray();
                    if (!empty($doRow)) {
                        $bill['shipping_context'] = [
                            'delivery_order_id' => (int)($doRow['id'] ?? 0),
                            'delivery_order_public_id' => (string)($doRow['public_id'] ?? ''),
                            'delivery_order_number' => (string)($doRow['do_number'] ?? ''),
                            'shipment_weight_kg' => (float)($doRow['final_weight_kg'] ?? 0),
                            'shipped_at' => $doRow['shipped_at'] ?? null,
                            'estimated_delivery_days' => isset($doRow['estimated_delivery_days']) ? (int)$doRow['estimated_delivery_days'] : null,
                            'destination_country' => (string)($doRow['destination_country'] ?? ''),
                            'tracking_number' => (string)($doRow['tracking_number'] ?? ''),
                            'tracking_url' => (string)($doRow['tracking_url'] ?? ''),
                        ];
                    }
                } catch (\Throwable $_) {
                    $bill['shipping_context'] = null;
                }
            }

            // Get payment history for this bill
            $paymentHistory = $db->query("
                SELECT 
                    vp.id as payment_id,
                    vp.payment_date,
                    vp.payment_method,
                    vp.amount,
                    vp.advance_amount,
                    vpa.amount as allocation_amount,
                    (vpa.amount - COALESCE(vp.advance_amount, 0)) as cash_amount
                FROM vendor_payment_allocations vpa
                JOIN vendor_payments vp ON vp.id = vpa.payment_id
                WHERE vpa.vendor_bill_id = ? AND vp.status = 'posted'
                ORDER BY vp.payment_date ASC, vp.id ASC
            ", [$billId])->getResultArray();
                // Get related GRNs for the same PO to enable quick drill-down from bill page.
                $grnRefs = [];
                if (!empty($bill['po_id']) && $db->tableExists('purchase_grns')) {
                    try {
                        $grnRefs = $db->table('purchase_grns')
                            ->select('id, public_id, grn_number, received_at')
                            ->where('po_id', (int)$bill['po_id'])
                            ->orderBy('id', 'DESC')
                            ->get()
                            ->getResultArray();
                    } catch (\Throwable $_) {
                        $grnRefs = [];
                    }
                }

                $relatedBills = [];
                if (!empty($bill['po_id']) && $db->tableExists('vendor_bills')) {
                    try {
                        $relatedBills = $db->table('vendor_bills')
                            ->select('id, public_id, bill_number, status, total_amount, balance, bill_date')
                            ->where('po_id', (int)$bill['po_id'])
                            ->where('id !=', $billId)
                            ->where('status !=', 'cancelled')
                            ->orderBy('id', 'DESC')
                            ->get()
                            ->getResultArray();

                        if (!empty($relatedBills) && $db->tableExists('vendor_payment_allocations') && $db->tableExists('vendor_payments')) {
                            $rbIds = array_values(array_filter(array_map(static function ($rb) {
                                return (int)($rb['id'] ?? 0);
                            }, $relatedBills)));
                            if (!empty($rbIds)) {
                                $paidRows = $db->table('vendor_payment_allocations vpa')
                                    ->select("vpa.vendor_bill_id, SUM(COALESCE(NULLIF(vpa.amount_allocated, 0), vpa.amount, 0)) as paid_amount")
                                    ->join('vendor_payments vp', 'vp.id = vpa.payment_id', 'inner')
                                    ->whereIn('vpa.vendor_bill_id', $rbIds)
                                    ->where('vp.status', 'posted')
                                    ->groupBy('vpa.vendor_bill_id')
                                    ->get()
                                    ->getResultArray();
                                $paidMap = [];
                                foreach ($paidRows as $pr) {
                                    $paidMap[(int)($pr['vendor_bill_id'] ?? 0)] = (float)($pr['paid_amount'] ?? 0);
                                }
                                foreach ($relatedBills as &$rb) {
                                    $rid = (int)($rb['id'] ?? 0);
                                    $tot = (float)($rb['total_amount'] ?? 0);
                                    $pd = (float)($paidMap[$rid] ?? 0);
                                    $rb['balance'] = max(0.0, $tot - $pd);
                                    $rb['is_paid'] = $rb['balance'] <= 0.0001;
                                }
                                unset($rb);
                            }
                        }
                    } catch (\Throwable $_) {
                        $relatedBills = [];
                    }
                }

        } catch (\Throwable $ex) {
            $paymentHistory = [];
                $grnRefs = [];
                $relatedBills = [];
                log_message('error', 'Exception in bill enrichment: ' . $ex->getMessage() . "\n" . $ex->getTraceAsString());
        }

        // Safety: Ensure all lines have image_urls populated (fallback if exception caused skip)
        foreach ($lines as &$line) {
            // Only try fallback if image_urls is still empty
            if (empty($line['image_urls'])) {
                $imageUrls = [];
                
                // Strategy 1: Try variant_image
                if (!empty($line['variant_image'])) {
                    $vImg = trim((string)$line['variant_image']);
                    if ($vImg !== '' && !preg_match('#^/?\s*$#', $vImg)) {
                        // Clean the path
                        $vImg = ltrim($vImg, '/');
                        $vImg = preg_replace('#^(public/)?uploads/variants/#i', '', $vImg);
                        if ($vImg !== '' && !empty($vImg)) {
                            // Try both common locations
                            $imageUrls[] = base_url('uploads/variants/' . $vImg);
                            $imageUrls[] = base_url('public/uploads/variants/' . $vImg);
                        }
                    }
                }
                
                // Strategy 2: Try product_images
                if (empty($imageUrls) && !empty($line['product_images'])) {
                    $imgData = $line['product_images'];
                    $imgFile = '';
                    
                    // Try to extract filename from various formats
                    if (is_string($imgData)) {
                        // Could be JSON string
                        if (strpos($imgData, '[') === 0 || strpos($imgData, '{') === 0) {
                            $decoded = json_decode($imgData, true);
                            if (is_array($decoded)) {
                                if (!empty($decoded[0])) {
                                    $first = $decoded[0];
                                    if (is_string($first)) {
                                        $imgFile = $first;
                                    } elseif (is_array($first)) {
                                        $imgFile = $first['path'] ?? $first['file'] ?? $first['url'] ?? $first['name'] ?? '';
                                    }
                                }
                            }
                        } else {
                            // Try as plain filename
                            $imgFile = trim($imgData);
                        }
                    } elseif (is_array($imgData)) {
                        // Array of filenames or objects
                        if (!empty($imgData[0])) {
                            if (is_string($imgData[0])) {
                                $imgFile = $imgData[0];
                            } elseif (is_array($imgData[0])) {
                                $imgFile = $imgData[0]['path'] ?? $imgData[0]['file'] ?? $imgData[0]['url'] ?? $imgData[0]['name'] ?? '';
                            }
                        }
                    }
                    
                    if (!empty($imgFile) && is_string($imgFile)) {
                        // Clean the file path
                        $imgFile = ltrim(trim($imgFile), '/');
                        $imgFile = preg_replace('#^(public/)?uploads/products/#i', '', $imgFile);
                        if (!empty($imgFile)) {
                            // Try both common locations
                            $imageUrls[] = base_url('uploads/products/' . $imgFile);
                            $imageUrls[] = base_url('public/uploads/products/' . $imgFile);
                        }
                    }
                }
                
                // Remove duplicates and empty values
                $imageUrls = array_values(array_unique(array_filter(array_map(function($url) {
                    return is_string($url) && !empty(trim($url)) ? trim($url) : null;
                }, $imageUrls))));
                
                $line['image_urls'] = $imageUrls;
                log_message('debug', "Fallback builder for line: generated " . count($imageUrls) . " URLs");
            }
        }
        unset($line);

        // DEBUG: Log final image_urls for all lines before returning
        log_message('debug', "=== FINAL BILL RESPONSE ===");
        foreach ($lines as $ln) {
            $code = $ln['product_code'] ?? $ln['product_id'] ?? 'unknown';
            $urls = $ln['image_urls'] ?? [];
            log_message('debug', "Line {$code}: " . count($urls) . " image_urls: " . json_encode($urls));
        }
        log_message('debug', "=== END BILL RESPONSE ===");

        // Return JSON for AJAX/API requests, view for browser
        if ($this->request->isAJAX() || strpos($this->request->getHeaderLine('Accept'), 'application/json') !== false) {
            return $this->response->setJSON([
                'success' => true,
                'data' => [
                    'bill' => $bill,
                    'lines' => $lines,
                    'paymentHistory' => $paymentHistory,
                    'grnRefs' => $grnRefs,
                    'relatedBills' => $relatedBills ?? [],
                ],
            ]);
        }

        // Return view for browser
        return view('vendor_bills/show', [
            'defaultCurrency' => 'PKR',
        ]);
    }

    /**
     * Get payment transactions for a vendor bill.
     * Route: GET /vendor-bills/{bill_id}/transactions
     */
    public function transactions($billId = null)
    {
        $billId = (int)$billId;
        if ($billId <= 0) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'Invalid bill id']);
        }
        try {
            $db = Database::connect();
            $rows = $db->table('vendor_payment_lines')
                ->select('id, vendor_payment_id, amount, payment_date, reference_no, notes')
                ->where('vendor_bill_id', $billId)
                ->orderBy('payment_date', 'DESC')
                ->get()
                ->getResultArray();
            return $this->response->setJSON(['success' => true, 'data' => $rows]);
        } catch (\Throwable $e) {
            log_message('error', 'Failed to load vendor bill transactions: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => 'Server error']);
        }
    }

    /**
     * Confirm a vendor bill and post to accounting
     * 
     * Route: POST /vendor-bills/{bill_id}/confirm
     */
    public function confirm($billId = null)
    {
        $billModel = new VendorBillModel();
        $bill = $billModel->findByPublicIdOrId($billId);
        if (!$bill) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'Bill not found']);
        }
        $billId = (int)$bill['id'];

        if (strtolower($bill['status'] ?? '') !== 'draft') {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'error' => 'Only draft bills can be confirmed',
            ]);
        }

        $payload = $this->request->getJSON(true) ?: $this->request->getPost();
        $billDateOverride = trim((string)($payload['bill_date'] ?? ''));
        $db = Database::connect();
        $db->transBegin();
        try {
            // Generate bill number if not set
            $billNumber = $bill['bill_number'];
            if (!$billNumber) {
                $billNumber = 'VB-' . date('YmdHis') . '-' . $billId;
            }
            
            // Update bill status and bill number
            $updateData = [
                'status' => 'confirmed',
                'bill_number' => $billNumber,
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($billDateOverride !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $billDateOverride)) {
                $updateData['bill_date'] = $billDateOverride;
            }
            $billModel->update($billId, $updateData);

            // Post to accounting
            $postingService = new AccountingPostingService($db);
            $postResult = $postingService->postVendorBill($billId);

            if (!$postResult['success']) {
                throw new \RuntimeException('Accounting posting failed: ' . $postResult['message']);
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('DB transaction failed');
            }

            $db->transCommit();
            log_message('info', 'Vendor bill confirmed: bill_id=' . $billId . ', posted_entry_id=' . ($postResult['posted_entry_id'] ?? ''));

            return $this->response->setJSON([
                'success' => true,
                'bill_id' => $billId,
                'posted_entry_id' => $postResult['posted_entry_id'] ?? null,
                'message' => 'Vendor bill confirmed and posted to accounting',
            ]);
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Failed to confirm vendor bill: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update vendor bill
     * 
     * Route: POST /vendor-bills/{bill_id}/update
     */
    public function update($billId = null)
    {
        $billModel = new VendorBillModel();
        $bill = $billModel->findByPublicIdOrId($billId);
        if (!$bill) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'Bill not found']);
        }
        $billId = (int)$bill['id'];

        // Only allow editing draft bills
        if (strtolower($bill['status'] ?? '') !== 'draft') {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'error' => 'Only draft bills can be edited',
            ]);
        }

        $data = $this->request->getPost();
        $updateData = [];

        // Allow updating these fields
        $allowedFields = ['memo', 'notes', 'bill_date', 'issue_date'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        $updateData['updated_at'] = date('Y-m-d H:i:s');

        try {
            $billModel->update($billId, $updateData);
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Bill updated',
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Failed to update vendor bill: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Cancel a vendor bill
     * 
     * Route: POST /vendor-bills/{bill_id}/cancel
     */
    public function cancel($billId = null)
    {
        $billModel = new VendorBillModel();
        $bill = $billModel->findByPublicIdOrId($billId);
        if (!$bill) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'Bill not found']);
        }
        $billId = (int)$bill['id'];

        // Only allow cancelling bills that are not paid
        $status = strtolower($bill['status'] ?? '');
        if (in_array($status, ['paid', 'partially_paid'])) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'error' => 'Cannot cancel a bill that has been paid',
            ]);
        }

        try {
            $billModel->update($billId, [
                'status' => 'cancelled',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            log_message('info', 'Vendor bill cancelled: bill_id=' . $billId);

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Bill cancelled',
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Failed to cancel vendor bill: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * List all vendor bills
     * 
     * Route: GET /vendor-bills
     */
    public function index()
    {
        try {
            $db = Database::connect();

            // ── Filter params ──────────────────────────────────────────────
            $vendorId  = $this->request->getGet('vendor_id');
            $dbStatus  = $this->request->getGet('status');      // draft|confirmed|cancelled (DB level)
            $payStatus = $this->request->getGet('pay_status');  // paid|unpaid|partial (computed level)
            $search    = $this->request->getGet('search');
            $perPage   = max(10, min(200, (int)($this->request->getGet('per_page') ?: 25)));
            $page      = max(1, (int)($this->request->getGet('page') ?: 1));

            // ── Sorting params ─────────────────────────────────────────────
            $allowedSorts = [
                'id'           => 'vendor_bills.id',
                'bill_date'    => 'vendor_bills.bill_date',
                'vendor'       => 'vendors.name',
                'total'        => 'vendor_bills.total_amount',
                'paid'         => 'pay_agg.paid_total',
                'balance'      => 'vendor_bills.balance',
                'last_paid'    => 'pay_agg.last_payment_date',
                'payment_count'=> 'pay_agg.payment_count',
            ];
            $sortKey = $this->request->getGet('sort') ?: 'bill_date';
            $sortDir = strtolower($this->request->getGet('dir') ?: 'desc') === 'asc' ? 'ASC' : 'DESC';
            if (!array_key_exists($sortKey, $allowedSorts)) $sortKey = 'bill_date';

            // ── Build query ────────────────────────────────────────────────
            $query = $db->table('vendor_bills');

            // DB-level status filter (only for draft/confirmed/cancelled)
            if ($dbStatus && in_array($dbStatus, ['draft', 'confirmed', 'cancelled', 'paid'])) {
                $query->where('vendor_bills.status', $dbStatus);
            }
            if ($vendorId) {
                $query->where('vendor_bills.vendor_id', (int)$vendorId);
            }
            if ($search) {
                $query->groupStart()
                      ->like('vendor_bills.bill_number', $search)
                      ->orLike('vendor_bills.notes', $search)
                      ->orLike('vendors.name', $search)
                      ->groupEnd();
            }

            $allBills = $query
                ->select('vendor_bills.*, vendors.name as vendor_name,
                    COALESCE(pay_agg.payment_count, 0) as payment_count,
                    COALESCE(pay_agg.paid_total, 0) as paid_total,
                    pay_agg.last_payment_date')
                ->join('vendors', 'vendors.id = vendor_bills.vendor_id', 'left')
                ->join('(
                    SELECT vpa.vendor_bill_id,
                        COUNT(DISTINCT vp.id) as payment_count,
                        SUM(COALESCE(NULLIF(vpa.amount_allocated, 0), vpa.amount, 0)) as paid_total,
                        MAX(vp.payment_date) as last_payment_date
                    FROM vendor_payment_allocations vpa
                    JOIN vendor_payments vp ON vp.id = vpa.payment_id
                    WHERE vp.status = \'posted\'
                    GROUP BY vpa.vendor_bill_id
                ) pay_agg', 'pay_agg.vendor_bill_id = vendor_bills.id', 'left')
                ->orderBy($allowedSorts[$sortKey], $sortDir)
                ->get()
                ->getResultArray();

            // ── Computed pay_status PHP filter ────────────────────────────
            if ($payStatus && in_array($payStatus, ['paid', 'unpaid', 'partial'])) {
                $allBills = array_values(array_filter($allBills, function($row) use ($payStatus) {
                    $total  = (float)($row['total_amount'] ?? 0);
                    $paid   = (float)($row['paid_total']   ?? 0);
                    $bal    = max(0, $total - $paid);
                    $dbSt   = $row['status'] ?? '';
                    if ($dbSt === 'cancelled') return $payStatus === 'cancelled'; // won't hit here but safety
                    $computed = ($dbSt === 'paid' || $bal <= 0.001 && $total > 0)
                        ? 'paid'
                        : ($paid > 0 ? 'partial' : 'unpaid');
                    return $computed === $payStatus;
                }));
            }

            $totalRecords = count($allBills);
            $totalPages   = max(1, (int)ceil($totalRecords / $perPage));
            $page         = min($page, $totalPages);
            $offset       = ($page - 1) * $perPage;
            $bills        = array_slice($allBills, $offset, $perPage);

            // ── Vendors dropdown ──────────────────────────────────────────
            $vendors = $db->table('vendors')
                ->select('id, name')
                ->where('is_active', 1)
                ->orderBy('name')
                ->get()
                ->getResultArray();

            return view('vendor_bills/index', [
                'bills'        => $bills,
                'allBills'     => $allBills,   // needed for footer totals across all filtered rows
                'vendors'      => $vendors,
                'activeStatus' => $dbStatus,
                'activePayStatus' => $payStatus,
                'activeVendor' => $vendorId,
                'searchTerm'   => $search,
                'sortKey'      => $sortKey,
                'sortDir'      => $sortDir,
                'perPage'      => $perPage,
                'page'         => $page,
                'totalPages'   => $totalPages,
                'totalRecords' => $totalRecords,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Failed to load vendor bills list: ' . $e->getMessage());
            return redirect()->to(site_url('purchases'))->with('error', 'Failed to load bills');
        }
    }
}
