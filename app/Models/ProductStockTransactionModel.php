<?php
namespace App\Models;

use CodeIgniter\Model;

class ProductStockTransactionModel extends Model
{
    protected $table = 'product_stock_transactions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['product_id','transaction_type','quantity','unit_cost','reference_type','reference_id','notes','created_by','created_at'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';

    public function getTransactionsByProduct(int $productId, int $limit = 50): array
    {
        return $this->select('product_stock_transactions.*, users.first_name as created_by_name')
                    ->join('users', 'users.id = product_stock_transactions.created_by', 'left')
                    ->where('product_stock_transactions.product_id', $productId)
                    ->orderBy('product_stock_transactions.created_at', 'DESC')
                    ->limit($limit)
                    ->findAll();
    }

    /**
     * Insert a transaction and optionally return inserted ID
     */
    public function insertTransaction(array $data, bool $returnId = false)
    {
        $this->insert($data);
        if ($returnId) {
            return $this->getInsertID();
        }
        return true;
    }
}
