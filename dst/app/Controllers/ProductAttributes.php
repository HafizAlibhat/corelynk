<?php
namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\ProductAttributeModel;
use Config\Database;

class ProductAttributes extends BaseController
{
    protected $attributes;

    public function __construct()
    {
        $this->attributes = new ProductAttributeModel();
    }

    public function index()
    {
        try {
            $list = $this->attributes->orderBy('name', 'ASC')->findAll();
            if ($this->request->isAJAX() || $this->request->getGet('modal') == '1') {
                return view('product_attributes/index', ['attributes' => $list]);
            }
            return view('product_attributes/index', ['attributes' => $list]);
        } catch (\Exception $e) {
            // Likely the migration hasn't been run or table missing. Show friendly guidance.
            $msg = $e->getMessage();
            // If AJAX/modal, return JSON with error
            if ($this->request->isAJAX() || $this->request->getGet('modal') == '1') {
                return $this->response->setJSON(['success' => false, 'message' => 'Database error: ' . $msg]);
            }
            return view('product_attributes/error_missing_table', ['message' => $msg]);
        }
    }

    public function create()
    {
        if ($this->request->isAJAX() || $this->request->getGet('modal') == '1') {
            return view('product_attributes/form');
        }
        return view('product_attributes/form');
    }

    public function edit($id = null)
    {
        if (! $id) {
            return redirect()->to('/product-attributes')->with('error', 'Invalid attribute');
        }

        try {
            $attr = $this->attributes->find((int) $id);
            if (! $attr) {
                if ($this->request->isAJAX() || $this->request->getGet('modal') == '1') {
                    return $this->response->setJSON(['success' => false, 'message' => 'Attribute not found']);
                }
                return redirect()->to('/product-attributes')->with('error', 'Attribute not found');
            }

            if ($this->request->isAJAX() || $this->request->getGet('modal') == '1') {
                return view('product_attributes/form', ['attribute' => $attr]);
            }
            return view('product_attributes/form', ['attribute' => $attr]);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            if ($this->request->isAJAX() || $this->request->getGet('modal') == '1') {
                return $this->response->setJSON(['success' => false, 'message' => 'Database error: ' . $msg]);
            }
            return redirect()->to('/product-attributes')->with('error', 'Database error: ' . $msg);
        }
    }

    public function update($id = null)
    {
        if (! $id) {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid attribute']);
        }

        $attr = $this->attributes->find((int) $id);
        if (! $attr) {
            return $this->response->setJSON(['success' => false, 'message' => 'Attribute not found']);
        }

        $post = $this->request->getPost();
        $newName = trim((string) ($post['name'] ?? ''));
        if ($newName === '') {
            return $this->response->setJSON(['success' => false, 'message' => 'Name required']);
        }

        // Values[] in edit mode are treated as NEW values to append (JSON remains authoritative)
        $newValues = [];
        if (isset($post['values']) && is_array($post['values'])) {
            $newValues = $post['values'];
        } elseif ($this->request->getPost('values[]')) {
            $newValues = $this->request->getPost('values[]');
        }
        $newValues = array_values(array_filter(array_map('trim', (array) $newValues)));

        $oldName = (string) ($attr['name'] ?? '');

        // Renaming is unsafe if variants already use this attribute name as a JSON key.
        if ($oldName !== '' && strcasecmp($oldName, $newName) !== 0) {
            $db = Database::connect();
            $pvTable = $db->table('product_variants');
            $likeKey = '"' . addslashes($oldName) . '":';
            $pvCount = $pvTable->like('attributes', $likeKey, 'both')->countAllResults();
            if ($pvCount > 0) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Cannot rename this attribute because product variants already use it. Rename would break existing variants.',
                ]);
            }
        }

        $existingValues = json_decode($attr['values'] ?? '[]', true) ?? [];
        if (! is_array($existingValues)) $existingValues = [];

        // Append only new unique values (case-insensitive compare, preserve original casing)
        $existingLower = array_map(function ($v) { return mb_strtolower(trim((string)$v)); }, $existingValues);
        foreach ($newValues as $v) {
            $lv = mb_strtolower($v);
            if ($lv === '') continue;
            if (! in_array($lv, $existingLower, true)) {
                $existingValues[] = $v;
                $existingLower[] = $lv;
            }
        }

        $isActive = isset($post['is_active']) ? 1 : (int)($attr['is_active'] ?? 1);

        $this->attributes->update((int) $id, [
            'name' => $newName,
            'values' => json_encode(array_values($existingValues)),
            'is_active' => $isActive,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->response->setJSON([
            'success' => true,
            'attribute' => $this->attributes->find((int) $id),
        ]);
    }

    public function store()
    {
        $post = $this->request->getPost();
        $name = trim($post['name'] ?? '');
        // Accept values as either comma-separated string or as array (values[])
        $values = [];
        if (isset($post['values']) && is_array($post['values'])) {
            $values = $post['values'];
        } elseif (isset($post['values']) && is_string($post['values'])) {
            $values = explode(',', $post['values']);
        } elseif ($this->request->getPost('values[]')) {
            // fallback for form-encoded arrays
            $values = $this->request->getPost('values[]');
        }
        if ($name === '') {
            return $this->response->setJSON(['success' => false, 'message' => 'Name required']);
        }
        $vals = array_values(array_filter(array_map('trim', (array)$values)));

        // Enforce case-insensitive uniqueness by name.
        $existing = $this->attributes->findAll();
        $existingAttr = null;
        $nameKey = mb_strtolower($name);
        foreach ($existing as $row) {
            $rowName = trim((string)($row['name'] ?? ''));
            if ($rowName !== '' && mb_strtolower($rowName) === $nameKey) {
                $existingAttr = $row;
                break;
            }
        }

        if ($existingAttr) {
            $existingVals = json_decode((string)($existingAttr['values'] ?? '[]'), true);
            if (!is_array($existingVals)) {
                $existingVals = [];
            }
            $seen = [];
            $merged = [];
            foreach (array_merge($existingVals, $vals) as $v) {
                $v = trim((string)$v);
                if ($v === '') {
                    continue;
                }
                $vKey = mb_strtolower($v);
                if (isset($seen[$vKey])) {
                    continue;
                }
                $seen[$vKey] = true;
                $merged[] = $v;
            }

            $this->attributes->update((int)$existingAttr['id'], [
                'name' => $existingAttr['name'] ?: $name,
                'values' => json_encode($merged),
                'is_active' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $attr = $this->attributes->find((int)$existingAttr['id']);
            return $this->response->setJSON(['success' => true, 'attribute' => $attr, 'deduplicated' => true]);
        }

        $this->attributes->insert([
            'name' => $name,
            'values' => json_encode($vals),
            'is_active' => isset($post['is_active']) ? 1 : 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success' => true, 'attribute' => $this->attributes->find($this->attributes->getInsertID())]);
        }

        return redirect()->to('/product-attributes')->with('success', 'Attribute created');
    }

    public function data()
    {
        // Return JSON list of active attributes for select/import
        $list = $this->attributes->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
        $out = [];
        foreach ($list as $a) {
            $vals = [];
            if (! empty($a['values'])) {
                $vals = json_decode($a['values'], true) ?? [];
            }
            $out[] = ['id' => $a['id'], 'name' => $a['name'], 'values' => $vals];
        }
        return $this->response->setJSON($out);
    }

    /**
     * Lightweight list for product form: returns only id + name (no values payload).
     * GET: /product-attributes/list
     */
    public function list()
    {
        try {
            $list = $this->attributes->select('id,name')->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
            return $this->response->setJSON(['success' => true, 'data' => $list]);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['success' => false, 'message' => 'Database error: ' . $e->getMessage(), 'data' => []]);
        }
    }

    /**
     * Search attributes by keyword (used by product form)
     * GET: /product-attributes/search?q=...
     */
    public function search()
    {
        try {
            $q = trim((string)($this->request->getGet('q') ?? ''));
            $builder = $this->attributes->select('id,name')->where('is_active', 1);
            if ($q !== '') {
                $builder->like('name', $q);
            }
            $list = $builder->orderBy('name', 'ASC')->limit(20)->findAll();
            return $this->response->setJSON(['success' => true, 'data' => $list]);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['success' => false, 'message' => 'Database error: ' . $e->getMessage(), 'data' => []]);
        }
    }

    /**
     * Search values for a single attribute, without returning all values at once.
     * GET: /product-attributes/{id}/values?q=...
     */
    public function values($id = null)
    {
        $id = (int) $id;
        if (! $id) {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid attribute', 'data' => []]);
        }

        try {
            $attr = $this->attributes->find($id);
            if (! $attr) {
                return $this->response->setJSON(['success' => false, 'message' => 'Attribute not found', 'data' => []]);
            }

            $vals = json_decode($attr['values'] ?? '[]', true) ?? [];
            if (! is_array($vals)) $vals = [];
            $vals = array_values(array_filter(array_map('strval', $vals)));

            $q = trim((string) ($this->request->getGet('q') ?? ''));
            if ($q !== '') {
                $ql = mb_strtolower($q);
                $vals = array_values(array_filter($vals, function ($v) use ($ql) {
                    return mb_stripos(mb_strtolower((string) $v), $ql) !== false;
                }));
            }

            // Sort and limit to keep response small
            usort($vals, function ($a, $b) { return strnatcasecmp((string) $a, (string) $b); });
            $vals = array_slice($vals, 0, 30);

            return $this->response->setJSON(['success' => true, 'data' => $vals]);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['success' => false, 'message' => 'Database error: ' . $e->getMessage(), 'data' => []]);
        }
    }

    public function delete($id = null)
    {
        if (! $id) return redirect()->back()->with('error', 'Invalid attribute');

        $attr = $this->attributes->find((int)$id);
        if (! $attr) {
            if ($this->request->isAJAX()) return $this->response->setJSON(['success' => false, 'message' => 'Attribute not found']);
            return redirect()->back()->with('error', 'Attribute not found');
        }

        // Block deletion if any variants already use this attribute key.
        try {
            $db = Database::connect();
            $pvTable = $db->table('product_variants');
            $name = (string)($attr['name'] ?? '');
            if ($name !== '') {
                $likeKey = '"' . addslashes($name) . '":';
                $pvCount = $pvTable->like('attributes', $likeKey, 'both')->countAllResults();
                if ($pvCount > 0) {
                    $msg = 'Cannot delete this attribute because product variants already use it.';
                    if ($this->request->isAJAX()) return $this->response->setJSON(['success' => false, 'message' => $msg]);
                    return redirect()->back()->with('error', $msg);
                }
            }
        } catch (\Throwable $e) {
            // If we cannot check safely, default to blocking deletion to avoid data loss.
            $msg = 'Unable to verify attribute usage. Delete blocked: ' . $e->getMessage();
            if ($this->request->isAJAX()) return $this->response->setJSON(['success' => false, 'message' => $msg]);
            return redirect()->back()->with('error', $msg);
        }

        $this->attributes->delete((int)$id);
        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success' => true]);
        }
        return redirect()->back()->with('success', 'Attribute deleted');
    }

    /**
     * Add a new value to an attribute's JSON values array.
     * Expects POST: value
     */
    public function addValue($id = null)
    {
        if (! $id) return $this->response->setJSON(['success' => false, 'message' => 'Invalid attribute']);
        $post = $this->request->getPost();
        $val = trim((string)($post['value'] ?? ''));
        if ($val === '') return $this->response->setJSON(['success' => false, 'message' => 'Value required']);

        $attr = $this->attributes->find((int)$id);
        if (! $attr) return $this->response->setJSON(['success' => false, 'message' => 'Attribute not found']);

        $vals = json_decode($attr['values'] ?? '[]', true) ?? [];
        if (! is_array($vals)) $vals = [];

        $lower = array_map(function ($v) { return mb_strtolower(trim((string)$v)); }, $vals);
        $lv = mb_strtolower($val);
        if (! in_array($lv, $lower, true)) {
            $vals[] = $val;
        }

        $this->attributes->update((int)$id, ['values' => json_encode(array_values($vals)), 'updated_at' => date('Y-m-d H:i:s')]);
        return $this->response->setJSON(['success' => true, 'values' => $vals]);
    }

    /**
     * Update a single value in an attribute's values list.
     * Expects POST: old_value, new_value
     */
    public function updateValue($id = null)
    {
        if (! $id) return $this->response->setJSON(['success' => false, 'message' => 'Invalid attribute']);
        $post = $this->request->getPost();
        $old = trim($post['old_value'] ?? '');
        $new = trim($post['new_value'] ?? '');
        if ($old === '' || $new === '') return $this->response->setJSON(['success' => false, 'message' => 'Old and new values required']);
        $attr = $this->attributes->find($id);
        if (! $attr) return $this->response->setJSON(['success' => false, 'message' => 'Attribute not found']);
        $name = $attr['name'];
        $vals = json_decode($attr['values'] ?? '[]', true) ?? [];
        $foundIndex = array_search($old, $vals);
        if ($foundIndex === false) return $this->response->setJSON(['success' => false, 'message' => 'Value not found']);
        if ($old === $new) return $this->response->setJSON(['success' => true, 'message' => 'No changes']);

        // Check if any product variants use this attribute value
        $db = Database::connect();
        $pvTable = $db->table('product_variants');
        $likePattern = '"' . addslashes($name) . '":"' . addslashes($old) . '"';
        $pvCount = $pvTable->like('attributes', $likePattern, 'both')->countAllResults();
        if ($pvCount > 0) {
            // collect parent product ids to check orders/quotes
            $rows = $pvTable->select('product_id')->like('attributes', $likePattern, 'both')->get()->getResultArray();
            $productIds = array_values(array_unique(array_column($rows, 'product_id')));
            // check quotation_lines and sales_order_lines
            $ql = $db->table('quotation_lines');
            $sl = $db->table('sales_order_lines');
            $hasQuotes = false; $hasSales = false;
            if (! empty($productIds)) {
                $hasQuotes = $ql->whereIn('product_id', $productIds)->countAllResults() > 0;
                $hasSales = $sl->whereIn('product_id', $productIds)->countAllResults() > 0;
            }
            if ($hasQuotes || $hasSales) {
                return $this->response->setJSON(['success' => false, 'message' => 'Value is used by variants and those products are present in quotations or sales orders. Edit denied.']);
            }
            return $this->response->setJSON(['success' => false, 'message' => 'Value is used in product variants; remove variants first before editing values.']);
        }

        // Safe to update value
        $vals[$foundIndex] = $new;
        $this->attributes->update($id, ['values' => json_encode(array_values($vals)), 'updated_at' => date('Y-m-d H:i:s')]);
        return $this->response->setJSON(['success' => true, 'values' => $vals]);
    }

    /**
     * Delete a single value from attribute values array.
     * Expects POST: value
     */
    public function deleteValue($id = null)
    {
        if (! $id) return $this->response->setJSON(['success' => false, 'message' => 'Invalid attribute']);
        $post = $this->request->getPost();
        $val = trim($post['value'] ?? '');
        if ($val === '') return $this->response->setJSON(['success' => false, 'message' => 'Value required']);
        $attr = $this->attributes->find($id);
        if (! $attr) return $this->response->setJSON(['success' => false, 'message' => 'Attribute not found']);
        $name = $attr['name'];
        $vals = json_decode($attr['values'] ?? '[]', true) ?? [];
        if (! in_array($val, $vals, true)) return $this->response->setJSON(['success' => false, 'message' => 'Value not found']);

        // Check usage in product variants
        $db = Database::connect();
        $pvTable = $db->table('product_variants');
        $likePattern = '"' . addslashes($name) . '":"' . addslashes($val) . '"';
        $pvCount = $pvTable->like('attributes', $likePattern, 'both')->countAllResults();
        if ($pvCount > 0) {
            // collect parent product ids and check orders/quotes
            $rows = $pvTable->select('product_id')->like('attributes', $likePattern, 'both')->get()->getResultArray();
            $productIds = array_values(array_unique(array_column($rows, 'product_id')));
            $ql = $db->table('quotation_lines');
            $sl = $db->table('sales_order_lines');
            $hasQuotes = false; $hasSales = false;
            if (! empty($productIds)) {
                $hasQuotes = $ql->whereIn('product_id', $productIds)->countAllResults() > 0;
                $hasSales = $sl->whereIn('product_id', $productIds)->countAllResults() > 0;
            }
            if ($hasQuotes || $hasSales) {
                return $this->response->setJSON(['success' => false, 'message' => 'Value cannot be removed because variants exist and those products appear in quotations or sales orders.']);
            }
            return $this->response->setJSON(['success' => false, 'message' => 'Value is used in product variants; remove variants first before deleting this value.']);
        }

        // remove value
        $newVals = array_values(array_filter($vals, function($v) use ($val){ return $v !== $val; }));
        $this->attributes->update($id, ['values' => json_encode($newVals), 'updated_at' => date('Y-m-d H:i:s')]);
        return $this->response->setJSON(['success' => true, 'values' => $newVals]);
    }

    /**
     * Check whether a single attribute value is used by variants or orders.
     * Expects POST: value
     * Returns JSON { success: true, used: bool, pvCount: int, hasQuotes: bool, hasSales: bool, message: string }
     */
    public function checkValueUsage($id = null)
    {
        if (! $id) return $this->response->setJSON(['success' => false, 'message' => 'Invalid attribute']);
        $post = $this->request->getPost();
        $val = trim($post['value'] ?? '');
        if ($val === '') return $this->response->setJSON(['success' => false, 'message' => 'Value required']);
        $attr = $this->attributes->find($id);
        if (! $attr) return $this->response->setJSON(['success' => false, 'message' => 'Attribute not found']);
        $name = $attr['name'];

        $db = Database::connect();
        $pvTable = $db->table('product_variants');
        $likePattern = '"' . addslashes($name) . '":"' . addslashes($val) . '"';
        $pvCount = $pvTable->like('attributes', $likePattern, 'both')->countAllResults();

        $hasQuotes = false; $hasSales = false;
        $productIds = [];
        if ($pvCount > 0) {
            $rows = $pvTable->select('product_id')->like('attributes', $likePattern, 'both')->get()->getResultArray();
            $productIds = array_values(array_unique(array_column($rows, 'product_id')));
            $ql = $db->table('quotation_lines');
            $sl = $db->table('sales_order_lines');
            if (! empty($productIds)) {
                $hasQuotes = $ql->whereIn('product_id', $productIds)->countAllResults() > 0;
                $hasSales = $sl->whereIn('product_id', $productIds)->countAllResults() > 0;
            }
        }

        $used = $pvCount > 0 || $hasQuotes || $hasSales;
        $msg = '';
        if ($used) {
            if ($hasQuotes || $hasSales) {
                $msg = 'Value is used by variants and those products appear in quotations or sales orders. Operation blocked.';
            } else {
                $msg = 'Value is used in product variants; remove variants first before modifying this value.';
            }
        }

        return $this->response->setJSON(['success' => true, 'used' => $used, 'pvCount' => (int)$pvCount, 'hasQuotes' => $hasQuotes, 'hasSales' => $hasSales, 'message' => $msg]);
    }
}
