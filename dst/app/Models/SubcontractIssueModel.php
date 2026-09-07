<?php
namespace App\Models;

use CodeIgniter\Model;

class SubcontractIssueModel extends Model
{
    protected $table = 'subcontract_issues';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['issue_number','vendor_id','issued_at','notes','created_by','created_at'];
    protected $useTimestamps = true;
}
