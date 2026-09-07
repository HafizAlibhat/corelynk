<?php
namespace App\Controllers;

use App\Models\ProductVendorModel;
use App\Models\ProductModel;

class ProductVendors extends BaseController
{
    // GET /product-vendors/price?product_id=...&vendor_id=...
    public function price()
    {
        $productId = (int)$this->request->getGet('product_id');
        $vendorId = (int)$this->request->getGet('vendor_id');
        $debug = $this->request->getGet('debug') ? true : false;
        if (!$productId || !$vendorId) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'product_id and vendor_id are required']);
        }

        $m = new ProductVendorModel();
        // Primary lookup: only active vendor-product mappings (business logic)
        $row = $m->getVendorCost($productId, $vendorId);
        // If found in product_vendors and last_cost is set, use it (highest priority)
        if ($row && isset($row['last_cost']) && $row['last_cost'] !== null) {
            return $this->response->setJSON(['success' => true, 'cost_price' => (float)$row['last_cost'], 'currency' => 'PKR', 'source' => 'product_vendors']);
        }

        // Fallback: check products table for vendor-specific price saved on product record
        try {
            $pm = new ProductModel();
            $p = $pm->find($productId);
            if ($p && isset($p['vendor_id']) && (int)$p['vendor_id'] === (int)$vendorId) {
                // Use vendor_price if present
                if (isset($p['vendor_price']) && $p['vendor_price'] !== null && $p['vendor_price'] !== '') {
                    return $this->response->setJSON(['success' => true, 'cost_price' => (float)$p['vendor_price'], 'currency' => 'PKR', 'source' => 'products.vendor_price']);
                }
                // Some installations may store cost in cost_price or unit_cost — try those as secondary fallbacks
                if (isset($p['unit_cost']) && $p['unit_cost'] !== null && $p['unit_cost'] !== '') {
                    return $this->response->setJSON(['success' => true, 'cost_price' => (float)$p['unit_cost'], 'currency' => 'PKR', 'source' => 'products.unit_cost']);
                }
                if (isset($p['cost_price']) && $p['cost_price'] !== null && $p['cost_price'] !== '') {
                    return $this->response->setJSON(['success' => true, 'cost_price' => (float)$p['cost_price'], 'currency' => 'PKR', 'source' => 'products.cost_price']);
                }
            }
        } catch (\Throwable $e) {
            // ignore and continue to final not-configured response
        }

        // Nothing found — show diagnostics in debug mode if requested
        if ($debug) {
            $raw = $m->where('product_id', $productId)->where('vendor_id', $vendorId)->first();
            // include product row when debug is requested
            $pm = new ProductModel();
            $prodRaw = $pm->find($productId);
            return $this->response->setJSON(['success' => false, 'message' => 'Vendor price not configured', 'debug' => ['product_vendors' => $raw, 'product' => $prodRaw]]);
        }

        return $this->response->setJSON(['success' => false, 'message' => 'Vendor price not configured']);
    }
}
