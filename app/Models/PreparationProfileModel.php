<?php

namespace App\Models;

use CodeIgniter\Model;

class PreparationProfileModel extends Model
{
    protected $table            = 'preparation_profiles';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'product_id',
        'variant_id',
        'name',
        'description',
        'is_active',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function getByProduct(int $productId, bool $activeOnly = true): array
    {
        $builder = $this->where('product_id', $productId);
        if ($this->db->fieldExists('variant_id', $this->table)) {
            $builder->where('variant_id', null);
        }
        if ($activeOnly) {
            $builder->where('is_active', 1);
        }
        return $builder->orderBy('id', 'DESC')->findAll();
    }

    public function getWithCountsByProduct(int $productId, bool $activeOnly = true): array
    {
        $builder = $this->db->table($this->table . ' pp')
            ->select('pp.*')
            ->select('(SELECT COUNT(*) FROM preparation_components pc WHERE pc.profile_id = pp.id) AS materials_count', false)
            ->select('(SELECT COUNT(*) FROM preparation_steps ps WHERE ps.profile_id = pp.id) AS steps_count', false)
            ->where('pp.product_id', $productId);

        if ($this->db->fieldExists('variant_id', $this->table)) {
            $builder->where('pp.variant_id IS NULL', null, false);
        }

        if ($activeOnly) {
            $builder->where('pp.is_active', 1);
        }

        return $builder->orderBy('pp.id', 'DESC')->get()->getResultArray();
    }

    public function getWithCountsByVariant(int $variantId, bool $activeOnly = true): array
    {
        $builder = $this->db->table($this->table . ' pp')
            ->select('pp.*')
            ->select('(SELECT COUNT(*) FROM preparation_components pc WHERE pc.profile_id = pp.id) AS materials_count', false)
            ->select('(SELECT COUNT(*) FROM preparation_steps ps WHERE ps.profile_id = pp.id) AS steps_count', false);

        if ($this->db->fieldExists('variant_id', $this->table)) {
            $builder->where('pp.variant_id', $variantId);
        } else {
            $builder->where('1 = 0', null, false);
        }

        if ($activeOnly) {
            $builder->where('pp.is_active', 1);
        }

        return $builder->orderBy('pp.id', 'DESC')->get()->getResultArray();
    }

    public function createProfile(array $data): int
    {
        $this->insert($data);
        return (int) $this->getInsertID();
    }

    public function updateProfile(int $id, array $data): bool
    {
        return (bool) $this->update($id, $data);
    }

    public function softDeleteProfile(int $id): bool
    {
        return (bool) $this->update($id, ['is_active' => 0]);
    }
}
