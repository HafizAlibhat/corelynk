<?php

namespace App\Models;

use CodeIgniter\Model;

class CustomsInvoiceVersionModel extends Model
{
    protected $table = 'customs_invoice_versions';
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
        'customs_invoice_id',
        'version_no',
        'parent_version_id',
        'change_type',
        'change_reason',
        'is_approved_snapshot',
        'is_final_snapshot',
        'sealed_at',
        'snapshot_json',
        'snapshot_hash',
        'pdf_file_id',
        'created_by',
        'updated_by',
    ];
}
