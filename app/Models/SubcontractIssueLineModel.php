<?php
namespace App\Models;

use CodeIgniter\Model;

class SubcontractIssueLineModel extends Model
{
    protected $table = 'subcontract_issue_lines';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['issue_id','product_id','description','quantity'];
    public $timestamps = false;
}
