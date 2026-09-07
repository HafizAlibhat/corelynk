<?php

namespace App\Models;

use CodeIgniter\Model;
use App\Traits\PublicIdTrait;

class VendorBillModel extends Model
{
    use PublicIdTrait;

    protected $table = 'vendor_bills';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'public_id',
        'vendor_id',
        'po_id',
        'vendor_bill_number',
        'memo',
        'notes',
        'status',
        'bill_date',
        'issue_date',
        'total_amount',
        'balance',
        'based_on',
        'currency_code',
        'account_id',
        'expense_account_id',
        'purchase_account_id',
        'posted_entry_id',
        'created_by',
        'created_at',
        'updated_at',
    ];
    protected $useTimestamps = false;

    public function __construct(?\CodeIgniter\Database\ConnectionInterface $db = null, ?\CodeIgniter\Validation\ValidationInterface $validation = null)
    {
        parent::__construct($db, $validation);
        $this->bootPublicId();
    }

    /**
     * Relationship to PurchaseOrderModel
     */
    public function purchaseOrder()
    {
        return $this->belongsTo('App\Models\PurchaseOrderModel', 'po_id', 'id');
    }

    /**
     * Relationship to VendorBillLineModel
     */
    public function lines()
    {
        return $this->hasMany('App\Models\VendorBillLineModel', 'vendor_bill_id', 'id');
    }
}
