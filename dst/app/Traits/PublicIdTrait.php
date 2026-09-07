<?php

namespace App\Traits;

/**
 * Public ID trait for models.
 *
 * Adds UUID-based public_id support to any model. Public IDs are
 * generated automatically on insert and can be used as URL-safe
 * identifiers to prevent numeric ID enumeration.
 *
 * Usage:
 *   class VendorModel extends Model {
 *       use PublicIdTrait;
 *   }
 *
 * Then in constructors or init methods call:
 *   $this->bootPublicId();
 *
 * Route resolution example:
 *   $vendor = $vendorModel->findByPublicIdOrId($slug);
 *   // accepts both "5" and "vb_a1b2c3d4-..."
 */
trait PublicIdTrait
{
    /** @var array<string,bool> */
    private static array $publicIdColumnCache = [];

    private function hasPublicIdColumn(): bool
    {
        $table = (string)($this->table ?? '');
        if ($table === '') {
            return false;
        }
        if (array_key_exists($table, self::$publicIdColumnCache)) {
            return self::$publicIdColumnCache[$table];
        }
        try {
            return self::$publicIdColumnCache[$table] = (bool)$this->db->fieldExists('public_id', $table);
        } catch (\Throwable) {
            return self::$publicIdColumnCache[$table] = false;
        }
    }

    /**
     * Boot public ID auto-generation. Call after parent::__construct().
     */
    protected function bootPublicId(): void
    {
        // Only if the table has a public_id column
        if (! $this->hasPublicIdColumn()) {
            return;
        }

        $this->beforeInsert[] = 'generatePublicId';
    }

    /**
     * Auto-generate a UUID v4 public_id before insert.
     */
    protected function generatePublicId(array $data): array
    {
        if (isset($data['data']) && is_array($data['data'])) {
            if (empty($data['data']['public_id'])) {
                $data['data']['public_id'] = self::uuid4();
            }
        }
        return $data;
    }

    /**
     * Find a record by public_id OR numeric id.
     *
     * Allows routes to accept either:
     *   /vendor-bills/5          → legacy numeric
     *   /vendor-bills/abc12345   → public_id
     *
     * @param string|int $identifier  Numeric ID or public_id string
     * @return array|null
     */
    public function findByPublicIdOrId($identifier): ?array
    {
        // If purely numeric, look up by primary key
        if (is_numeric($identifier)) {
            return $this->find((int) $identifier) ?: null;
        }

        // Otherwise try public_id
        if ($this->hasPublicIdColumn()) {
            return $this->where('public_id', $identifier)->first() ?: null;
        }

        return null;
    }

    /**
     * Generate a UUID v4.
     */
    public static function uuid4(): string
    {
        $data = random_bytes(16);
        // Set version to 0100 (UUID v4)
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set variant to 10xx
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Backfill public_id for existing records that don't have one.
     *
     * Call from a CLI command or one-time migration script:
     *   (new VendorModel())->backfillPublicIds();
     *
     * @return int Number of records updated
     */
    public function backfillPublicIds(int $batchSize = 500): int
    {
        if (! $this->hasPublicIdColumn()) {
            return 0;
        }

        $count = 0;
        do {
            $records = $this->where('public_id IS NULL')
                            ->limit($batchSize)
                            ->find();

            if (empty($records)) {
                break;
            }

            foreach ($records as $record) {
                $this->skipValidation(true)
                     ->update($record[$this->primaryKey], [
                         'public_id' => self::uuid4(),
                     ]);
                $count++;
            }
        } while (count($records) === $batchSize);

        return $count;
    }
}
