<?php
namespace App\Models;

use CodeIgniter\Model;

class QuotationLineModel extends Model
{
    protected $table = 'quotation_lines';
    protected $primaryKey = 'id';
    protected $useTimestamps = false;
    protected $allowedFields = ['quotation_id','product_id','product_variant_id','product_code','product_name','description','quantity','unit','unit_price','line_total','line_number','discount_type','discount_value','discount_amount','tax_rate','tax_amount','weight','unit_weight','weight_unit','vendor_id','cost_price','sale_price_currency','sort_order','base_amount','net_amount','product_image_url'];

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
        $price = (float)($line['unit_price'] ?? $line['price'] ?? 0);
        $discountType = $line['discount_type'] ?? 'percent';
        $discountValue = isset($line['discount_value']) ? (float)$line['discount_value'] : (isset($line['document_discount_value']) ? (float)$line['document_discount_value'] : 0.0);
        $taxRate = isset($line['tax_rate']) ? (float)$line['tax_rate'] : (isset($line['tax']) ? (float)$line['tax'] : 0.0);

        // 1) Base amount
        $base = $qty * $price;

        // 2) Discount
        if ($discountType === 'percent') {
            $discountAmount = $base * ($discountValue / 100.0);
        } else {
            $discountAmount = $discountValue;
        }

        // 3) Net amount after discount
        $net = max(0, $base - $discountAmount);

        // 4) Tax on net amount
        $taxAmount = $net * ($taxRate / 100.0);

        // 5) Line total
        $lineTotal = $net + $taxAmount;

        // Round values to two decimals for currency-safe display/storage
        return [
            'base_amount' => round($base, 2),
            'discount_amount' => round($discountAmount, 2),
            'tax_amount' => round($taxAmount, 2),
            'line_total' => round($lineTotal, 2),
            // include some helpful internals
            'net_amount' => round($net, 2),
            'quantity' => $qty,
            'unit_price' => round($price, 2),
            'discount_value' => $discountValue,
            'discount_type' => $discountType,
            'tax_rate' => $taxRate,
        ];
    }

    public function getProductDetails(int $productId)
    {
        $pm = new \App\Models\ProductModel();
        return $pm->getWithVendorInfo($productId);
    }
}
