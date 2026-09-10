<?php

namespace App\Models;

use CodeIgniter\Model;

class VendorSendNoteModel extends Model
{
    protected $table            = 'vendor_send_notes';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'reference_no',
        'parent_send_note_id',
        'origin',
        'vendor_id',
        'step_id',
        'product_id',
        'sales_order_id',
        'sales_order_line_id',
        'qty',
        'qty_rerouted',
        'from_location_id',
        'to_location_id',
        'status',
        'created_at',
    ];

    /**
     * What the vendor still physically holds for this note: what we sent, minus
     * what has come back, minus what was collected and handed to someone else.
     */
    public function outstandingQty(int $sendNoteId): float
    {
        $note = $this->find($sendNoteId);
        if (! $note) {
            return 0.0;
        }

        $received = (float) ($this->db->table('vendor_receive_notes vrn')
            ->selectSum('vri.qty_received', 'total')
            ->join('vendor_receive_items vri', 'vri.receive_note_id = vrn.id', 'inner')
            ->where('vrn.send_note_id', $sendNoteId)
            ->get()->getRowArray()['total'] ?? 0);

        return max(0.0, (float) $note['qty'] - $received - (float) ($note['qty_rerouted'] ?? 0));
    }

    /**
     * The product that physically sits at the vendor. On a sales order the note
     * header carries the finished product while the line carries the material
     * actually shipped, so every stock move must follow the item, not the header.
     */
    public function physicalProductId(int $sendNoteId, int $fallbackProductId = 0): int
    {
        $item = $this->db->table('vendor_send_note_items')
            ->select('product_id')
            ->where('send_note_id', $sendNoteId)
            ->orderBy('id', 'ASC')
            ->get()->getRowArray();

        return (int) ($item['product_id'] ?? 0) ?: $fallbackProductId;
    }

    public function createSendNote(array $data): int
    {
        $this->insert($data);
        return (int) $this->getInsertID();
    }
}
