<?php

namespace App\Models;

use CodeIgniter\Model;

class CustomsInvoiceModel extends Model
{
    protected $table = 'customs_invoices';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    protected $allowedFields = [
        'uuid',
        'original_invoice_id',
        'customs_invoice_no',
        'mode',
        'status',
        'current_version_no',
        'current_version_id',
        'currency_code',
        'declared_total',
        'shipment_id',
        'tracking_no',
        'source_snapshot_hash',
        'lock_state',
        'row_version',
        'created_by',
        'updated_by',
    ];
}
