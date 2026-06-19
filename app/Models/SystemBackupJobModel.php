<?php

namespace App\Models;

use CodeIgniter\Model;

class SystemBackupJobModel extends Model
{
    protected $table = 'system_backup_jobs';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $allowedFields = [
        'public_id',
        'job_type',
        'backup_type',
        'status',
        'environment_name',
        'app_root',
        'db_name',
        'archive_path',
        'archive_name',
        'archive_size_bytes',
        'archive_sha256',
        'manifest_path',
        'health_status',
        'health_details_json',
        'schedule_id',
        'initiated_by',
        'error_message',
        'started_at',
        'completed_at',
    ];
}