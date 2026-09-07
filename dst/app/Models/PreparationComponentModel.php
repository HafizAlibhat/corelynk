<?php

namespace App\Models;

use CodeIgniter\Model;

class PreparationComponentModel extends Model
{
    protected $table            = 'preparation_components';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'profile_id',
        'product_id',
        'variant_id',
        'qty_per_unit',
        'is_optional',
        'created_at',
    ];

    public function getByProfile(int $profileId): array
    {
        return $this->where('profile_id', $profileId)->orderBy('id', 'ASC')->findAll();
    }

    public function getByProfileWithProduct(int $profileId): array
    {
        return $this->select('preparation_components.*, p.name AS product_name, p.code AS product_code, pv.name AS variant_name, pv.art_number AS variant_art_number')
            ->join('products p', 'p.id = preparation_components.product_id', 'left')
            ->join('product_variants pv', 'pv.id = preparation_components.variant_id', 'left')
            ->where('preparation_components.profile_id', $profileId)
            ->orderBy('preparation_components.id', 'ASC')
            ->findAll();
    }

    public function addComponent(array $data): int
    {
        $this->insert($data);
        return (int) $this->getInsertID();
    }

    public function deleteByProfile(int $profileId): bool
    {
        return (bool) $this->where('profile_id', $profileId)->delete();
    }
}
