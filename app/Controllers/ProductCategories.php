<?php

namespace App\Controllers;

use App\Models\ProductCategoryModel;
use App\Models\ProductModel;

class ProductCategories extends BaseController
{
    protected $categoryModel;
    protected $productModel;

    public function __construct()
    {
        $this->categoryModel = new ProductCategoryModel();
        $this->productModel = new ProductModel();
    }

    /**
     * Display categories list
     */
    public function index()
    {
        $this->requireAuth();

        $search = $this->request->getGet('search');
        $status = $this->request->getGet('status');

        $builder = $this->categoryModel;

        if ($search) {
            $builder = $builder->groupStart()
                             ->like('name', $search)
                             ->orLike('description', $search)
                             ->groupEnd();
        }

        if ($status !== null && $status !== '') {
            $builder = $builder->where('is_active', $status);
        }

    // Include parent category name via a LEFT JOIN so the list can display the parent
    $builder = $builder->select('product_categories.*, parent.name AS parent_name')
               ->join('product_categories AS parent', 'parent.id = product_categories.parent_id', 'left');

    $categories = $builder->orderBy('name', 'ASC')->findAll();

        // Get product count for each category
        foreach ($categories as &$category) {
            $category['product_count'] = $this->productModel->where('category_id', $category['id'])->countAllResults();
        }

        // Build an id -> category map to compute full parent chain for each category
        $allCategories = $this->categoryModel->findAll();
        $catMap = [];
        foreach ($allCategories as $c) {
            $catMap[$c['id']] = [
                'name' => $c['name'] ?? '',
                'parent_id' => isset($c['parent_id']) ? $c['parent_id'] : null,
            ];
        }

        // Helper to compute ancestry string (top -> ... -> direct parent)
        $buildChain = function ($startParentId) use ($catMap) {
            if (empty($startParentId)) {
                return null;
            }
            $names = [];
            $seen = [];
            $current = $startParentId;
            $depth = 0;
            while ($current && isset($catMap[$current]) && $depth < 20) {
                // avoid cycles
                if (isset($seen[$current])) break;
                $seen[$current] = true;
                $names[] = $catMap[$current]['name'];
                $current = $catMap[$current]['parent_id'];
                $depth++;
            }
            if (empty($names)) return null;
            // names collected from direct parent -> up; reverse to show top -> ... -> parent
            return implode(' > ', array_reverse($names));
        };

        // Attach parent_chain to each category
        foreach ($categories as &$category) {
            $category['parent_chain'] = $buildChain($category['parent_id'] ?? null);
        }

        $data = $this->setPageData([
            'page_title' => 'Product Categories',
            'categories' => $categories,
            'search' => $search,
            'status' => $status,
            'can_create' => true, // Skip permission for testing
            'can_edit' => true,
            'can_delete' => true
        ]);

        return view('product_categories/index', $data);
    }

    /**
     * Display create category form
     */
    public function create()
    {
        $this->requireAuth();

        $artService = new \App\Services\ArtNumberService();
        $data = $this->setPageData([
            'page_title' => 'Create Product Category',
            'category' => null,
            'categories_dropdown' => $this->categoryModel->getActiveCategoriesForDropdown(),
            'validation' => \Config\Services::validation(),
            'brand_code' => $artService->getBrandCode(),
        ]);

        return view('product_categories/form', $data);
    }

    /**
     * Handle category creation
     */
    public function store()
    {
        $this->requireAuth();

        // Rely on the model's validation rules (declared in ProductCategoryModel)
        // to avoid duplicating queries here that may cause table/alias conflicts.

        // Normalise suffix to uppercase letters only
        $rawSuffix = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $this->request->getPost('suffix')));

        $data = [
            'name' => $this->request->getPost('name'),
            'description' => $this->request->getPost('description'),
            'is_active' => $this->request->getPost('is_active') ? 1 : 1,
            'prefix' => $this->request->getPost('prefix') ? strtoupper($this->request->getPost('prefix')) : null,
            'suffix' => $rawSuffix !== '' ? $rawSuffix : null,
            'start_range' => $this->request->getPost('start_range') !== '' ? $this->request->getPost('start_range') : null,
            'end_range' => $this->request->getPost('end_range') !== '' ? $this->request->getPost('end_range') : null,
            'next_number' => $this->request->getPost('next_number') !== '' ? $this->request->getPost('next_number') : null,
            'parent_id' => $this->request->getPost('parent_id') !== '' ? $this->request->getPost('parent_id') : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // If next_number not provided but start_range is, initialize next_number to start_range
        if (($data['next_number'] === null || $data['next_number'] === '') && $data['start_range'] !== null) {
            $data['next_number'] = $data['start_range'];
        }

        // Pre-check suffix uniqueness to give a helpful error with suggestions
        $suffixConflict = $this->categoryModel->where('suffix', $data['suffix'])->first();
        if ($suffixConflict) {
            $suggestions = $this->generateSuffixSuggestions($data['name'] ?? '', $data['suffix'] ?? '', 0);
            $suggestText = !empty($suggestions) ? ' Try one of these instead: ' . implode(', ', $suggestions) . '.' : '';
            $conflictName = $suffixConflict['name'] ?? 'another category';
            if ($this->request->isAJAX()) {
                return $this->jsonResponse([
                    'success'     => false,
                    'field'       => 'suffix',
                    'message'     => 'The prefix "' . esc($data['suffix']) . '" is already used by "' . esc($conflictName) . '".' . $suggestText,
                    'suggestions' => $suggestions,
                ]);
            }
            return redirect()->back()
                           ->withInput()
                           ->with('validation', ['suffix' => 'The category prefix "' . $data['suffix'] . '" is already used by "' . $conflictName . '".' . $suggestText]);
        }

        try {
            $saved = $this->categoryModel->save($data);
        } catch (\Throwable $e) {
            // In development show the failing SQL to help diagnose duplicate alias
            $lastQuery = '';
            try {
                $lastQuery = $this->categoryModel->db->getLastQuery();
            } catch (\Throwable $_) {
                $lastQuery = '(could not retrieve last query)';
            }
            throw new \RuntimeException("Category save failed: " . $e->getMessage() . "\nLast Query: " . $lastQuery, 0, $e);
        }

        if ($saved) {
            // Check if it's an AJAX request
            if ($this->request->isAJAX()) {
                return $this->jsonResponse(['success' => true, 'message' => 'Category created successfully']);
            }
            return redirect()->to('/product-categories')->with('success', 'Product category created successfully.');
        } else {
            if ($this->request->isAJAX()) {
                return $this->jsonResponse(['success' => false, 'message' => 'Failed to create category']);
            }
            return redirect()->back()
                           ->withInput()
                           ->with('error', 'Failed to create product category.');
        }
    }

    /**
     * Display edit category form
     */
    public function edit($id = null)
    {
        $this->requireAuth();

        $category = $this->categoryModel->find($id);
        if (!$category) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Product category not found');
        }

        $artService = new \App\Services\ArtNumberService();
        $data = $this->setPageData([
            'page_title' => 'Edit Product Category - ' . $category['name'],
            'category' => $category,
            'categories_dropdown' => $this->categoryModel->getActiveCategoriesForDropdown(),
            'validation' => \Config\Services::validation(),
            'brand_code' => $artService->getBrandCode(),
        ]);

        return view('product_categories/form', $data);
    }

    /**
     * Handle category update
     */
    public function update($id = null)
    {
        $this->requireAuth();

        $category = $this->categoryModel->find($id);
        if (!$category) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Product category not found');
        }

        $rules = [
            'name' => "required|min_length[2]|max_length[100]|is_unique[product_categories.name,id,$id]",
            'description' => 'permit_empty|max_length[500]',
            'prefix' => "permit_empty|alpha_dash|min_length[1]|max_length[50]|is_unique[product_categories.prefix,id,$id]",
            'suffix' => "required|min_length[2]|max_length[4]|alpha|is_unique[product_categories.suffix,id,$id]",
            'start_range' => 'permit_empty|integer',
            'end_range' => 'permit_empty|integer',
            'next_number' => 'permit_empty|integer',
            'parent_id' => 'permit_empty|integer'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()
                           ->withInput()
                           ->with('validation', $this->validator->getErrors());
        }

        // Normalise suffix to uppercase letters only
        $rawSuffix = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $this->request->getPost('suffix')));

        $data = [
            'name' => $this->request->getPost('name'),
            'description' => $this->request->getPost('description'),
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
            'prefix' => $this->request->getPost('prefix') ? strtoupper($this->request->getPost('prefix')) : null,
            'suffix' => $rawSuffix !== '' ? $rawSuffix : null,
            'start_range' => $this->request->getPost('start_range') !== '' ? $this->request->getPost('start_range') : null,
            'end_range' => $this->request->getPost('end_range') !== '' ? $this->request->getPost('end_range') : null,
            'next_number' => $this->request->getPost('next_number') !== '' ? $this->request->getPost('next_number') : null,
            'parent_id' => $this->request->getPost('parent_id') !== '' ? $this->request->getPost('parent_id') : null,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // If there are existing products in this category, ensure changes won't invalidate their codes
        $oldPrefix = $category['prefix'] ?? null;
        $oldSuffix = $category['suffix'] ?? null;
        $oldStart = $category['start_range'] ?? null;
        $oldEnd = $category['end_range'] ?? null;

        $newPrefix = $data['prefix'];
        $newSuffix = $data['suffix'];
        $newStart = $data['start_range'];
        $newEnd = $data['end_range'];

        $productCount = $this->productModel->where('category_id', $id)->countAllResults();
        if ($productCount > 0) {
            // Disallow changing suffix while products exist (art numbers embed the suffix)
            if ($oldSuffix !== null && $oldSuffix !== '' && $oldSuffix !== $newSuffix) {
                return redirect()->back()
                               ->withInput()
                               ->with('error', 'Cannot change category suffix while products exist (' . $productCount . ' product(s)). Reassign or delete those products first.');
            }

            // Disallow changing prefix while products exist (to avoid orphaned/invalid SKUs)
            if ($oldPrefix !== $newPrefix && ($oldPrefix !== null || $newPrefix !== null)) {
                return redirect()->back()
                               ->withInput()
                               ->with('error', 'Cannot change category prefix while products exist in this category. Reassign or delete those products first.');
            }

            // If numeric range is being changed, validate that all existing product codes remain valid
            if (($newStart !== null && $newEnd !== null) && ($oldStart != $newStart || $oldEnd != $newEnd)) {
                // If old prefix is missing, we cannot validate product codes reliably — block change
                if (empty($oldPrefix)) {
                    return redirect()->back()
                                   ->withInput()
                                   ->with('error', 'Cannot change numeric range because existing products in this category do not use a category-prefixed SKU format.');
                }

                $products = $this->productModel->where('category_id', $id)->findAll();
                foreach ($products as $p) {
                    $code = strtoupper(trim($p['code'] ?? $p['sku'] ?? ''));
                    $expectedPrefix = strtoupper($oldPrefix) . '-';
                    if (strpos($code, $expectedPrefix) !== 0) {
                        return redirect()->back()
                                       ->withInput()
                                       ->with('error', 'Cannot change numeric range because product ID ' . ($p['id'] ?? '?') . " has code not matching the category prefix.");
                    }

                    $numPart = substr($code, strlen($expectedPrefix));
                    $numDigits = preg_replace('/\D/', '', $numPart);
                    if ($numDigits === '') {
                        return redirect()->back()
                                       ->withInput()
                                       ->with('error', 'Cannot change numeric range because product ID ' . ($p['id'] ?? '?') . ' has a non-numeric SKU suffix.');
                    }

                    $num = (int) $numDigits;
                    if ($num < (int)$newStart || $num > (int)$newEnd) {
                        return redirect()->back()
                                       ->withInput()
                                       ->with('error', 'Cannot change numeric range because product ID ' . ($p['id'] ?? '?') . " would fall outside the new range ({$newStart} - {$newEnd}).");
                    }
                }
            }
        }

    // Ensure model validation placeholders like {id} are resolvable by including primary key in data
    $data[$this->categoryModel->primaryKey] = $id;

    if ($this->categoryModel->update($id, $data)) {
            return redirect()->to('/product-categories')->with('success', 'Product category updated successfully.');
        } else {
            // Try to capture validation errors from the model where possible
            $errors = $this->categoryModel->errors();
            if (empty($errors)) {
                // fallback generic message
                $errors = ['update' => 'Failed to update product category.'];
            }
            return redirect()->back()
                           ->withInput()
                           ->with('validation', $errors)
                           ->with('error', 'Failed to update product category.');
        }
    }

    /**
     * Delete category
     */
    public function delete($id = null)
    {
        $this->requireAuth();

        $category = $this->categoryModel->find($id);
        if (!$category) {
            return $this->jsonResponse(['success' => false, 'message' => 'Product category not found'], 404);
        }

        // Check if category has associated products
        $productCount = $this->productModel->where('category_id', $id)->countAllResults();

        if ($productCount > 0) {
            return $this->jsonResponse([
                'success' => false,
                'message' => "Cannot delete category. It has $productCount associated product(s). Please reassign or delete those products first."
            ], 400);
        }

        if ($this->categoryModel->delete($id)) {
            return $this->jsonResponse(['success' => true, 'message' => 'Product category deleted successfully.']);
        } else {
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to delete product category.'], 500);
        }
    }

    /**
     * Toggle category status
     */
    public function toggleStatus($id = null)
    {
        $this->requireAuth();

        $category = $this->categoryModel->find($id);
        if (!$category) {
            return $this->jsonResponse(['success' => false, 'message' => 'Product category not found'], 404);
        }

        $newStatus = !$category['is_active'];
        $data = [
            'is_active' => $newStatus,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($this->categoryModel->update($id, $data)) {
            $statusText = $newStatus ? 'activated' : 'deactivated';
            return $this->jsonResponse([
                'success' => true,
                'message' => "Product category {$statusText} successfully.",
                'new_status' => $newStatus
            ]);
        } else {
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to update category status.'], 500);
        }
    }

    /**
     * Get categories for AJAX requests
     */
    public function getData()
    {
        $this->requireAuth();

        $action = $this->request->getGet('action');
        
        switch ($action) {
            case 'active':
                $categories = $this->categoryModel->where('is_active', true)
                                                 ->orderBy('name', 'ASC')
                                                 ->findAll();
                return $this->jsonResponse($categories);
                
            case 'search':
                $term = $this->request->getGet('term');
                $categories = $this->categoryModel->like('name', $term)
                                                 ->where('is_active', true)
                                                 ->select('id, name')
                                                 ->limit(10)
                                                 ->findAll();
                return $this->jsonResponse($categories);

            case 'check_prefix':
                $prefix = $this->request->getGet('prefix');
                if (! $prefix) {
                    return $this->jsonResponse(['error' => 'Missing prefix'], 400);
                }
                $existing = $this->categoryModel->where('prefix', strtoupper($prefix))->first();
                return $this->jsonResponse(['exists' => (bool)$existing, 'category' => $existing ?: null]);

            case 'check_suffix':
                $suffix = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $this->request->getGet('suffix')));
                $excludeId = (int) ($this->request->getGet('exclude_id') ?? 0);
                $categoryName = (string) $this->request->getGet('name');
                if (! $suffix) {
                    return $this->jsonResponse(['error' => 'Missing suffix'], 400);
                }
                $builder = $this->categoryModel->where('suffix', $suffix);
                if ($excludeId > 0) $builder = $builder->where('id !=', $excludeId);
                $existing = $builder->first();
                $suggestions = [];
                if ($existing) {
                    $suggestions = $this->generateSuffixSuggestions($categoryName ?: $suffix, $suffix, $excludeId);
                }
                return $this->jsonResponse([
                    'exists'          => (bool) $existing,
                    'suffix'          => $suffix,
                    'existing_name'   => $existing ? ($existing['name'] ?? '') : null,
                    'suggestions'     => $suggestions,
                ]);

            case 'preview_next_sku':
                // Return next art number preview without incrementing.
                // Accepts either a saved category_id or a raw suffix (for new categories).
                $catId = (int) ($this->request->getGet('category_id') ?? 0);
                $rawSuffix = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $this->request->getGet('suffix')));
                try {
                    $artService = new \App\Services\ArtNumberService();
                    if ($catId > 0) {
                        $preview = $artService->previewForCategory($catId);
                        if ($preview === '—') {
                            return $this->jsonResponse(['error' => 'Selected category is missing a valid suffix (2-4 letters).'], 400);
                        }
                    } elseif ($rawSuffix && strlen($rawSuffix) >= 2) {
                        // New category — build preview from raw suffix + global counter
                        $next = $artService->currentGlobalNumber();
                        $numStr = str_pad((string) $next, \App\Services\ArtNumberService::PAD_DIGITS, '0', STR_PAD_LEFT);
                        $preview = $artService->getBrandCode() . '-' . $rawSuffix . '-' . $numStr;
                    } else {
                        return $this->jsonResponse(['error' => 'Provide category_id or a valid suffix'], 400);
                    }
                    return $this->jsonResponse(['sku' => $preview]);
                } catch (\Throwable $e) {
                    return $this->jsonResponse(['error' => $e->getMessage()], 400);
                }
                
            default:
                return $this->jsonResponse(['error' => 'Invalid action'], 400);
        }
    }

    /**
     * Generate available suffix suggestions based on a category name.
     */
    private function generateSuffixSuggestions(string $name, string $taken, int $excludeId = 0): array
    {
        $candidates = [];
        $words = preg_split('/[\s\-_\/]+/', strtoupper(trim($name)));
        $words = array_values(array_filter($words, fn($w) => strlen($w) > 0));

        // Pattern 1: Initials of all words (up to 4 chars)
        if (count($words) >= 2) {
            $initials = implode('', array_map(fn($w) => substr(preg_replace('/[^A-Z]/', '', $w), 0, 1), array_slice($words, 0, 4)));
            if (strlen($initials) >= 2) $candidates[] = $initials;
        }

        // Pattern 2: First 2/3/4 letters of first word
        $first = preg_replace('/[^A-Z]/', '', $words[0] ?? '');
        if (strlen($first) >= 2) $candidates[] = substr($first, 0, 2);
        if (strlen($first) >= 3) $candidates[] = substr($first, 0, 3);
        if (strlen($first) >= 4) $candidates[] = substr($first, 0, 4);

        // Pattern 3: First letter of word1 + first 2 of word2, and vice versa
        if (count($words) >= 2) {
            $w1 = preg_replace('/[^A-Z]/', '', $words[0]);
            $w2 = preg_replace('/[^A-Z]/', '', $words[1]);
            if ($w1 && $w2) {
                $candidates[] = substr($w1, 0, 1) . substr($w2, 0, 2);
                $candidates[] = substr($w1, 0, 2) . substr($w2, 0, 1);
                $candidates[] = substr($w1, 0, 1) . substr($w2, 0, 3);
            }
        }

        // Filter: unique, valid length, not taken, not already in DB
        $suggestions = [];
        $seen = [$taken => true];
        foreach ($candidates as $c) {
            $c = strtoupper(substr(preg_replace('/[^A-Z]/', '', $c), 0, 4));
            if (strlen($c) < 2 || isset($seen[$c])) continue;
            $seen[$c] = true;
            $b = $this->categoryModel->where('suffix', $c);
            if ($excludeId > 0) $b = $b->where('id !=', $excludeId);
            if (! $b->first()) {
                $suggestions[] = $c;
            }
            if (count($suggestions) >= 4) break;
        }
        return $suggestions;
    }
}
