<?php
namespace App\Models;

use CodeIgniter\Model;

class QuotationLineModel extends Model
{
    protected $table = 'quotation_lines';
    protected $primaryKey = 'id';
    protected $useTimestamps = false;
    protected $allowedFields = ['quotation_id','product_id','description','quantity','unit_price','line_total','line_number','discount_type','discount_value','tax_rate','tax_amount','weight','vendor_id','cost_price','sale_price_currency'];

    public function product()
    {
        return $this->belongsTo('App\Models\ProductModel', 'product_id', 'id');
    }

    /**
     * Calculate a line total based on qty, unit_price, discount and tax
     */
    public function calculateLineTotal(array $line): array
    {
        // Expected keys: quantity, unit_price, discount_type, discount_value, tax_rate
        $qty = (float)($line['quantity'] ?? 0);
        $price = (float)($line['unit_price'] ?? 0);
        $discountType = $line['discount_type'] ?? 'percent';
        $discountValue = (float)($line['discount_value'] ?? 0);
        $taxRate = (float)($line['tax_rate'] ?? 0);

        $raw = $qty * $price;
        if ($discountType === 'percent') {
            $discountAmount = $raw * ($discountValue / 100);
        } else {
            $discountAmount = $discountValue;
        }
        $taxable = max(0, $raw - $discountAmount);
        $taxAmount = $taxable * ($taxRate / 100);
        $lineTotal = $taxable + $taxAmount;

        return [
            'raw' => $raw,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'line_total' => $lineTotal
        ];
    }

    public function getProductDetails(int $productId)
    {
        $pm = new \App\Models\ProductModel();
        return $pm->getWithVendorInfo($productId);
    }
}
