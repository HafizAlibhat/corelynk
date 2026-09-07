<?php

namespace App\Models\Accounting;

use CodeIgniter\Model;

class JournalEntryModel extends Model
{
    // Unified DB: removed $DBGroup to use default connection
    protected $table = 'journal_entries';
    protected $primaryKey = 'id';
    protected $allowedFields = ['entry_date', 'memo', 'currency_code', 'total_debits', 'total_credits', 'source_type', 'source_id'];
    protected $useTimestamps = false;
}
