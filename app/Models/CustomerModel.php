<?php

namespace App\Models;

use CodeIgniter\Model;
use App\Traits\PublicIdTrait;

class CustomerModel extends Model
{
    use PublicIdTrait;

    protected $table = 'customers';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = [
        'public_id', 'customer_code', 'name', 'company_name', 'type', 'status', 'metadata', 'created_by',
        'odoo_id', 'email', 'phone', 'mobile', 'website',
        // Address/contact fields needed for PDF and view
        'address', 'address1', 'address2', 'billing_address', 'shipping_address',
        'city', 'billing_city', 'postal_code', 'zip'
    ];

    public function __construct(?\CodeIgniter\Database\ConnectionInterface $db = null, ?\CodeIgniter\Validation\ValidationInterface $validation = null)
    {
        parent::__construct($db, $validation);
        $this->bootPublicId();
    }



    public function generateCustomerCode(): string
    {
        $db = $this->db;
        $prefix = $this->getCustomerCodePrefix();
        $seqName = $this->getSequenceName($prefix);

        $this->ensureSequencesTable();

        $db->transStart();

        // Ensure a sequence row exists, then lock it.
        $db->query(
            "INSERT IGNORE INTO `sequences` (`name`, `last_value`, `updated_at`) VALUES (?, 0, NOW())",
            [$seqName]
        );

        $row = $db->query(
            'SELECT last_value FROM `sequences` WHERE `name` = ? FOR UPDATE',
            [$seqName]
        )->getRowArray();

        $lastValue = (int)($row['last_value'] ?? 0);
        $maxExisting = $this->getMaxExistingCustomerCodeNumber($prefix);
        $nextValue = max($lastValue + 1, $maxExisting + 1);

        $db->query(
            'UPDATE `sequences` SET `last_value` = ?, `updated_at` = NOW() WHERE `name` = ?',
            [$nextValue, $seqName]
        );

        $db->transComplete();
        if ($db->transStatus() === false) {
            throw new \RuntimeException('Failed to allocate next customer code.');
        }

        return $prefix . '-' . $nextValue;
    }

    /**
     * Peek the next customer code without incrementing the sequence.
     */
    public function peekNextCustomerCode(): string
    {
        $prefix = $this->getCustomerCodePrefix();
        $nextValue = $this->peekNextCustomerCodeNumber($prefix);
        return $prefix . '-' . $nextValue;
    }

    public function peekNextCustomerCodeNumber(?string $prefix = null): int
    {
        $prefix = $this->normalizePrefix($prefix ?? $this->getCustomerCodePrefix());
        $seqName = $this->getSequenceName($prefix);

        $this->ensureSequencesTable();

        $row = $this->db->query(
            'SELECT last_value FROM `sequences` WHERE `name` = ?',
            [$seqName]
        )->getRowArray();

        $lastValue = (int)($row['last_value'] ?? 0);
        $maxExisting = $this->getMaxExistingCustomerCodeNumber($prefix);
        return max($lastValue + 1, $maxExisting + 1);
    }

    public function setNextCustomerCodeNumber(int $nextNumber, ?string $prefix = null): int
    {
        $prefix = $this->normalizePrefix($prefix ?? $this->getCustomerCodePrefix());
        if ($nextNumber < 1) {
            throw new \RuntimeException('Customer next number must be at least 1.');
        }

        $minAllowed = $this->peekNextCustomerCodeNumber($prefix);
        if ($nextNumber < $minAllowed) {
            throw new \RuntimeException("Cannot set customer next number to {$nextNumber}. Minimum allowed is {$minAllowed}.");
        }

        $this->ensureSequencesTable();
        $seqName = $this->getSequenceName($prefix);
        $this->db->query(
            "INSERT IGNORE INTO `sequences` (`name`, `last_value`, `updated_at`) VALUES (?, 0, NOW())",
            [$seqName]
        );
        $this->db->query(
            'UPDATE `sequences` SET `last_value` = ?, `updated_at` = NOW() WHERE `name` = ?',
            [$nextNumber - 1, $seqName]
        );

        return $nextNumber;
    }

    private function ensureSequencesTable(): void
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `sequences` (
            `name` VARCHAR(100) NOT NULL PRIMARY KEY,
            `last_value` BIGINT UNSIGNED NOT NULL DEFAULT 0,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }

    public function getCustomerCodePrefix(): string
    {
        try {
            $cols = $this->db->getFieldNames('company_settings');
            $selectCols = [];
            if (in_array('customer_code_prefix', $cols, true)) {
                $selectCols[] = 'customer_code_prefix';
            }
            if (in_array('art_number_prefix', $cols, true)) {
                $selectCols[] = 'art_number_prefix';
            }

            if (!empty($selectCols)) {
                $row = $this->db->table('company_settings')
                    ->select(implode(',', $selectCols))
                    ->orderBy('id', 'ASC')
                    ->limit(1)
                    ->get()
                    ->getRowArray();

                $customPrefix = $this->normalizePrefix((string)($row['customer_code_prefix'] ?? ''));
                if ($customPrefix !== '') {
                    return $customPrefix;
                }

                $artPrefix = $this->normalizePrefix((string)($row['art_number_prefix'] ?? ''));
                if ($artPrefix !== '') {
                    return $artPrefix;
                }
            }
        } catch (\Throwable $e) {
            // fallback below
        }

        return 'RI';
    }

    private function getSequenceName(string $prefix): string
    {
        return 'customer_code_' . strtolower($this->normalizePrefix($prefix));
    }

    private function normalizePrefix(string $prefix): string
    {
        $prefix = strtoupper(trim($prefix));
        $prefix = preg_replace('/[^A-Z0-9]/', '', $prefix);
        return $prefix;
    }

    private function getMaxExistingCustomerCodeNumber(string $prefix): int
    {
        $prefix = strtoupper(trim($prefix));
        $row = $this->db->query(
            "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(customer_code, '-', -1) AS UNSIGNED)), 0) AS max_num
             FROM `customers`
             WHERE customer_code REGEXP ?",
            ['^' . $prefix . '-[0-9]+$']
        )->getRowArray();

        return (int)($row['max_num'] ?? 0);
    }
}
