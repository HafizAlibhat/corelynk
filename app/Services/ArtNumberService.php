<?php
namespace App\Services;

use Config\Database;

class ArtNumberService
{
    /** Fallback company prefix if DB has no value. */
    public const DEFAULT_PREFIX = 'RI';

    /** Minimum digit padding for the sequential number portion. */
    public const PAD_DIGITS = 5;

    protected $db;

    /** Cached brand code loaded from company_settings. */
    protected string $brandCode;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->brandCode = $this->loadBrandCode();
    }

    /**
     * Read the art_number_prefix from company_settings (single-row table).
     */
    protected function loadBrandCode(): string
    {
        try {
            $cols = $this->db->getFieldNames('company_settings');
            if (in_array('art_number_prefix', $cols, true)) {
                $row = $this->db->table('company_settings')
                                ->select('art_number_prefix')
                                ->orderBy('id', 'ASC')
                                ->limit(1)
                                ->get()
                                ->getRowArray();
                $val = strtoupper(trim($row['art_number_prefix'] ?? ''));
                if ($val !== '') return $val;
            }
        } catch (\Throwable $_) {}
        return self::DEFAULT_PREFIX;
    }

    /**
     * Return the current brand code (e.g. "RI").
     */
    public function getBrandCode(): string
    {
        return $this->brandCode;
    }

    // ------------------------------------------------------------------
    //  Universal Art Number Generator
    //  Format: RI-<SUFFIX>-<PADDED_GLOBAL_NUMBER>
    // ------------------------------------------------------------------

    /**
     * Generate the next art number for a given category using the **global**
     * sequential counter and the category's unique suffix.
     *
     * Thread-safe: uses SELECT … FOR UPDATE on the counter row inside a
     * transaction to serialise concurrent allocations.
     *
     * @param int $categoryId  The product_categories.id
     * @return string          e.g. "RI-SI-00001"
     * @throws \RuntimeException on missing data or DB failure
     */
    public function generateForCategory(int $categoryId): string
    {
        $db = $this->db;

        $db->transStart();

        // 1. Fetch category (need suffix)
        $category = $db->query(
            'SELECT id, suffix FROM product_categories WHERE id = ?',
            [$categoryId]
        )->getRowArray();

        if (! $category) {
            $db->transComplete();
            throw new \RuntimeException('Category not found (id=' . $categoryId . ')');
        }

        $suffix = strtoupper(trim($category['suffix'] ?? ''));
        if ($suffix === '') {
            $db->transComplete();
            throw new \RuntimeException(
                'Category "' . $categoryId . '" has no suffix configured. '
                . 'Please set a 2–4 letter suffix in category settings before generating art numbers.'
            );
        }

        // 2. Lock and read the global counter
        $counter = $db->query(
            'SELECT next_number FROM art_number_counter WHERE id = 1 FOR UPDATE'
        )->getRowArray();

        if (! $counter) {
            // Auto-seed if somehow the row is missing
            $db->table('art_number_counter')->insert([
                'id' => 1, 'next_number' => 1, 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $next = 1;
        } else {
            $next = max(1, (int) $counter['next_number']);
        }

        // 3. Build the art number
        $numStr = str_pad((string) $next, self::PAD_DIGITS, '0', STR_PAD_LEFT);
        $art    = $this->brandCode . '-' . $suffix . '-' . $numStr;

        // 4. Increment the global counter
        $db->table('art_number_counter')
           ->where('id', 1)
           ->update([
               'next_number' => $next + 1,
               'updated_at'  => date('Y-m-d H:i:s'),
           ]);

        $db->transComplete();

        if ($db->transStatus() === false) {
            throw new \RuntimeException('Failed to allocate next art number (DB transaction failed)');
        }

        return $art;
    }

    // ------------------------------------------------------------------
    //  Helpers
    // ------------------------------------------------------------------

    /**
     * Preview the *next* art number for a category without incrementing.
     */
    public function previewForCategory(int $categoryId): string
    {
        $db = $this->db;

        $category = $db->table('product_categories')
                       ->where('id', $categoryId)
                       ->get()
                       ->getRowArray();

        if (! $category || empty($category['suffix'])) {
            return '—';
        }

        $counter = $db->table('art_number_counter')
                      ->where('id', 1)
                      ->get()
                      ->getRowArray();

        $next   = max(1, (int) ($counter['next_number'] ?? 1));
        $suffix = strtoupper(trim($category['suffix']));
        $numStr = str_pad((string) $next, self::PAD_DIGITS, '0', STR_PAD_LEFT);

        return $this->brandCode . '-' . $suffix . '-' . $numStr;
    }

    /**
     * Return the current global counter value (without incrementing).
     */
    public function currentGlobalNumber(): int
    {
        $row = $this->db->table('art_number_counter')
                        ->where('id', 1)
                        ->get()
                        ->getRowArray();

        return max(1, (int) ($row['next_number'] ?? 1));
    }

    /**
     * Set the global counter to a specific value.
     * Only allows setting to a value >= the current counter (no going backwards).
     *
     * @param int $value  The new next_number value
     * @param bool $force If true, allow setting to any value >= 1
     * @return int The new counter value
     * @throws \RuntimeException if value is invalid
     */
    public function setGlobalCounter(int $value, bool $force = false): int
    {
        if ($value < 1) {
            throw new \RuntimeException('Counter value must be at least 1.');
        }

        $current = $this->currentGlobalNumber();

        if (!$force && $value < $current) {
            throw new \RuntimeException(
                "Cannot set counter to {$value} — it is lower than the current value ({$current}). "
                . "This could cause duplicate art numbers."
            );
        }

        $this->db->table('art_number_counter')
                  ->where('id', 1)
                  ->update([
                      'next_number' => $value,
                      'updated_at'  => date('Y-m-d H:i:s'),
                  ]);

        return $value;
    }
}
