<?php
namespace App\Models;

use CodeIgniter\Model;

class CustomerInvoiceScheduleModel extends Model
{
    protected $table = 'customer_invoice_schedules';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'invoice_id', 'seq', 'label', 'percentage', 'amount',
        'basis', 'offset_days', 'due_date',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';

    public function forInvoice(int $invoiceId): array
    {
        return $this->where('invoice_id', $invoiceId)->orderBy('seq', 'ASC')->findAll();
    }
}
