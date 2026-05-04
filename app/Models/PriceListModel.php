<?php
namespace App\Models;

use CodeIgniter\Model;

class PriceListModel extends Model
{
    protected $table = 'price_lists';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['customer_id','name','is_active','valid_from','valid_to','created_by','created_at','updated_at'];

    /**
     * Get price lists for a customer
     */
    public function getCustomerPriceList(int $customerId): array
    {
        return $this->where('customer_id', $customerId)->where('is_active',1)->orderBy('valid_from','DESC')->findAll();
    }

    public function getActivePriceListForDate(int $customerId, string $asOfDate = null)
    {
        $asOf = $asOfDate ?? date('Y-m-d');
        return $this->where('customer_id', $customerId)
                    ->where('is_active',1)
                    ->groupStart()
                        ->where("valid_from <= ", $asOf)
                        ->orWhere('valid_from', null)
                    ->groupEnd()
                    ->groupStart()
                        ->where("valid_to >= ", $asOf)
                        ->orWhere('valid_to', null)
                    ->groupEnd()
                    ->orderBy('valid_from','DESC')
                    ->first();
    }
}
