<?php

namespace App\Models;

use CodeIgniter\Model;

class CustomsInvoiceFileModel extends Model
{
    protected $table = 'customs_invoice_files';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = false;

    protected $allowedFields = [
        'uuid',
        'customs_invoice_id',
        'customs_invoice_version_id',
        'file_type',
        'storage_disk',
        'storage_path',
        'file_name',
        'mime_type',
        'file_size',
        'sha256_hash',
        'template_version',
        'render_engine_version',
        'is_current',
        'created_by',
        'created_at',
    ];
}
