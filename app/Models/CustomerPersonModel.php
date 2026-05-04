<?php

namespace App\Models;

use CodeIgniter\Model;

class CustomerPersonModel extends Model
{
    protected $table = 'customer_persons';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'customer_id', 'name', 'title', 'email', 'phone', 'mobile',
        'is_primary_contact', 'is_active'
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    
    /**
     * Get primary contact person for a customer
     */
    public function getPrimaryContact(int $customerId): ?array
    {
        return $this->where('customer_id', $customerId)
            ->where('is_primary_contact', 1)
            ->where('is_active', 1)
            ->first();
    }
    
    /**
     * Get all active persons for a customer
     */
    public function getActivePersons(int $customerId): array
    {
        return $this->where('customer_id', $customerId)
            ->where('is_active', 1)
            ->orderBy('is_primary_contact', 'DESC')
            ->orderBy('created_at', 'ASC')
            ->findAll();
    }
}
