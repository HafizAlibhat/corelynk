<?php

namespace App\Models;

use CodeIgniter\Model;

class SecuritySettingsModel extends Model
{
    protected $table = 'security_settings';
    protected $primaryKey = 'id';
    protected $allowedFields = ['backdate_password_hash'];
    protected $useTimestamps = false;
}
