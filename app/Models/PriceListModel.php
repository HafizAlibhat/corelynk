<?php
namespace App\Models;

use CodeIgniter\Model;

class PriceListModel extends Model
{
    protected $table = 'price_lists';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['party_type','customer_id','vendor_id','name','currency','pricing_mode','margin_percent','margin_method','is_active','valid_from','valid_to','created_by','created_at','updated_at'];

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

    /**
     * Lists a document can pick from: the party's own lists plus the
     * company-wide ones (no party set), active on the given date.
     */
    public function optionsFor(string $partyType, int $partyId = 0, ?string $asOf = null): array
    {
        $asOf = $asOf ?: date('Y-m-d');
        $col  = $partyType === 'vendor' ? 'vendor_id' : 'customer_id';

        return $this->where('party_type', $partyType === 'vendor' ? 'vendor' : 'customer')
            ->where('is_active', 1)
            ->groupStart()
                ->where($col, $partyId > 0 ? $partyId : null)
                ->orWhere($col, null)
            ->groupEnd()
            ->groupStart()->where('valid_from <=', $asOf)->orWhere('valid_from', null)->groupEnd()
            ->groupStart()->where('valid_to >=', $asOf)->orWhere('valid_to', null)->groupEnd()
            ->orderBy('customer_id IS NULL AND vendor_id IS NULL', 'ASC', false)
            ->orderBy('name', 'ASC')
            ->findAll();
    }
}
