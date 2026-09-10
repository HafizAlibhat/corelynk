<?php

namespace App\Services;

use Config\Database;

/**
 * Turns vendor_send_notes (lots sent out for a preparation step — coloring,
 * plasma coating, etc.) into a payable Purchase Order per vendor.
 *
 * One sales order can fan a step out to several vendors, and each lot can
 * carry its own price (blue/black/gold/purple/rose-gold all differ), so
 * this groups by vendor_id and puts one PO line per lot on that vendor's PO.
 *
 * These POs never go through the normal GRN "receive against PO" screen —
 * the goods already moved through VendorReceive::store(), which is also
 * what fills qty_received on these lines (see the hook there). A GRN
 * receipt here would double-count the stock.
 */
class VendorJobPoService
{
    private const SOURCE_TYPE = 'subcontract_job';

    public function createForSalesOrder(int $salesOrderId, int $userId): array
    {
        $db = Database::connect();

        $lots = $db->table('vendor_send_notes vsn')
            ->select('vsn.id, vsn.reference_no, vsn.vendor_id, vsn.step_id, vsn.product_id, vsn.qty, ps.name AS step_name, p.name AS product_name')
            ->join('preparation_steps ps', 'ps.id = vsn.step_id', 'left')
            ->join('products p', 'p.id = vsn.product_id', 'left')
            ->where('vsn.sales_order_id', $salesOrderId)
            ->whereIn('vsn.status', ['sent', 'completed'])
            ->where('NOT EXISTS (SELECT 1 FROM purchase_order_lines pol WHERE pol.vendor_send_note_id = vsn.id)', null, false)
            ->orderBy('vsn.vendor_id', 'ASC')
            ->orderBy('vsn.id', 'ASC')
            ->get()->getResultArray();

        if (empty($lots)) {
            return ['ok' => false, 'message' => 'No un-invoiced vendor lots found for this sales order.', 'created' => []];
        }

        $lotIds = array_column($lots, 'id');
        $itemsByNote = [];
        foreach ($db->table('vendor_send_note_items')->whereIn('send_note_id', $lotIds)->get()->getResultArray() as $item) {
            $itemsByNote[(int) $item['send_note_id']][] = $item;
        }

        $byVendor = [];
        foreach ($lots as $lot) {
            $items = $itemsByNote[(int) $lot['id']] ?? [];
            $hasPrice = false;
            foreach ($items as $item) {
                if ((float) ($item['unit_price'] ?? 0) > 0) {
                    $hasPrice = true;
                    break;
                }
            }
            if (! $hasPrice) {
                continue;
            }
            $byVendor[(int) $lot['vendor_id']][] = ['lot' => $lot, 'items' => $items];
        }

        if (empty($byVendor)) {
            return ['ok' => false, 'message' => 'None of the outstanding vendor lots have a job price set yet.', 'created' => []];
        }

        $created = [];
        $db->transStart();
        try {
            foreach ($byVendor as $vendorId => $rows) {
                $created[] = $this->createPoForVendor($db, (int) $vendorId, $salesOrderId, $rows, $userId);
            }
            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            return ['ok' => false, 'message' => 'Failed to create job PO(s): ' . $e->getMessage(), 'created' => []];
        }

        if (! $db->transStatus()) {
            return ['ok' => false, 'message' => 'Failed to create job PO(s).', 'created' => []];
        }

        return ['ok' => true, 'message' => count($created) . ' job PO(s) created.', 'created' => $created];
    }

    private function createPoForVendor(\CodeIgniter\Database\BaseConnection $db, int $vendorId, int $salesOrderId, array $rows, int $userId): array
    {
        $vendor = $db->table('vendors')->select('id, name')->where('id', $vendorId)->get()->getRowArray();

        $lines = [];
        $subtotal = 0.0;
        foreach ($rows as $row) {
            $lot = $row['lot'];
            foreach ($row['items'] as $item) {
                $unitPrice = (float) ($item['unit_price'] ?? 0);
                if ($unitPrice <= 0) {
                    continue;
                }
                $qty = (float) $item['qty'];
                $lineTotal = round($qty * $unitPrice, 2);
                $subtotal += $lineTotal;
                $lines[] = [
                    'vendor_send_note_id' => (int) $lot['id'],
                    'product_id'          => null,
                    'description'         => trim(($lot['step_name'] ?? 'Job work') . ' — ' . ($lot['product_name'] ?? '') . ' — Lot ' . $lot['reference_no']),
                    'qty'                 => $qty,
                    'unit_price'          => $unitPrice,
                    'line_total'          => $lineTotal,
                    'qty_received'        => 0,
                    'created_at'          => date('Y-m-d H:i:s'),
                ];
            }
        }

        if (empty($lines)) {
            throw new \RuntimeException('No priced lots for vendor #' . $vendorId);
        }

        $poNumber = $this->nextPoNumber($db);
        $poModel = new \App\Models\PurchaseOrderModel();
        $poId = (int) $poModel->insert([
            'po_number'     => $poNumber,
            'vendor_id'     => $vendorId,
            'status'        => 'confirmed',
            'order_date'    => date('Y-m-d'),
            'currency_code' => 'PKR',
            'subtotal'      => round($subtotal, 2),
            'tax_total'     => 0,
            'total'         => round($subtotal, 2),
            'source_type'   => self::SOURCE_TYPE,
            'created_by'    => $userId ?: null,
            'created_at'    => date('Y-m-d H:i:s'),
        ], true);

        if ($poId <= 0) {
            throw new \RuntimeException('Failed to create PO for vendor #' . $vendorId);
        }

        foreach ($lines as &$line) {
            $line['po_id'] = $poId;
        }
        unset($line);
        $db->table('purchase_order_lines')->insertBatch($lines);

        return [
            'po_id'      => $poId,
            'po_number'  => $poNumber,
            'vendor_id'  => $vendorId,
            'vendor_name'=> $vendor['name'] ?? ('Vendor #' . $vendorId),
            'lines'      => count($lines),
            'total'      => round($subtotal, 2),
        ];
    }

    private function nextPoNumber(\CodeIgniter\Database\BaseConnection $db): string
    {
        $prefixRow = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'purchase_rfq_prefix' LIMIT 1")->getRowArray();
        $prefix = trim((string) ($prefixRow['setting_value'] ?? '')) ?: 'RI-PO-';

        $lastNumber = 0;
        foreach (['purchase_rfqs' => 'rfq_number', 'purchase_orders' => 'po_number'] as $table => $col) {
            $row = $db->query("SELECT {$col} AS doc_number FROM {$table} WHERE {$col} LIKE ? ORDER BY {$col} DESC LIMIT 1 FOR UPDATE", [$prefix . '%'])->getRowArray();
            if (! empty($row['doc_number'])) {
                $lastNumber = max($lastNumber, (int) preg_replace('/[^0-9]/', '', (string) $row['doc_number']));
            }
        }

        return $prefix . str_pad((string) ($lastNumber + 1), 4, '0', STR_PAD_LEFT);
    }
}
