<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\ProductAttributeAssignmentModel;
use App\Models\ProductAttributeModel;
use App\Models\ProductModel;

class ProductAttributeAssignments extends BaseController
{
    public function index()
    {
        $this->requireAuth();

        $assignModel = new ProductAttributeAssignmentModel();

        $rows = $assignModel
            ->select('product_attribute_assignments.*, products.name as product_name, products.code as product_code, product_attributes.name as attribute_name')
            ->join('products', 'products.id = product_attribute_assignments.product_id', 'left')
            ->join('product_attributes', 'product_attributes.id = product_attribute_assignments.attribute_id', 'left')
            ->orderBy('products.name', 'ASC')
            ->orderBy('product_attribute_assignments.position', 'ASC')
            ->findAll();

        return view('product_attribute_assignments/index', ['rows' => $rows]);
    }

    public function create()
    {
        $this->requireAuth();

        $productModel = new ProductModel();
        $attrModel = new ProductAttributeModel();

        $products = $productModel->orderBy('name', 'ASC')->findAll();
        $attrs = $attrModel->orderBy('name', 'ASC')->findAll();

        return view('product_attribute_assignments/form', [
            'row' => null,
            'products' => $products,
            'attributes' => $attrs,
        ]);
    }

    public function store()
    {
        $this->requireAuth();

        $assignModel = new ProductAttributeAssignmentModel();

        $data = [
            'product_id' => (int) $this->request->getPost('product_id'),
            'attribute_id' => (int) $this->request->getPost('attribute_id'),
            'position' => (int) ($this->request->getPost('position') ?? 0),
        ];

        if (! $assignModel->insert($data)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $assignModel->errors() ?: ['Failed to create assignment']));
        }

        return redirect()->to('/product-attribute-assignments')->with('success', 'Assignment created');
    }

    public function edit($id = null)
    {
        $this->requireAuth();

        $assignModel = new ProductAttributeAssignmentModel();
        $row = $id ? $assignModel->find((int)$id) : null;
        if (! $row) {
            return redirect()->to('/product-attribute-assignments')->with('error', 'Assignment not found');
        }

        $productModel = new ProductModel();
        $attrModel = new ProductAttributeModel();

        $products = $productModel->orderBy('name', 'ASC')->findAll();
        $attrs = $attrModel->orderBy('name', 'ASC')->findAll();

        return view('product_attribute_assignments/form', [
            'row' => $row,
            'products' => $products,
            'attributes' => $attrs,
        ]);
    }

    public function update($id = null)
    {
        $this->requireAuth();

        $assignModel = new ProductAttributeAssignmentModel();
        $row = $id ? $assignModel->find((int)$id) : null;
        if (! $row) {
            return redirect()->to('/product-attribute-assignments')->with('error', 'Assignment not found');
        }

        $data = [
            'product_id' => (int) $this->request->getPost('product_id'),
            'attribute_id' => (int) $this->request->getPost('attribute_id'),
            'position' => (int) ($this->request->getPost('position') ?? 0),
        ];

        if (! $assignModel->update((int)$id, $data)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $assignModel->errors() ?: ['Failed to update assignment']));
        }

        return redirect()->to('/product-attribute-assignments')->with('success', 'Assignment updated');
    }

    public function delete($id = null)
    {
        $this->requireAuth();

        if (! $id) {
            return redirect()->to('/product-attribute-assignments')->with('error', 'Invalid assignment');
        }

        $assignModel = new ProductAttributeAssignmentModel();
        $assignModel->delete((int)$id);

        return redirect()->to('/product-attribute-assignments')->with('success', 'Assignment deleted');
    }
}
