<?php
namespace App\Models;

use CodeIgniter\Model;

class PaymentTermModel extends Model
{
    protected $table = 'payment_terms';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'name', 'code', 'description', 'net_days', 'discount_days',
        'discount_percentage', 'milestones', 'is_active',
    ];
    protected $useTimestamps = false;

    protected $validationRules = [
        'name' => 'required|max_length[100]',
        'code' => 'required|max_length[20]|is_unique[payment_terms.code,id,{id}]',
    ];

    public function active(): array
    {
        return $this->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
    }
}
