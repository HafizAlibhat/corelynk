<?php

namespace App\Models\Accounting;

use CodeIgniter\Model;

class TaxCodeModel extends Model
{
    // Unified DB: removed $DBGroup
    protected $table = 'tax_codes';
    protected $primaryKey = 'id';
    protected $allowedFields = ['code', 'name', 'rate', 'is_compound'];
}
