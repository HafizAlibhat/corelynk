<?php
namespace App\Models;

use CodeIgniter\Model;

class QuotationModel extends Model
{
    protected $table = 'quotations';
    protected $primaryKey = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'quote_number','customer_id','issue_date','valid_until','subtotal','tax_total','total','status','created_by',
        'document_discount_type','document_discount_value','document_tax_type','document_tax_value','shipping_cost','handling_charges','packaging_charges','insurance_cost','total_weight','exchange_rate','base_currency','quote_currency'
    ];

    public function lines()
    {
        return $this->hasMany('App\Models\QuotationLineModel', 'quotation_id', 'id');
    }

    public function getWithFullDetails(int $id)
    {
        $q = $this->find($id);
        if (!$q) return null;
        $lineModel = new \App\Models\QuotationLineModel();
        $q['lines'] = $lineModel->where('quotation_id',$id)->findAll();
        return $q;
    }

    public function generateQuoteNumber(): string
    {
        $prefix = 'QUO-' . date('Y-m');
        // find last number for month
        $last = $this->select('quote_number')->like('quote_number', $prefix, 'after')->orderBy('id','DESC')->first();
        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last['quote_number'], $m)) {
            $seq = intval($m[1]) + 1;
        }
        return sprintf('%s-%03d', $prefix, $seq);
    }

    /**
     * Calculate totals for a quote payload (not saved)
     */
    public function calculateTotals(array $payload): array
    {
        $subtotal = 0;
        $taxTotal = 0;
        foreach ($payload['lines'] as $ln) {
            $lineCalc = (new \App\Models\QuotationLineModel())->calculateLineTotal($ln);
            $subtotal += $lineCalc['raw'] - $lineCalc['discount_amount'];
            $taxTotal += $lineCalc['tax_amount'];
        }
        // document discount
        $docDiscount = 0;
        if (!empty($payload['document_discount_value'])) {
            if (($payload['document_discount_type'] ?? 'percent') === 'percent') {
                $docDiscount = $subtotal * (($payload['document_discount_value'] ?? 0) / 100);
            } else {
                $docDiscount = (float)$payload['document_discount_value'];
            }
        }
        $subtotalAfterDoc = max(0, $subtotal - $docDiscount);
        // document tax
        $docTax = 0;
        if (!empty($payload['document_tax_value'])) {
            if (($payload['document_tax_type'] ?? 'percent') === 'percent') {
                $docTax = $subtotalAfterDoc * (($payload['document_tax_value'] ?? 0) / 100);
            } else {
                $docTax = (float)$payload['document_tax_value'];
            }
        }
        $shipping = (float)($payload['shipping_cost'] ?? 0);
        $total = $subtotalAfterDoc + $taxTotal + $docTax + $shipping + (float)($payload['handling_charges'] ?? 0) + (float)($payload['packaging_charges'] ?? 0) + (float)($payload['insurance_cost'] ?? 0);

        return [
            'subtotal' => round($subtotal,2),
            'doc_discount' => round($docDiscount,2),
            'tax_total' => round($taxTotal + $docTax,2),
            'shipping' => $shipping,
            'total' => round($total,2)
        ];
    }

    public function applyPriceList(int $customerId, array &$lines)
    {
        $pli = new \App\Models\PriceListItemModel();
        foreach ($lines as &$ln) {
            $plItem = $pli->getCustomerProductPrice($customerId, $ln['product_id'], isset($ln['quantity']) ? (int)$ln['quantity'] : 1);
            if ($plItem) {
                $ln['unit_price'] = $plItem['special_price'];
                $ln['sale_price_currency'] = $plItem['currency'];
                $ln['price_list_applied'] = $plItem['price_list_id'] ?? $plItem['price_list_id'] ?? null;
            }
        }
        return $lines;
    }
}
