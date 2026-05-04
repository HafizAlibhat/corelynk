<?php
namespace App\Models;

use CodeIgniter\Model;
use App\Traits\PublicIdTrait;

class CustomerInvoiceModel extends Model
{
    use PublicIdTrait;
    protected $table = 'customer_invoices';
    protected $primaryKey = 'id';
    protected $useSoftDeletes = true;
    protected $allowedFields = [
        'public_id', 'invoice_number', 'customer_id', 'parent_invoice_id', 'invoice_type',
        'issue_date', 'due_date', 'payment_term_id', 'currency_code',
        'subtotal', 'tax_total', 'total_amount', 'shipping_cost', 'customs_value',
        'status', 'is_custom_adjusted', 'custom_notes', 'export_reference',
        'notes', 'posted_entry_id', 'created_by'
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';

    // Validation rules
    protected $validationRules = [
        'invoice_number' => 'required|is_unique[customer_invoices.invoice_number,id,{id}]',
        'customer_id' => 'required|numeric',
        'total_amount' => 'required|numeric'
    ];

    // Relationships
    public function customer()
    {
        return $this->belongsTo('App\Models\CustomerModel', 'customer_id', 'id');
    }

    public function parentInvoice()
    {
        return $this->belongsTo('App\Models\CustomerInvoiceModel', 'parent_invoice_id', 'id');
    }

    public function customInvoices()
    {
        return $this->hasMany('App\Models\CustomerInvoiceModel', 'parent_invoice_id', 'id')
                   ->where('invoice_type', 'custom');
    }

    public function lines()
    {
        return $this->hasMany('App\Models\CustomerInvoiceLineModel', 'invoice_id', 'id');
    }

    public function payments()
    {
        return $this->hasMany('App\Models\CustomerPaymentAllocationModel', 'invoice_id', 'id');
    }

    public function documents()
    {
        return $this->hasMany('App\Models\InvoiceDocumentModel', 'invoice_id', 'id');
    }

    // Business methods
    public function generateInvoiceNumber()
    {
        $prefix = 'INV-';
        $year = date('Y');
        $month = date('m');

        // Get last invoice number
        $last = $this->orderBy('id', 'DESC')->first();
        if ($last && strpos($last['invoice_number'], $prefix . $year . $month) === 0) {
            $lastNum = intval(substr($last['invoice_number'], -4));
            $nextNum = str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $nextNum = '0001';
        }

        return $prefix . $year . $month . '-' . $nextNum;
    }

    public function getOutstandingBalance()
    {
        $totalPaid = 0;
        if (!empty($this->payments)) {
            foreach ($this->payments as $payment) {
                $totalPaid += $payment['allocated_amount'];
            }
        }
        return $this->total_amount - $totalPaid;
    }
}
