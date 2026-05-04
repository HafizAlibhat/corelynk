<?php

namespace App\Models;

use CodeIgniter\Model;

class OdooSettingsModel extends Model
{
    protected $table = 'odoo_settings';
    protected $primaryKey = 'id';
    protected $allowedFields = ['host', 'port', 'db_name', 'username', 'password'];
    public $useTimestamps = true;
    protected $createdField = '';
    protected $updatedField = 'updated_at';
}
