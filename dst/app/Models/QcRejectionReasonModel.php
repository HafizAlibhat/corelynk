<?php

namespace App\Models;

use CodeIgniter\Model;

class QcRejectionReasonModel extends Model
{
    protected $table            = 'qc_rejection_reasons';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'name',
        'is_active',
    ];
}
