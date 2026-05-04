<?php
namespace App\Models;

use CodeIgniter\Model;

class GRNLineModel extends Model
{
    protected $table = 'grn_lines';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['grn_id','po_line_id','product_id','description','qty_received','unit_cost'];
    public $timestamps = false;
}
