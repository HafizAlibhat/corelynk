<?php

namespace App\Controllers;

use App\Services\PriceListService;

/**
 * Price list lookups shared by the sales (customer) and purchase (vendor)
 * documents. Read-only: nothing here writes to a document.
 */
class Pricing extends BaseController
{
    /** GET /pricing/lists?party_type=customer|vendor&party_id=12 */
    public function lists()
    {
        $partyType = $this->request->getGet('party_type') === 'vendor' ? 'vendor' : 'customer';
        $partyId   = (int)$this->request->getGet('party_id');

        return $this->response->setJSON((new PriceListService())->lists($partyType, $partyId));
    }

    /**
     * POST /pricing/resolve
     * { party_type, party_id, price_list_id, currency, lines: [{key, product_id, variant_id, quantity}] }
     */
    public function resolve()
    {
        $payload = $this->request->getJSON(true) ?: $this->request->getPost();
        $lines   = $payload['lines'] ?? [];
        if (! is_array($lines)) {
            $lines = [];
        }

        $svc = new PriceListService();
        $out = [];
        foreach ($lines as $i => $ln) {
            $productId = (int)($ln['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            $price = $svc->resolve([
                'party_type'    => $payload['party_type'] ?? 'customer',
                'party_id'      => (int)($payload['party_id'] ?? 0),
                'price_list_id' => (int)($payload['price_list_id'] ?? 0),
                'currency'      => $payload['currency'] ?? '',
                'product_id'    => $productId,
                'variant_id'    => (int)($ln['variant_id'] ?? 0),
                'quantity'      => (float)($ln['quantity'] ?? 1),
            ]);
            $price['key'] = $ln['key'] ?? $i;
            $out[]        = $price;
        }

        return $this->response->setJSON(['success' => true, 'prices' => $out]);
    }
}
