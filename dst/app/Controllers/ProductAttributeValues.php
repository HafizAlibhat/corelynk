<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\ProductAttributeModel;
use App\Models\ProductAttributeValueModel;

class ProductAttributeValues extends BaseController
{
    public function index()
    {
        $this->requireAuth();

        $attrModel = new ProductAttributeModel();
        $valModel  = new ProductAttributeValueModel();

        $rows = $valModel
            ->select('product_attribute_values.*, product_attributes.name as attribute_name')
            ->join('product_attributes', 'product_attributes.id = product_attribute_values.attribute_id', 'left')
            ->orderBy('product_attributes.name', 'ASC')
            ->orderBy('product_attribute_values.sort_order', 'ASC')
            ->orderBy('product_attribute_values.value', 'ASC')
            ->findAll();

        $attrs = $attrModel->orderBy('name', 'ASC')->findAll();

        return view('product_attribute_values/index', [
            'rows' => $rows,
            'attributes' => $attrs,
        ]);
    }

    public function create()
    {
        $this->requireAuth();

        $attrModel = new ProductAttributeModel();
        $attrs = $attrModel->orderBy('name', 'ASC')->findAll();

        return view('product_attribute_values/form', [
            'attributes' => $attrs,
            'row' => null,
        ]);
    }

    public function store()
    {
        $this->requireAuth();

        $valModel = new ProductAttributeValueModel();

        $data = [
            'attribute_id' => (int) $this->request->getPost('attribute_id'),
            'value'        => trim((string) $this->request->getPost('value')),
            'code'         => strtoupper(trim((string) $this->request->getPost('code'))),
            'sort_order'   => (int) ($this->request->getPost('sort_order') ?? 0),
            'is_active'    => $this->request->getPost('is_active') ? 1 : 0,
        ];

        if (! $valModel->insert($data)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $valModel->errors() ?: ['Failed to create value']));
        }

        return redirect()->to('/product-attribute-values')->with('success', 'Attribute value created');
    }

    public function edit($id = null)
    {
        $this->requireAuth();

        $valModel = new ProductAttributeValueModel();
        $row = $id ? $valModel->find((int)$id) : null;
        if (! $row) {
            return redirect()->to('/product-attribute-values')->with('error', 'Value not found');
        }

        $attrModel = new ProductAttributeModel();
        $attrs = $attrModel->orderBy('name', 'ASC')->findAll();

        return view('product_attribute_values/form', [
            'attributes' => $attrs,
            'row' => $row,
        ]);
    }

    public function update($id = null)
    {
        $this->requireAuth();

        $valModel = new ProductAttributeValueModel();
        $row = $id ? $valModel->find((int)$id) : null;
        if (! $row) {
            return redirect()->to('/product-attribute-values')->with('error', 'Value not found');
        }

        $data = [
            'attribute_id' => (int) $this->request->getPost('attribute_id'),
            'value'        => trim((string) $this->request->getPost('value')),
            'code'         => strtoupper(trim((string) $this->request->getPost('code'))),
            'sort_order'   => (int) ($this->request->getPost('sort_order') ?? 0),
            'is_active'    => $this->request->getPost('is_active') ? 1 : 0,
        ];

        if (! $valModel->update((int)$id, $data)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $valModel->errors() ?: ['Failed to update value']));
        }

        return redirect()->to('/product-attribute-values')->with('success', 'Attribute value updated');
    }

    public function delete($id = null)
    {
        $this->requireAuth();

        if (! $id) {
            return redirect()->to('/product-attribute-values')->with('error', 'Invalid value');
        }

        $valModel = new ProductAttributeValueModel();
        $valModel->delete((int)$id);

        return redirect()->to('/product-attribute-values')->with('success', 'Attribute value deleted');
    }

    /**
     * JSON helper for future use; does not change existing behavior.
     * GET: /product-attribute-values/by-attribute?attribute_id=123
     */
    public function byAttribute()
    {
        $this->requireAuth();

        $attributeId = (int) ($this->request->getGet('attribute_id') ?? 0);
        if (! $attributeId) {
            return $this->response->setJSON([]);
        }

        $valModel = new ProductAttributeValueModel();
        $rows = $valModel->where('attribute_id', $attributeId)->where('is_active', 1)->orderBy('sort_order', 'ASC')->orderBy('value', 'ASC')->findAll();
        return $this->response->setJSON($rows);
    }
}
