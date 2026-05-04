<?php

namespace App\Models;

use CodeIgniter\Model;

class FiscalYearModel extends Model
{
    protected $table = 'fiscal_year';
    protected $primaryKey = 'id';
    protected $allowedFields = ['start_date','end_date','is_active'];
    protected $useTimestamps = false;
}
