<?php
namespace App\Models;

use CodeIgniter\Model;

class CustomerInvoiceLineModel extends Model
{
    protected $table = 'customer_invoice_lines';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'invoice_id',
        'product_id',
        'product_variant_id',
        'product_code',
        'product_name',
        'description',
        'unit',
        'quantity',
        'unit_price',
        'discount_type',
        'discount_value',
        'discount_amount',
        'tax_type',
        'tax_value',
        'tax_rate',
        'tax_amount',
        'tax_code_id',
        'line_total',
        'product_image_url',
        'display_type',
        'section_title',
        'sort_order'
    ];
    protected $useTimestamps = true;

    public function invoice()
    {
        return $this->belongsTo('App\Models\CustomerInvoiceModel', 'invoice_id', 'id');
    }

    public function product()
    {
        return $this->belongsTo('App\Models\ProductModel', 'product_id', 'id');
    }
}
