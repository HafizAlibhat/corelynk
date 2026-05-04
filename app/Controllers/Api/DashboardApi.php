<?php

namespace App\Controllers\Api;

use App\Models\ProductModel;
use App\Models\VendorModel;
use App\Models\CustomerModel;
use App\Models\CustomerInvoiceModel;
use App\Models\GRNModel;

/**
 * GET /api/dashboard — Aggregate summary stats for the application dashboard.
 *
 * Returns counts + simple financial totals so an external consumer
 * (mobile app, partner portal, reporting tool) can build a summary view
 * without a separate query per entity.
 */
class DashboardApi extends BaseApiController
{
    /**
     * GET /api/dashboard
     *
     * Response:
     * {
     *   "success": true,
     *   "data": {
     *     "products":  { "total": 120, "active": 110 },
     *     "vendors":   { "total": 45,  "active": 40  },
     *     "customers": { "total": 300, "active": 290 },
     *     "invoices":  { "total": 500, "this_month": 18 },
     *     "grn":       { "total": 200, "this_month": 5  },
     *     "generated_at": "2025-01-01 12:00:00"
     *   },
     *   "message": "OK"
     * }
     */
    public function index(): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }
        if (!$this->requirePermission('dashboard', 'read')) {
            return $this->response;
        }

        $monthStart = date('Y-m-01 00:00:00');

        // Products
        $productModel = new ProductModel();
        $totalProducts  = $productModel->countAll();
        $activeProducts = $productModel->where('is_active', 1)->countAllResults();

        // Vendors
        $vendorModel = new VendorModel();
        $totalVendors  = $vendorModel->countAll();
        $activeVendors = $vendorModel->where('is_active', 1)->countAllResults();

        // Customers
        $customerModel = new CustomerModel();
        $totalCustomers  = $customerModel->countAll();
        $activeCustomers = $customerModel->where('is_active', 1)->countAllResults();

        // Customer invoices
        $invoiceModel      = new CustomerInvoiceModel();
        $totalInvoices     = $invoiceModel->countAll();
        $monthlyInvoices   = $invoiceModel->where('created_at >=', $monthStart)->countAllResults();

        // GRN (Goods Received Notes)
        $grnModel    = new GRNModel();
        $totalGrn    = $grnModel->countAll();
        $monthlyGrn  = $grnModel->where('created_at >=', $monthStart)->countAllResults();

        return $this->success([
            'products'  => ['total' => $totalProducts,  'active' => $activeProducts],
            'vendors'   => ['total' => $totalVendors,   'active' => $activeVendors],
            'customers' => ['total' => $totalCustomers, 'active' => $activeCustomers],
            'invoices'  => ['total' => $totalInvoices,  'this_month' => $monthlyInvoices],
            'grn'       => ['total' => $totalGrn,       'this_month' => $monthlyGrn],
            'generated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
