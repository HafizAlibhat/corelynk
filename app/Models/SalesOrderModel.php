<?php
namespace App\Models;

use CodeIgniter\Model;
use App\Traits\PublicIdTrait;

class SalesOrderModel extends Model
{
    use PublicIdTrait;

    protected $table = 'sales_orders';
    protected $primaryKey = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'order_number','quotation_id','customer_id','order_date','subtotal','tax_total','total','status','created_by','public_id'
    ];

    public function __construct(?\CodeIgniter\Database\ConnectionInterface $db = null, ?\CodeIgniter\Validation\ValidationInterface $validation = null)
    {
        parent::__construct($db, $validation);
        $this->bootPublicId();

        // Add optional columns only if they exist in the table
        try {
            $cols = $this->db->getFieldNames($this->table);
            if (in_array('shipping_amount', $cols)) {
                $this->allowedFields[] = 'shipping_amount';
            } elseif (in_array('shipping_cost', $cols)) {
                $this->allowedFields[] = 'shipping_cost';
            }

            // Sync with QuotationModel: ensure currency persistence columns exist
            if (!in_array('currency', $cols)) {
                try {
                    $this->db->query("ALTER TABLE `sales_orders` ADD COLUMN `currency` VARCHAR(10) DEFAULT 'PKR'");
                    $this->allowedFields[] = 'currency';
                } catch (\Throwable $_) {}
            } else {
                $this->allowedFields[] = 'currency';
            }

            if (!in_array('currency_code', $cols)) {
                try {
                    $this->db->query("ALTER TABLE `sales_orders` ADD COLUMN `currency_code` VARCHAR(10) DEFAULT 'PKR'");
                    $this->allowedFields[] = 'currency_code';
                } catch (\Throwable $_) {}
            } else {
                $this->allowedFields[] = 'currency_code';
            }
        } catch (\Throwable $_) {
            // best-effort
        }
    }

    public function lines()
    {
        return $this->hasMany('App\Models\SalesOrderLineModel', 'sales_order_id', 'id');
    }
}
