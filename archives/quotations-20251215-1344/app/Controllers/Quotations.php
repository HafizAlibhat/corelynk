<?php
namespace App\Controllers;

use App\Models\QuotationModel;
use App\Models\QuotationLineModel;
use App\Models\ProductModel;
use App\Models\CustomerModel;
use App\Models\SalesOrderModel;

class Quotations extends BaseController
{
    protected $model;
    protected $lineModel;
    protected $productModel;
    protected $customerModel;
    protected $salesOrderModel;

    public function __construct()
    {
        $this->model = new QuotationModel();
        $this->lineModel = new QuotationLineModel();
        $this->productModel = new ProductModel();
        $this->customerModel = new CustomerModel();
        $this->salesOrderModel = new SalesOrderModel();
        helper(['form','url']);
    }

    // FULL ORIGINAL CONTROLLER ARCHIVED - trimmed in archive for brevity in this file listing
}
