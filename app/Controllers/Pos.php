<?php

namespace App\Controllers;

use App\Models\ProductModel;
use App\Models\ProductCategoryModel;
use App\Models\ProductVariantModel;
use App\Models\PosOrderModel;
use App\Models\PosOrderLineModel;
use App\Models\CompanySettingsModel;

class Pos extends BaseController
{
    protected $productModel;
    protected $categoryModel;
    protected $variantModel;
    protected $orderModel;
    protected $orderLineModel;
    protected $companySettings;

    public function __construct()
    {
        $this->productModel   = new ProductModel();
        $this->categoryModel  = new ProductCategoryModel();
        $this->variantModel   = new ProductVariantModel();
        $this->orderModel     = new PosOrderModel();
        $this->orderLineModel = new PosOrderLineModel();
        $this->companySettings = new CompanySettingsModel();
    }

    /**
     * Main POS Register screen
     */
    public function index()
    {
        // Get all active categories
        $categories = $this->categoryModel
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->findAll();

        // Get all active products with sale_price
        $products = $this->productModel
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->findAll();

        // Group products by category
        $productsByCategory = [];
        foreach ($products as $p) {
            $catId = $p['category_id'] ?? 0;
            $productsByCategory[$catId][] = $p;
        }

        // Company settings for receipt
        $company = $this->companySettings->first();

        $data = [
            'title'              => 'POS Register',
            'categories'         => $categories,
            'products'           => $products,
            'productsByCategory' => $productsByCategory,
            'company'            => $company,
            'orderNumber'        => $this->orderModel->nextOrderNumber(),
        ];

        return view('pos/register', $data);
    }

    /**
     * AJAX: Get products by category (optional filter)
     */
    public function getProducts()
    {
        $categoryId = $this->request->getGet('category_id');
        
        $builder = $this->productModel->where('is_active', 1);
        
        if ($categoryId && $categoryId !== 'all') {
            $builder->where('category_id', $categoryId);
        }

        $products = $builder->orderBy('name', 'ASC')->findAll();
        
        return $this->response->setJSON(['products' => $products]);
    }

    /**
     * AJAX: Search products by name or barcode
     */
    public function searchProducts()
    {
        $q = $this->request->getGet('q');
        if (!$q || strlen($q) < 1) {
            return $this->response->setJSON(['products' => []]);
        }

        $products = $this->productModel
            ->where('is_active', 1)
            ->groupStart()
                ->like('name', $q)
                ->orLike('code', $q)
                ->orLike('barcode', $q)
                ->orLike('sku', $q)
            ->groupEnd()
            ->orderBy('name', 'ASC')
            ->findAll(20);

        return $this->response->setJSON(['products' => $products]);
    }

    /**
     * AJAX: Save/complete an order
     */
    public function saveOrder()
    {
        $json = $this->request->getJSON(true);
        
        if (empty($json['items'])) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'No items in order'
            ]);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        // Create the order
        $orderData = [
            'order_number'   => $json['order_number'] ?? $this->orderModel->nextOrderNumber(),
            'order_type'     => $json['order_type'] ?? 'dine_in',
            'customer_name'  => $json['customer_name'] ?? 'Walk-in',
            'table_number'   => $json['table_number'] ?? null,
            'subtotal'       => $json['subtotal'] ?? 0,
            'tax_rate'       => $json['tax_rate'] ?? 0,
            'tax_amount'     => $json['tax_amount'] ?? 0,
            'discount_amount'=> $json['discount_amount'] ?? 0,
            'discount_type'  => $json['discount_type'] ?? 'fixed',
            'total'          => $json['total'] ?? 0,
            'amount_paid'    => $json['amount_paid'] ?? 0,
            'change_due'     => $json['change_due'] ?? 0,
            'payment_method' => $json['payment_method'] ?? 'cash',
            'status'         => $json['status'] ?? 'paid',
            'notes'          => $json['notes'] ?? '',
            'cashier_id'     => session()->get('user_id'),
        ];

        $this->orderModel->insert($orderData);
        $orderId = $this->orderModel->getInsertID();

        // Save line items
        foreach ($json['items'] as $item) {
            $this->orderLineModel->insert([
                'pos_order_id' => $orderId,
                'product_id'   => $item['product_id'] ?? null,
                'variant_id'   => $item['variant_id'] ?? null,
                'product_name' => $item['name'] ?? 'Unknown',
                'variant_name' => $item['variant_name'] ?? '',
                'quantity'     => $item['qty'] ?? 1,
                'unit_price'   => $item['price'] ?? 0,
                'discount'     => $item['discount'] ?? 0,
                'line_total'   => $item['line_total'] ?? 0,
                'notes'        => $item['notes'] ?? '',
            ]);
        }

        $db->transComplete();

        if ($db->transStatus()) {
            return $this->response->setJSON([
                'success'  => true,
                'order_id' => $orderId,
                'message'  => 'Order saved successfully'
            ]);
        }

        return $this->response->setJSON([
            'success' => false,
            'message' => 'Failed to save order'
        ]);
    }

    /**
     * AJAX: Get order details for receipt printing
     */
    public function getOrder(int $id)
    {
        $order = $this->orderModel->find($id);
        if (!$order) {
            return $this->response->setJSON(['success' => false, 'message' => 'Order not found']);
        }

        $lines = $this->orderLineModel->getByOrder($id);
        $company = $this->companySettings->first();

        return $this->response->setJSON([
            'success' => true,
            'order'   => $order,
            'lines'   => $lines,
            'company' => $company,
        ]);
    }

    /**
     * AJAX: Get today's orders list
     */
    public function orders()
    {
        $orders = $this->orderModel->todaysOrders();
        $total  = $this->orderModel->todaysSalesTotal();

        return $this->response->setJSON([
            'orders'      => $orders,
            'daily_total' => $total,
        ]);
    }

    /**
     * AJAX: Void an order
     */
    public function voidOrder(int $id)
    {
        $order = $this->orderModel->find($id);
        if (!$order) {
            return $this->response->setJSON(['success' => false, 'message' => 'Order not found']);
        }

        $this->orderModel->update($id, ['status' => 'voided']);

        return $this->response->setJSON(['success' => true, 'message' => 'Order voided']);
    }

    /**
     * AJAX: Get next order number
     */
    public function nextOrderNumber()
    {
        return $this->response->setJSON([
            'order_number' => $this->orderModel->nextOrderNumber()
        ]);
    }

    /**
     * AJAX: Get receipt HTML for printing
     */
    public function receipt(int $id)
    {
        $order = $this->orderModel->find($id);
        if (!$order) {
            return $this->response->setJSON(['success' => false]);
        }

        $lines   = $this->orderLineModel->getByOrder($id);
        $company = $this->companySettings->first();

        return view('pos/receipt', [
            'order'   => $order,
            'lines'   => $lines,
            'company' => $company,
        ]);
    }
}
