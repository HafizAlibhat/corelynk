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
        'vendor_id',
        'step_id',
        'product_id',
        'qty',
        'from_location_id',
        'to_location_id',
        'status',
        'created_at',
    ];

    public function createSendNote(array $data): int
    {
        $this->insert($data);
        return (int) $this->getInsertID();
    }
}
