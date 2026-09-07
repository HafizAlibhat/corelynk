<?php

namespace App\Models;

use CodeIgniter\Model;

class VendorReceiveNoteModel extends Model
{
    protected $table            = 'vendor_receive_notes';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'reference_no',
        'vendor_id',
        'send_note_id',
        'created_at',
    ];
}
