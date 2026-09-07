<?php

namespace App\Models\Accounting;

use CodeIgniter\Model;

class ChequeModel extends Model
{
    protected $table = 'cheques';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields = [
        'bank_account_id','employee_id','cheque_number','cheque_date','payee_type','vendor_id','contact_id','payee_name',
        'delivery_type','payment_type','status','amount','posted_entry_id','notes','receipt_number'
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function getWithJoins(int $limit = 50): array
    {
        $db = \Config\Database::connect();
        $sql = "SELECT c.*, a.name AS bank_name, v.name AS vendor_name,
                       CONCAT(IFNULL(e.first_name,''), ' ', IFNULL(e.last_name,'')) AS employee_name
                FROM cheques c
                LEFT JOIN accounts a ON a.id = c.bank_account_id
                LEFT JOIN vendors v ON v.id = c.vendor_id
                LEFT JOIN employees e ON e.id = c.employee_id
                ORDER BY c.cheque_date DESC, c.id DESC
                LIMIT ?";
        return $db->query($sql, [$limit])->getResultArray();
    }
}
