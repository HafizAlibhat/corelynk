<?php

namespace App\Models\Accounting;

use CodeIgniter\Model;

class CurrencyModel extends Model
{
    // Unified DB: removed $DBGroup
    protected $table = 'currencies';
    protected $primaryKey = 'code';
    protected $allowedFields = ['code', 'name', 'symbol', 'is_base', 'decimals', 'is_active'];
    protected $useAutoIncrement = false;
}
