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
     * Get vendor bill details - CLEAN VERSION
     * Route: GET /vendor-bills/{bill_id}
     */
    public function show($billId = null)
    {
        $billModel = new VendorBillModel();
        $bill = $billModel->findByPublicIdOrId($billId);
        if (!$bill) {
            if ($this->request->isAJAX() || strpos($this->request->getHeaderLine('Accept'), 'application/json') !== false) {
                return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'Bill not found']);
            }
            return redirect()->to(site_url('purchases'))->with('error', 'Bill not found');
        }
        $billId = (int)$bill['id'];

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

        // CRITICAL: Initialize image_urls on ALL lines BEFORE processing
        foreach ($lines as &$line) {
            $line['image_urls'] = [];
        }
        unset($line);

        $paymentHistory = [];
        $grnRefs = [];
        $relatedBills = [];

        try {
            // Fetch all product IDs from lines
            $productIds = array_values(array_filter(array_unique(array_map(function($l){ return $l['product_id'] ?? null; }, $lines))));
            $variantIds = array_values(array_filter(array_unique(array_map(function($l){ return $l['variant_id'] ?? null; }, $lines))));
            
            // Fetch products with images
            $prodMap = [];
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

            // Fetch variants with images
            $variantMap = [];
            if (!empty($variantIds)) {
                $variants = $db->table('product_variants')
                    ->select('id, product_id, art_number, name, attributes, image')
                    ->whereIn('id', $variantIds)
                    ->get()
                    ->getResultArray();
                foreach ($variants as $v) {
                    $variantMap[(int)$v['id']] = $v;
                }
            }

            // Helper function to build image URLs
            $buildImageUrls = function(string $filename, string $folder): array {
                if (empty($filename)) return [];
                $filename = trim($filename);
                if (preg_match('#^https?://#i', $filename)) {
                    return [$filename];
                }
                $filename = ltrim(str_replace('\\', '/', $filename), '/');
                $filename = preg_replace('#^(public/)?uploads/' . preg_quote($folder, '#') . '/#i', '', $filename);
                $filename = ltrim($filename, '/');
                if (empty($filename)) return [];
                
                // Return both possible URL locations (browser will try first available)
                return [
                    base_url('uploads/' . $folder . '/' . $filename),
                    base_url('public/uploads/' . $folder . '/' . $filename)
                ];
            };

            // Enrich lines with product/variant data and build image URLs
            foreach ($lines as &$line) {
                // Merge variant data if available
                if (!empty($line['variant_id']) && isset($variantMap[(int)$line['variant_id']])) {
                    $v = $variantMap[(int)$line['variant_id']];
                    $line['variant_art_number'] = $v['art_number'] ?? null;
                    $line['variant_name'] = $v['name'] ?? null;
                    $line['variant_attributes'] = $v['attributes'] ?? null;
                    $line['variant_image'] = $v['image'] ?? null;
                }

                // Merge product data if available
                if (!empty($line['product_id']) && isset($prodMap[(int)$line['product_id']])) {
                    $p = $prodMap[(int)$line['product_id']];
                    $line['product_name'] = $p['name'] ?? null;
                    $line['product_code'] = $p['code'] ?? $p['sku'] ?? null;
                    $line['product_description'] = $p['description'] ?? null;
                    $line['product_images'] = $p['images'] ?? null;
                    $line['product_detailed_type'] = $p['detailed_type'] ?? null;
                    $line['product_unit'] = $p['unit'] ?? null;
                }

                // Build image URLs from variant or product images
                $imageUrls = [];
                
                if (!empty($line['variant_image'])) {
                    $imageUrls = array_merge($imageUrls, $buildImageUrls($line['variant_image'], 'variants'));
                }
                
                if (empty($imageUrls) && !empty($line['product_images'])) {
                    $productImageFile = '';
                    $raw = $line['product_images'];
                    
                    if (is_string($raw)) {
                        $decoded = json_decode($raw, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && !empty($decoded[0])) {
                            $first = $decoded[0];
                            if (is_array($first)) {
                                $productImageFile = (string)($first['path'] ?? $first['file'] ?? $first['url'] ?? $first['name'] ?? '');
                            } else {
                                $productImageFile = (string)$first;
                            }
                        } else {
                            $productImageFile = $raw;
                        }
                    } elseif (is_array($raw) && !empty($raw[0])) {
                        $first = $raw[0];
                        if (is_array($first)) {
                            $productImageFile = (string)($first['path'] ?? $first['file'] ?? $first['url'] ?? $first['name'] ?? '');
                        } else {
                            $productImageFile = (string)$first;
                        }
                    }
                    
                    if (!empty($productImageFile)) {
                        $imageUrls = array_merge($imageUrls, $buildImageUrls($productImageFile, 'products'));
                    }
                }
                
                $line['image_urls'] = array_values(array_unique(array_filter($imageUrls)));
            }
            unset($line);

            // Get payment history
            $paymentHistory = $db->query("
                SELECT vp.id, vp.payment_date, vp.payment_method, vp.amount, vpa.amount as allocation_amount
                FROM vendor_payment_allocations vpa
                JOIN vendor_payments vp ON vp.id = vpa.payment_id
                WHERE vpa.vendor_bill_id = ? AND vp.status = 'posted'
                ORDER BY vp.payment_date ASC
            ", [$billId])->getResultArray();

            // Get related GRNs
            if (!empty($bill['po_id']) && $db->tableExists('purchase_grns')) {
                $grnRefs = $db->table('purchase_grns')
                    ->select('id, public_id, grn_number, received_at')
                    ->where('po_id', (int)$bill['po_id'])
                    ->orderBy('id', 'DESC')
                    ->get()
                    ->getResultArray();
            }

            // Get related bills
            if (!empty($bill['po_id'])) {
                $relatedBills = $db->table('vendor_bills')
                    ->select('id, public_id, bill_number, status, total_amount, balance, bill_date')
                    ->where('po_id', (int)$bill['po_id'])
                    ->where('id !=', $billId)
                    ->where('status !=', 'cancelled')
                    ->orderBy('id', 'DESC')
                    ->get()
                    ->getResultArray();
            }

        } catch (\Throwable $ex) {
            // If enrichment fails, still return lines with image_urls initialized (already done above)
        }

        // Return JSON for AJAX or view for browser
        if ($this->request->isAJAX() || strpos($this->request->getHeaderLine('Accept'), 'application/json') !== false) {
            return $this->response->setJSON([
                'success' => true,
                'data' => [
                    'bill' => $bill,
                    'lines' => $lines,
                    'paymentHistory' => $paymentHistory,
                    'grnRefs' => $grnRefs,
                    'relatedBills' => $relatedBills,
                ],
            ]);
        }

        return view('vendor_bills/show', ['defaultCurrency' => 'PKR']);
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
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => 'Server error']);
        }
    }

        public function confirm($billId = null)
        {
            $billModel = new VendorBillModel();
            $bill = $billModel->findByPublicIdOrId($billId);
            if (!$bill) {
                return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'Bill not found']);
            }
            $billId = (int)$bill['id'];

            if (strtolower($bill['status'] ?? '') !== 'draft') {
                return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'Only draft bills can be confirmed']);
            }

            $db = Database::connect();
            $db->transBegin();
            try {
                $payload = $this->request->getJSON(true) ?: $this->request->getPost();
                $billNumber = $bill['bill_number'];
                if (!$billNumber) {
                    $billNumber = 'VB-' . date('YmdHis') . '-' . $billId;
                }
            
                $updateData = [
                    'status' => 'confirmed',
                    'bill_number' => $billNumber,
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
                $billModel->update($billId, $updateData);

                $postingService = new AccountingPostingService($db);
                $postResult = $postingService->postVendorBill($billId);

                if (!$postResult['success']) {
                    throw new \RuntimeException('Accounting posting failed');
                }

                $db->transCommit();
                return $this->response->setJSON(['success' => true, 'bill_id' => $billId, 'message' => 'Bill confirmed']);
            } catch (\Throwable $e) {
                $db->transRollback();
                return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => $e->getMessage()]);
            }
        }

                        public function cancel($billId = null)
                        {
                            $billModel = new VendorBillModel();
                            $bill = $billModel->findByPublicIdOrId($billId);
                            if (!$bill) {
                                return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'Bill not found']);
                            }
                            $billId = (int)$bill['id'];

                            $status = strtolower($bill['status'] ?? '');
                            if (in_array($status, ['paid', 'partially_paid'])) {
                                return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'Cannot cancel paid bills']);
                            }

                            try {
                                $billModel->update($billId, ['status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')]);
                                return $this->response->setJSON(['success' => true, 'message' => 'Bill cancelled']);
                            } catch (\Throwable $e) {
                                return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => $e->getMessage()]);
                            }
                        }
                    }
                }

                $db->transCommit();
                return $this->response->setJSON(['success' => true, 'bill_id' => $billId, 'message' => 'Bill confirmed']);
            } catch (\Throwable $e) {
                $db->transRollback();
                return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => $e->getMessage()]);
            }
        }

        public function cancel($billId = null)
        {
            $billModel = new VendorBillModel();
            $bill = $billModel->findByPublicIdOrId($billId);
            if (!$bill) {
                return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'Bill not found']);
            }
            $billId = (int)$bill['id'];

            $status = strtolower($bill['status'] ?? '');
            if (in_array($status, ['paid', 'partially_paid'])) {
                return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'Cannot cancel paid bills']);
            }

            try {
                $billModel->update($billId, ['status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')]);
                return $this->response->setJSON(['success' => true, 'message' => 'Bill cancelled']);
            } catch (\Throwable $e) {
                return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => $e->getMessage()]);
            }
        }
}
