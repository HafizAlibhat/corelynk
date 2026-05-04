<?php

namespace App\Models;

use CodeIgniter\Model;

class VendorReceiveItemModel extends Model
{
    protected $table            = 'vendor_receive_items';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'receive_note_id',
        'product_id',
        'qty_received',
        'qty_accepted',
        'qty_rejected',
        'created_at',
    ];
}
