<?php

namespace App\Models;

use CodeIgniter\Model;

class DataPortabilityJobModel extends Model
{
    protected $table = 'data_portability_jobs';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $allowedFields = [
        'public_id',
        'direction',
        'status',
        'modules_json',
        'archive_path',
        'archive_name',
        'archive_size_bytes',
        'archive_sha256',
        'result_summary_json',
        'error_message',
        'initiated_by',
        'progress_percent',
        'progress_stage',
        'cancel_requested',
        'started_at',
        'completed_at',
    ];
}
