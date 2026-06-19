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

        // Enrich bill header fields that are not stored directly on vendor_bills.
        if (!empty($bill['vendor_id']) && empty($bill['vendor_name'])) {
            $vendor = $db->table('vendors')
                ->select('id, vendor_code, name')
                ->where('id', (int)$bill['vendor_id'])
                ->get()
                ->getRowArray();
            if (!empty($vendor['name'])) {
                $bill['vendor_name'] = $vendor['name'];
            }
            if (!empty($vendor['vendor_code'])) {
                $bill['vendor_code'] = $vendor['vendor_code'];
            }
        }

        if (!empty($bill['po_id']) && empty($bill['po_number']) && $db->tableExists('purchase_orders')) {
            $po = $db->table('purchase_orders')
                ->select('id, po_number')
                ->where('id', (int)$bill['po_id'])
                ->get()
                ->getRowArray();
            if (!empty($po['po_number'])) {
                $bill['po_number'] = $po['po_number'];
            }
        }

        if (empty($bill['vendor_name']) && !empty($bill['vendor_id'])) {
            $bill['vendor_name'] = 'Vendor #' . (int)$bill['vendor_id'];
        }

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
            $resolveStoredImageUrl = function (?string $filename, string $folder): ?string {
                $filename = trim((string)($filename ?? ''));
                if ($filename === '') {
                    return null;
                }
                if (preg_match('#^https?://#i', $filename)) {
                    return $filename;
                }

                $normalized = ltrim(str_replace('\\', '/', $filename), '/');
                $normalized = preg_replace('#^(public/)?uploads/' . preg_quote($folder, '#') . '/#i', '', $normalized);
                $normalized = ltrim((string)$normalized, '/');
                if ($normalized === '') {
                    return null;
                }

                $candidateA = rtrim((string)FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $normalized;
                $candidateB = rtrim((string)dirname(rtrim((string)FCPATH, DIRECTORY_SEPARATOR)), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $normalized;

                if (is_file($candidateA) || is_file($candidateB)) {
                    return base_url('uploads/' . $folder . '/' . $normalized);
                }

                return null;
            };

            $extractFirstProductImage = function ($raw): string {
                if (empty($raw)) {
                    return '';
                }
                if (is_array($raw)) {
                    $first = $raw[0] ?? null;
                    if (is_array($first)) {
                        return trim((string)($first['path'] ?? $first['file'] ?? $first['url'] ?? $first['name'] ?? ''));
                    }
                    return trim((string)($first ?? ''));
                }
                if (is_string($raw)) {
                    $decoded = json_decode($raw, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && !empty($decoded[0])) {
                        $first = $decoded[0];
                        if (is_array($first)) {
                            return trim((string)($first['path'] ?? $first['file'] ?? $first['url'] ?? $first['name'] ?? ''));
                        }
                        return trim((string)$first);
                    }
                    return trim($raw);
                }
                return '';
            };

            // Fetch all product IDs from lines
            $productIds = array_values(array_filter(array_unique(array_map(function($l){ return $l['product_id'] ?? null; }, $lines))));
            $variantIds = array_values(array_filter(array_unique(array_map(function($l){ return $l['variant_id'] ?? null; }, $lines))));
            
            // Fetch products with images
            $prodMap = [];
            if (!empty($productIds)) {
                $products = $db->table('products')
                    ->select('id, public_id, name, code, sku, description, images, detailed_type, unit')
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
                    $line['product_public_id'] = $p['public_id'] ?? null;
                    $line['product_name'] = $p['name'] ?? null;
                    $line['product_code'] = $p['code'] ?? $p['sku'] ?? null;
                    $line['product_description'] = $p['description'] ?? null;
                    $line['product_images'] = $p['images'] ?? null;
                    $line['product_detailed_type'] = $p['detailed_type'] ?? null;
                    $line['product_unit'] = $p['unit'] ?? null;
                }

                $thumbnailUrl = null;
                if (!empty($line['variant_image'])) {
                    $thumbnailUrl = $resolveStoredImageUrl((string)$line['variant_image'], 'variants');
                }

                if (!$thumbnailUrl && !empty($line['product_images'])) {
                    $productImageFile = $extractFirstProductImage($line['product_images']);
                    if ($productImageFile !== '') {
                        $thumbnailUrl = $resolveStoredImageUrl($productImageFile, 'products');
                    }
                }

                $line['thumbnail_url'] = $thumbnailUrl ?: base_url('assets/images/no-image.png');
                $line['image_urls'] = $thumbnailUrl ? [$thumbnailUrl] : [$line['thumbnail_url']];
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
                if ($db->tableExists('purchase_grn_lines')) {
                    $grnRefs = $db->table('purchase_grns g')
                        ->select('g.id, g.public_id, g.grn_number, g.received_at')
                        ->join('(SELECT grn_id, COUNT(*) AS line_count FROM purchase_grn_lines GROUP BY grn_id) gl', 'gl.grn_id = g.id', 'inner', false)
                        ->where('g.po_id', (int)$bill['po_id'])
                        ->orderBy('g.id', 'DESC')
                        ->get()
                        ->getResultArray();
                } else {
                    $grnRefs = $db->table('purchase_grns')
                        ->select('id, public_id, grn_number, received_at')
                        ->where('po_id', (int)$bill['po_id'])
                        ->orderBy('id', 'DESC')
                        ->get()
                        ->getResultArray();
                }
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
            // If enrichment fails, still return lines with image_urls initialized
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
     * Get payment transactions for a vendor bill
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

    /**
     * Confirm a draft bill
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
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'Only draft bills can be confirmed']);
        }

        $db = Database::connect();
        $db->transBegin();
        try {
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

    /**
     * Cancel a bill
     */
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

    public function printView($billId = null)
    {
        $db = \Config\Database::connect();
        $billModel = new \App\Models\VendorBillModel();
        $bill = $billModel->findByPublicIdOrId($billId);
        if (!$bill) {
            return redirect()->back()->with('error', 'Vendor bill not found');
        }
        $billId = (int)$bill['id'];

        $vendor = [];
        try {
            $vendor = $db->table('vendors')->where('id', (int)($bill['vendor_id'] ?? 0))->get()->getRowArray() ?: [];
        } catch (\Throwable $_) {}

        $lineModel = new \App\Models\VendorBillLineModel();
        $lines = $lineModel->where('vendor_bill_id', $billId)->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->findAll();

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

        $currency = strtoupper(trim((string) ($bill['currency'] ?? ($company['base_currency'] ?? 'USD'))));
        $symbols = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'PKR' => '₨', 'INR' => '₹'];
        $sym = $symbols[$currency] ?? $currency;
        $fmt = fn($value) => $sym . ' ' . number_format((float) $value, 2);

        $subtotal = (float) ($bill['subtotal'] ?? 0);
        $total = (float) ($bill['total'] ?? 0);
        $billNumber = esc($bill['vendor_bill_number'] ?? ('VB-' . $billId));
        $billDate = '';
        $rawDate = trim((string) ($bill['bill_date'] ?? ($bill['created_at'] ?? '')));
        if ($rawDate && strpos($rawDate, '0000') === false) {
            $ts = strtotime($rawDate);
            if ($ts) {
                $billDate = date('d-m-Y', $ts);
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
<title>Vendor Bill <?= $billNumber ?></title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:Arial,sans-serif;font-size:12px;color:#1e293b;background:#f8fafc;padding:24px}
  .grn-doc{max-width:1100px;margin:0 auto}
  .grn-hero{background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);border-radius:.75rem .75rem 0 0;padding:1.6rem 2rem 1.4rem;color:#fff;position:relative;overflow:hidden}
  .grn-hero::after{content:'BILL';position:absolute;right:-1rem;top:50%;transform:translateY(-50%);font-size:6rem;font-weight:900;opacity:.04;pointer-events:none;user-select:none;line-height:1}
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
    <div class="grn-doc-type">Vendor Bill</div>
    <div class="grn-hero-num"><?= $billNumber ?></div>
    <div class="grn-hero-sub"><?= $companyName ?></div>
    <div class="grn-hero-actions no-print">
      <button type="button" class="grn-hero-btn" onclick="window.print()">Print</button>
      <button type="button" class="grn-hero-btn" onclick="window.close()">Close</button>
    </div>
  </div>

  <div class="grn-facts">
    <div class="grn-fact"><div class="grn-fact-lbl">Vendor</div><div class="grn-fact-val"><?= $vendorName ?></div></div>
    <div class="grn-fact"><div class="grn-fact-lbl">Bill Date</div><div class="grn-fact-val"><?= esc($billDate ?: '-') ?></div></div>
    <div class="grn-fact"><div class="grn-fact-lbl">Currency</div><div class="grn-fact-val"><?= esc($currency) ?></div></div>
    <div class="grn-fact"><div class="grn-fact-lbl">Lines</div><div class="grn-fact-val"><?= number_format(count($printLines), 0) ?></div></div>
  </div>

  <div class="grn-sec">
    <div class="grn-sec-hd">Bill Lines<span class="grn-sec-badge"><?= number_format(count($printLines), 0) ?></span></div>
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
