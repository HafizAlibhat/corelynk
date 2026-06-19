<?php

namespace App\Models;

use CodeIgniter\Model;

class CustomsInvoiceItemModel extends Model
{
    protected $table = 'customs_invoice_items';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    protected $allowedFields = [
        'uuid',
        'customs_invoice_id',
        'customs_invoice_version_id',
        'line_no',
        'line_type',
        'source_invoice_line_id',
        'source_product_id',
        'custom_description',
        'hs_code',
        'declared_qty',
        'uom',
        'declared_unit_price',
        'declared_line_total',
        'declared_weight',
        'weight_uom',
        'currency_code',
        'group_key',
        'metadata_json',
        'created_by',
        'updated_by',
    ];
}
