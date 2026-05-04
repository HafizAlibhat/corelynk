<?php

namespace App\Services;

use Config\Database;
use Exception;

class SkuService
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Allocate the next product SKU/code for a category using the universal
     * art number system.
     *
     * Format: RI-<SUFFIX>-<PADDED_GLOBAL_NUMBER>
     *
     * Delegates to ArtNumberService so that products and variants share the
     * same global counter.
     *
     * @param int $categoryId  product_categories.id
     * @return string  e.g. "RI-KS-00001"
     * @throws Exception on missing suffix, DB error, etc.
     */
    public function allocateSku(int $categoryId): string
    {
        $artService = new ArtNumberService();

        // Attempt allocation with retry (skip codes already in use)
        $maxAttempts = 20;
        $db = $this->db;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $sku = $artService->generateForCategory($categoryId);

            // Check if this code already exists in products table
            $existing = $db->query('SELECT id FROM products WHERE code = ? LIMIT 1', [$sku])->getRow();

            if (! $existing) {
                return $sku;
            }
            // Code already taken — loop will allocate next number automatically
        }

        throw new Exception('No available SKU codes found after ' . $maxAttempts . ' attempts for this category');
    }
}
