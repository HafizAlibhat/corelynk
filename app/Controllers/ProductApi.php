<?php

namespace App\Controllers;

use App\Models\ProductModel;
use CodeIgniter\RESTful\ResourceController;

class ProductApi extends ResourceController
{
    protected $productModel;

    public function __construct()
    {
        $this->productModel = new ProductModel();
    }

    /**
     * Return all products as JSON (API endpoint for AJAX/mobile apps)
     */
    public function index()
    {
        $products = $this->productModel
            ->select('id, name, sku, code, description, category_id, stock_quantity, unit_cost, sale_price, created_at')
            ->where('is_active', 1)
            ->findAll(100);

        return $this->respond([
            'status' => 'success',
            'data' => $products
        ]);
    }

    /**
     * Return a single product as JSON (API endpoint for AJAX/mobile apps)
     */
    public function show($id = null)
    {
        if (!$id) {
            return $this->failNotFound('Product not found');
        }

        $product = $this->productModel
            ->select('id, name, sku, code, description, category_id, stock_quantity, unit_cost, sale_price, weight, created_at, updated_at')
            ->where([
                'id' => $id,
                'is_active' => 1
            ])
            ->first();

        if (!$product) {
            return $this->failNotFound('Product not found');
        }

        return $this->respond([
            'status' => 'success',
            'data' => $product
        ]);
    }
}
