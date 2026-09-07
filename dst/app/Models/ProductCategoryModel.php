<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductCategoryModel extends Model
{
    protected $table            = 'product_categories';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    // allowed fields including SKU prefix and ranges
    protected $allowedFields    = ['name', 'description', 'is_active', 'prefix', 'suffix', 'start_range', 'end_range', 'next_number', 'parent_id'];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation

    protected $validationRules = [
        'id' => 'permit_empty',
        'name' => 'required|min_length[2]|max_length[100]|is_unique[product_categories.name,id,{id}]',
        'prefix' => 'permit_empty|alpha_dash|min_length[1]|max_length[50]|is_unique[product_categories.prefix,id,{id}]',
        'suffix' => 'required|min_length[2]|max_length[4]|alpha|is_unique[product_categories.suffix,id,{id}]',
        'start_range' => 'permit_empty|integer',
        'end_range' => 'permit_empty|integer',
        'next_number' => 'permit_empty|integer',
        'parent_id' => 'permit_empty|integer'
    ];

    protected $validationMessages = [
        'name' => [
            'required'    => 'Category name is required',
            'min_length'  => 'Category name must be at least 2 characters',
            'is_unique'   => 'Category name already exists'
        ],
        'prefix' => [
            'is_unique' => 'This prefix is already in use. Please choose a unique prefix.'
        ],
        'suffix' => [
            'required'   => 'Category suffix is required for art number generation.',
            'min_length' => 'Suffix must be 2–4 uppercase letters.',
            'max_length' => 'Suffix must be 2–4 uppercase letters.',
            'alpha'      => 'Suffix must contain only letters (A-Z).',
            'is_unique'  => 'This suffix is already used by another category.'
        ],
        'start_range' => [
            'integer' => 'Start range must be a valid integer'
        ],
        'end_range' => [
            'integer' => 'End range must be a valid integer'
        ],
        'next_number' => [
            'integer' => 'Next number must be a valid integer'
        ]
    ];

    // Callbacks to validate ranges
    protected $beforeInsert = ['validateRanges'];
    protected $beforeUpdate = ['validateRanges'];

    protected function validateRanges(array $data)
    {
        // Callback receives $data['data'] containing fields to insert/update
        $fields = $data['data'] ?? [];

        $start = isset($fields['start_range']) && $fields['start_range'] !== '' ? (int)$fields['start_range'] : null;
        $end = isset($fields['end_range']) && $fields['end_range'] !== '' ? (int)$fields['end_range'] : null;
        $next = isset($fields['next_number']) && $fields['next_number'] !== '' ? (int)$fields['next_number'] : null;

        // Determine current record id (when updating)
        $currentId = null;
        if (isset($data['id'])) {
            $currentId = $data['id'];
        } elseif (isset($fields[$this->primaryKey])) {
            $currentId = $fields[$this->primaryKey];
        } elseif (isset($data['where']) && is_array($data['where']) && isset($data['where'][$this->primaryKey])) {
            $currentId = $data['where'][$this->primaryKey];
        }

        // If ranges provided, ensure start <= end and next within range
        if ($start !== null && $end !== null) {
            if ($start > $end) {
                // Attach an error; do not break the callback contract (must return $data array)
                $this->validator = \Config\Services::validation();
                $this->validator->setError('start_range', 'Start range must be less than or equal to end range');
                return $data;
            }
            if ($next !== null && ($next < $start || $next > $end)) {
                $this->validator = \Config\Services::validation();
                $this->validator->setError('next_number', 'Next number must be within the start and end range');
                return $data;
            }
        }

        // Check for overlapping ranges with other categories
        if ($start !== null && $end !== null) {
            // Query for any category where ranges overlap: NOT (other.end < start OR other.start > end)
            // Use the model's builder (already targets this table) - do not call from() again
            $builder = $this->builder();
            $builder->select('id');
            $builder->where('NOT (COALESCE(end_range, 0) < ' . $start . ' OR COALESCE(start_range, 0) > ' . $end . ')');
            if ($currentId !== null) {
                $builder->where('id !=', $currentId);
            }
            $conflict = $builder->limit(1)->get()->getRow();
            if ($conflict) {
                $this->validator = \Config\Services::validation();
                $this->validator->setError('start_range', 'The provided numeric range overlaps with another category (id: ' . ($conflict->id ?? '?') . '). Please choose a non-overlapping range.');
                return $data;
            }
        }

        return $data;
    }

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;

    /**
     * Get active categories for dropdown
     */
    public function getActiveCategoriesForDropdown(): array
    {
        $categories = $this->where('is_active', true)
                           ->orderBy('name', 'ASC')
                           ->findAll();

        $dropdown = ['' => 'Select Category'];
        foreach ($categories as $category) {
            $dropdown[$category['id']] = $category['name'];
        }

        return $dropdown;
    }

    /**
     * Get category with product count
     */
    public function getCategoriesWithProductCount(): array
    {
    return $this->select('product_categories.*, COUNT(p.id) as product_count')
            ->join('products p', 'p.category_id = product_categories.id', 'left')
            ->where('product_categories.is_active', true)
            ->groupBy('product_categories.id')
            ->orderBy('product_categories.name', 'ASC')
            ->findAll();
    }
}
