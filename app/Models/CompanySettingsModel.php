<?php

namespace App\Models;

use CodeIgniter\Model;

class CompanySettingsModel extends Model
{
    protected $table = 'company_settings';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'name','address','contact','email','phone','website','tagline',
        'base_currency','secondary_currency','use_demo_data',
        'default_sales_currency','default_purchase_currency',
        'logo_path','company_logo',
        'quotation_prefix','sales_order_prefix','art_number_prefix','customer_code_prefix','vendor_code_prefix',
        'invoice_footer','quotation_footer','purchase_order_footer','pdf_template',
        'pdf_inv_show_header','pdf_inv_show_footer',
        'pdf_quote_show_header','pdf_quote_show_footer',
        'pdf_so_show_header','pdf_so_show_footer',
        'pdf_po_show_header','pdf_po_show_footer',
        'pdf_rfq_show_header','pdf_rfq_show_footer'
    ];
    protected $useTimestamps = false;
}
