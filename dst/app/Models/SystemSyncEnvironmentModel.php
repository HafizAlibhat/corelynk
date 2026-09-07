<?php

namespace App\Models;

use CodeIgniter\Model;

class SystemSyncEnvironmentModel extends Model
{
    protected $table = 'system_sync_environments';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $allowedFields = [
        'name',
        'app_path',
        'db_host',
        'db_port',
        'db_name',
        'db_user',
        'db_password',
        'is_active',
    ];
}
