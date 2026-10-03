<?php

namespace App\Models;

use CodeIgniter\Model;

class PasswordResetTokenModel extends Model
{
    protected $table = 'password_reset_tokens';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $DBGroup = 'controlRuntime';
    protected $allowedFields = ['user_id', 'account_id', 'token_hash', 'request_ip', 'expires_at', 'used_at', 'created_at'];
}
