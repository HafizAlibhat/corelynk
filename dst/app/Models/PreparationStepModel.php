<?php

namespace App\Models;

use CodeIgniter\Model;

class PreparationStepModel extends Model
{
    protected $table            = 'preparation_steps';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'profile_id',
        'step_order',
        'name',
        'description',
        'is_optional',
        'created_at',
    ];

    public function getByProfile(int $profileId): array
    {
        return $this->where('profile_id', $profileId)
            ->orderBy('step_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    public function addStep(array $data): int
    {
        $this->insert($data);
        return (int) $this->getInsertID();
    }

    public function deleteByProfile(int $profileId): bool
    {
        return (bool) $this->where('profile_id', $profileId)->delete();
    }
}
