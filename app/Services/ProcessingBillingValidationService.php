<?php

namespace App\Services;

use Config\Database;

class ProcessingBillingValidationService
{
    public function getSummary(int $processingRecordId, ?int $excludeBillId = null): array
    {
        $db = Database::connect();

        $processedQty = 0.0;
        try {
            $row = $db->table('processing_records')
                ->select('qty')
                ->where('id', $processingRecordId)
                ->get()
                ->getRowArray();
            $processedQty = (float) ($row['qty'] ?? 0);
        } catch (\Throwable $_) {
            $processedQty = 0.0;
        }

        $billedQty = 0.0;
        try {
            $builder = $db->table('vendor_bill_lines vbl')
                ->select('COALESCE(SUM(vbl.qty), 0) AS billed_qty', false)
                ->join('vendor_bills vb', 'vb.id = vbl.vendor_bill_id', 'inner')
                ->where('vbl.processing_record_id', $processingRecordId)
                ->where('LOWER(COALESCE(vb.status, "")) <>', 'cancelled');

            if ($excludeBillId !== null && $excludeBillId > 0) {
                $builder->where('vb.id !=', $excludeBillId);
            }

            $billedRow = $builder->get()->getRowArray();
            $billedQty = (float) ($billedRow['billed_qty'] ?? 0);
        } catch (\Throwable $_) {
            $billedQty = 0.0;
        }

        $remainingQty = max(0.0, round($processedQty - $billedQty, 4));

        return [
            'processing_record_id' => $processingRecordId,
            'total_processed_qty' => round($processedQty, 4),
            'total_billed_qty' => round($billedQty, 4),
            'remaining_qty' => $remainingQty,
        ];
    }

    public function validateBillQuantity(int $processingRecordId, float $billQty, ?int $excludeBillId = null): array
    {
        $summary = $this->getSummary($processingRecordId, $excludeBillId);
        $qty = max(0.0, round($billQty, 4));

        return [
            'valid' => $qty <= ((float) $summary['remaining_qty'] + 0.0001),
            'requested_qty' => $qty,
            'summary' => $summary,
        ];
    }
}
