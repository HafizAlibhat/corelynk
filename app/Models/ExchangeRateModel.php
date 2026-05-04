<?php

namespace App\Models;

use CodeIgniter\Model;

class ExchangeRateModel extends Model
{
    protected $table = 'exchange_rate';
    protected $primaryKey = 'id';
    protected $allowedFields = ['base_code','quote_code','rate','as_of'];
    protected $useTimestamps = false;
}
