<?php

namespace App\Models\Accounting;

use CodeIgniter\Model;

class ChequeLineModel extends Model
{
    protected $table = 'cheque_lines';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields = [
        'cheque_id','account_id','description','amount'
    ];
    protected $useTimestamps = false;
}
