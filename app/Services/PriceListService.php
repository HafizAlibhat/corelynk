<?php

namespace App\Services;

use App\Models\PriceListModel;
use App\Models\PriceListItemModel;

/**
 * Single place that answers "what does this product cost this party?".
 *
 * Precedence, highest first:
 *   1. price list item for the product (fixed price, or its own margin rule)
 *   2. the price list rule (margin on cost, or discount on the base price)
 *   3. the product base price (sale price for customers, cost price for vendors)
 *
 * A manual price typed on a document always wins: the caller decides whether
 * to apply what this service returns.
 */
class PriceListService
{
    public const MODE_FIXED    = 'fixed';
    public const MODE_MARGIN   = 'margin_on_cost';
    public const MODE_DISCOUNT = 'discount_on_base';

    /** Selectable lists for a party (own lists + company-wide lists). */
    public function lists(string $partyType, int $partyId = 0, ?string $asOf = null): array
    {
        return (new PriceListModel())->optionsFor($partyType, $partyId, $asOf);
    }

    /**
     * @param array $ctx party_type, party_id, price_list_id, product_id,
     *                   variant_id, quantity, currency (document currency), as_of
     *
     * @return array unit_price, currency, source, list_id, list_name, basis, basis_amount
     */
    public function resolve(array $ctx): array
    {
        $partyType = ($ctx['party_type'] ?? 'customer') === 'vendor' ? 'vendor' : 'customer';
        $productId = (int)($ctx['product_id'] ?? 0);
        $variantId = (int)($ctx['variant_id'] ?? 0);
        $qty       = max(1.0, (float)($ctx['quantity'] ?? 1));
        $docCcy    = strtoupper(trim((string)($ctx['currency'] ?? ''))) ?: 'USD';
        $asOf      = $ctx['as_of'] ?? date('Y-m-d');

        $out = [
            'unit_price'   => 0.0,
            'currency'     => $docCcy,
            'source'       => 'base',
            'list_id'      => null,
            'list_name'    => null,
            'basis'        => null,
            'basis_amount' => 0.0,
        ];
        if ($productId <= 0) {
            return $out;
        }

        $product = $this->product($productId);
        if (! $product) {
            return $out;
        }

        // A variant carries its own sale/cost price; fall back to the product.
        $variant = $variantId > 0 ? $this->variant($variantId, $productId) : null;
        $saleRaw = $variant && (float)($variant['price'] ?? 0) > 0 ? (float)$variant['price'] : (float)($product['sale_price'] ?? 0);
        $saleCcy = $variant && (float)($variant['price'] ?? 0) > 0 ? ($variant['sale_currency'] ?: ($product['sale_currency'] ?? $docCcy)) : ($product['sale_currency'] ?? $docCcy);
        $costRaw = $variant && (float)($variant['cost'] ?? 0) > 0 ? (float)$variant['cost'] : (float)($product['cost_price'] ?? 0);
        $costCcy = $variant && (float)($variant['cost'] ?? 0) > 0 ? ($variant['cost_currency'] ?: ($product['cost_currency'] ?? $docCcy)) : ($product['cost_currency'] ?? $docCcy);

        $costAmount = $this->toCurrency($costRaw, $costCcy, $docCcy, $asOf);
        $baseAmount = $partyType === 'vendor' ? $costAmount : $this->toCurrency($saleRaw, $saleCcy, $docCcy, $asOf);

        $out['unit_price']   = round($baseAmount, 2);
        $out['basis']        = $partyType === 'vendor' ? 'cost_price' : 'sale_price';
        $out['basis_amount'] = round($baseAmount, 2);

        $list = $this->pickList($ctx, $partyType, $asOf);
        if (! $list) {
            return $out;
        }

        $out['list_id']   = (int)$list['id'];
        $out['list_name'] = $list['name'] ?? null;
        $listCcy          = strtoupper(trim((string)($list['currency'] ?? ''))) ?: $docCcy;

        $item = (new PriceListItemModel())->getProductPrice((int)$list['id'], $productId, (int)ceil($qty), $variantId ?: null);

        // 1. item level
        if ($item) {
            $itemMargin = $item['margin_percent'] ?? null;
            $itemMode   = $item['pricing_mode'] ?? null;
            if ($itemMode === self::MODE_MARGIN || ($itemMode === null && $itemMargin !== null && (float)$item['special_price'] <= 0)) {
                $out['unit_price']   = $this->applyMargin($costAmount, (float)$itemMargin, $list['margin_method'] ?? 'markup');
                $out['source']       = 'price_list_item_margin';
                $out['basis']        = 'cost_price';
                $out['basis_amount'] = round($costAmount, 2);

                return $out;
            }
            if ((float)$item['special_price'] > 0) {
                $itemCcy           = strtoupper(trim((string)($item['currency'] ?? ''))) ?: $listCcy;
                $out['unit_price'] = round($this->toCurrency((float)$item['special_price'], $itemCcy, $docCcy, $asOf), 2);
                $out['source']     = 'price_list_item';

                return $out;
            }
        }

        // 2. list level rule
        $mode   = $list['pricing_mode'] ?? self::MODE_FIXED;
        $margin = isset($list['margin_percent']) && $list['margin_percent'] !== null ? (float)$list['margin_percent'] : null;

        if ($mode === self::MODE_MARGIN && $margin !== null) {
            $out['unit_price']   = $this->applyMargin($costAmount, $margin, $list['margin_method'] ?? 'markup');
            $out['source']       = 'price_list_margin';
            $out['basis']        = 'cost_price';
            $out['basis_amount'] = round($costAmount, 2);

            return $out;
        }
        if ($mode === self::MODE_DISCOUNT && $margin !== null) {
            $out['unit_price'] = round($baseAmount * (1 - ($margin / 100)), 2);
            $out['source']     = 'price_list_discount';

            return $out;
        }

        return $out;
    }

    /**
     * markup: price = cost x (1 + p/100)  -> "add 30% on top of cost"
     * margin: price = cost / (1 - p/100)  -> "30% of the selling price is profit"
     */
    public function applyMargin(float $cost, float $percent, string $method = 'markup'): float
    {
        if ($cost <= 0) {
            return 0.0;
        }
        if ($method === 'margin') {
            $percent = min($percent, 99.99);

            return round($cost / (1 - ($percent / 100)), 2);
        }

        return round($cost * (1 + ($percent / 100)), 2);
    }

    private function pickList(array $ctx, string $partyType, string $asOf): ?array
    {
        $model  = new PriceListModel();
        $listId = (int)($ctx['price_list_id'] ?? 0);
        if ($listId > 0) {
            $list = $model->find($listId);

            return ($list && (int)($list['is_active'] ?? 1) === 1) ? $list : null;
        }

        // No explicit choice: fall back to the party's own active list, then to
        // a company-wide one.
        $lists = $model->optionsFor($partyType, (int)($ctx['party_id'] ?? 0), $asOf);
        $col   = $partyType === 'vendor' ? 'vendor_id' : 'customer_id';
        foreach ($lists as $l) {
            if ((int)($l[$col] ?? 0) > 0) {
                return $l;
            }
        }

        return $lists[0] ?? null;
    }

    private function product(int $productId): ?array
    {
        $db = \Config\Database::connect();
        if (! $db->tableExists('products')) {
            return null;
        }

        return $db->table('products')->where('id', $productId)->get()->getRowArray();
    }

    private function variant(int $variantId, int $productId): ?array
    {
        $db = \Config\Database::connect();
        if (! $db->tableExists('product_variants')) {
            return null;
        }
        $row = $db->table('product_variants')->where('id', $variantId)->get()->getRowArray();

        return ($row && (int)$row['product_id'] === $productId) ? $row : null;
    }

    private function toCurrency(float $amount, ?string $from, string $to, string $asOf): float
    {
        $from = strtoupper(trim((string)$from));
        $to   = strtoupper(trim($to));
        if ($amount == 0.0 || $from === '' || $from === $to) {
            return $amount;
        }
        try {
            helper('currency');

            return convert_amount($amount, $from, $to, $asOf);
        } catch (\Throwable $e) {
            log_message('error', 'Price list currency conversion failed: ' . $e->getMessage());

            return $amount;
        }
    }
}
