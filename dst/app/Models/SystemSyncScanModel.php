<?php

namespace App\Models;

use CodeIgniter\Model;

class SystemSyncScanModel extends Model
{
    protected $table = 'system_sync_scans';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $allowedFields = [
        'public_id',
        'source_env_id',
        'destination_env_id',
        'status',
        'summary_json',
        'safe_operations_json',
        'report_path',
        'created_by',
        'applied_by',
        'applied_at',
        'error_message',
    ];
}
