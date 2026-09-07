<?php

namespace App\Models;

use CodeIgniter\Model;

class SystemBackupScheduleModel extends Model
{
    protected $table = 'system_backup_schedules';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $allowedFields = [
        'name',
        'is_active',
        'backup_type',
        'frequency_type',
        'interval_minutes',
        'day_of_week',
        'time_of_day',
        'retention_count',
        'last_run_at',
        'next_run_at',
        'created_by',
        'updated_by',
    ];
}