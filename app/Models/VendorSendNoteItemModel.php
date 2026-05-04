<?php

namespace App\Models;

use CodeIgniter\Model;

class VendorSendNoteItemModel extends Model
{
    protected $table            = 'vendor_send_note_items';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'send_note_id',
        'product_id',
        'qty',
    ];

    public function addItem(array $data): int
    {
        $this->insert($data);
        return (int) $this->getInsertID();
    }
}
