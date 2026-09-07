<?php

namespace App\Models;

use CodeIgniter\Model;

class PaymentMethodModel extends Model
{
    protected $table = 'payment_methods';
    protected $primaryKey = 'id';
    protected $allowedFields = ['method_name','is_active'];
    protected $useTimestamps = false;
}
