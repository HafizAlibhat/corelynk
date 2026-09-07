<?php
namespace App\Models;

use CodeIgniter\Model;

class InvoiceDocumentModel extends Model
{
    protected $table = 'invoice_documents';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'invoice_id', 'document_type', 'file_path', 
        'file_name', 'generated_by'
    ];
    protected $useTimestamps = false;

    public function invoice()
    {
        return $this->belongsTo('App\Models\CustomerInvoiceModel', 'invoice_id', 'id');
    }
}
