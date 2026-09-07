<?php

namespace App\Services;

use App\Models\QuotationModel;
use App\Models\QuotationLineModel;

class QuotationService
{
    /**
     * Recalculate and persist total_weight for a quotation using product weights.
     *
     * @param int $quotationId
     * @return float Calculated total weight (kg)
     */
    public function recalculateWeight(int $quotationId): float
    {
        $quotationId = (int)$quotationId;
        if ($quotationId <= 0) {
            return 0.0;
        }

        $lineModel = new QuotationLineModel();
        $lines = $lineModel->where('quotation_id', $quotationId)->findAll();
        if (empty($lines)) {
            $this->updateHeaderWeight($quotationId, 0.0);
            return 0.0;
        }

        $quote = (new QuotationModel())->find($quotationId) ?? [];
        $shippingAmount = isset($quote['shipping_amount']) ? (float)$quote['shipping_amount'] : 0.0;
        $totals = (new QuotationModel())->calculateTotals($lines, $shippingAmount);
        $totalWeight = round((float)($totals['total_weight'] ?? 0.0), 3);
        $this->updateHeaderWeight($quotationId, $totalWeight);

        return $totalWeight;
    }

    /**
     * Persist weight to quotations table if column exists.
     */
    protected function updateHeaderWeight(int $quotationId, float $totalWeight): void
    {
        $qModel = new QuotationModel();
        $db = \Config\Database::connect();
        try {
            $cols = $db->getFieldNames($qModel->table);
        } catch (\Throwable $_) {
            $cols = $qModel->allowedFields;
        }
        if (!in_array('total_weight', $cols)) {
            return;
        }
        try {
            $upd = ['total_weight' => $totalWeight];
            $qModel->update($quotationId, $upd);
        } catch (\Throwable $_) {
            // best-effort; swallow errors so calling flow continues
        }
    }
}
