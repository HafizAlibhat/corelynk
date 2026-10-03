<?php
namespace App\Models;

use CodeIgniter\Model;

class PriceListItemModel extends Model
{
    protected $table = 'price_list_items';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['price_list_id','product_id','variant_id','pricing_mode','special_price','margin_percent','currency','min_quantity','created_at','updated_at'];

    /**
     * Get price for a product within a specific price list obeying min_quantity and tiering.
     * A line for the exact variant wins over a line for the whole product.
     */
    public function getProductPrice(int $priceListId, int $productId, int $quantity = 1, ?int $variantId = null)
    {
        $items = $this->where('price_list_id', $priceListId)->where('product_id', $productId)->orderBy('min_quantity','DESC')->findAll();

        $scopes = $variantId ? [$variantId, null] : [null];
        foreach ($scopes as $scope) {
            foreach ($items as $it) {
                $rowVariant = ((int)($it['variant_id'] ?? 0)) ?: null;
                if ($rowVariant !== $scope) continue;
                if ($quantity >= ($it['min_quantity'] ?? 1)) {
                    return $it;
                }
            }
        }
        return null;
    }

    /**
     * Get customer product price (search across customer's active price list)
     */
    public function getCustomerProductPrice(int $customerId, int $productId, int $quantity = 1, ?int $variantId = null)
    {
        $plModel = new PriceListModel();
        $pl = $plModel->getActivePriceListForDate($customerId);
        if (!$pl) return null;
        return $this->getProductPrice($pl['id'], $productId, $quantity, $variantId);
    }
}
