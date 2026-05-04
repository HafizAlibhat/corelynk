<?php
namespace App\Models\Accounting;
use CodeIgniter\Model;
class CreditNoteModel extends Model
{
    // Unified DB: removed $DBGroup
    protected $table='credit_notes';
    protected $primaryKey='id';
    protected $allowedFields=['party_type','party_id','account_id','reference','note','amount','applied_amount','status'];
    protected $useTimestamps=true;
}
