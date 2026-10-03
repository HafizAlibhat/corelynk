<?php

namespace App\Models;

use CodeIgniter\Model;

class CustomerAddressModel extends Model
{
    protected $table = 'customer_addresses';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'customer_id', 'line1', 'line2', 'city_id', 'state_id', 'country_id', 'city_name', 'state_name', 'postal_code', 'is_billing', 'is_shipping', 'is_default', 'label'
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * The address a document should use for this customer.
     *
     * `is_default` is the flag the customer profile toggles, so it must win;
     * billing/shipping/oldest only break ties for customers that never set one.
     *
     * @return array<string, mixed>|null
     */
    public function primaryFor(int $customerId): ?array
    {
        if ($customerId <= 0) {
            return null;
        }

        return $this->where('customer_id', $customerId)
            ->orderBy('is_default', 'DESC')
            ->orderBy('is_billing', 'DESC')
            ->orderBy('is_shipping', 'DESC')
            ->orderBy('id', 'ASC')
            ->first();
    }
}
