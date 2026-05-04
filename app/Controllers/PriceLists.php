<?php
namespace App\Controllers;

use App\Models\PriceListModel;

class PriceLists extends BaseController
{
    protected $model;
    public function __construct()
    {
        $this->model = new PriceListModel();
    }

    public function index()
    {
        $data['priceLists'] = $this->model->orderBy('created_at','desc')->findAll();
        return view('price_lists/index', $data);
    }

    public function manage($id = null)
    {
        // Basic stub: show a manage page (create/edit)
        $data = [];
        if ($id) {
            $data['priceList'] = $this->model->find($id);
        }
        return view('price_lists/manage', $data);
    }
}
