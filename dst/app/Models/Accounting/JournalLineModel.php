<?php

namespace App\Models\Accounting;

use CodeIgniter\Model;

class JournalLineModel extends Model
{
    // Unified DB: removed $DBGroup to use default connection
    protected $table = 'journal_lines';
    protected $primaryKey = 'id';
    protected $allowedFields = ['entry_id', 'account_id', 'description', 'debit', 'credit', 'currency_code', 'fx_rate', 'base_amount'];
    protected $useTimestamps = false;
}
