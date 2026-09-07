<?php
namespace App\Services;

use App\Models\CustomerInvoiceModel;
use App\Models\InvoiceDocumentModel;
use App\Libraries\InvoicePdfGenerator;
use App\Models\CompanySettingsModel;

class DualInvoiceService
{
    protected $invoiceModel;
    protected $docModel;
    protected $pdfGen;

    public function __construct()
    {
        $this->invoiceModel = new CustomerInvoiceModel();
        $this->docModel = new InvoiceDocumentModel();
        $this->pdfGen = new InvoicePdfGenerator();
    }

    /**
     * Create a system invoice: insert header, lines, reserve stock, post minimal journal entry, generate PDF
     * @param array $data - invoice header + lines
     * @return array created invoice
     */
    public function createSystemInvoice(array $data): array
    {
        // Expect $data['header'] and $data['lines']
        $header = $data['header'] ?? [];
        $lines = $data['lines'] ?? [];

        // Ensure required fields
        if (empty($header['invoice_number'])) {
            $header['invoice_number'] = $this->invoiceModel->generateInvoiceNumber();
        }
        if (empty($header['currency_code'])) {
            $header['currency_code'] = $this->getDefaultSalesCurrency();
        }
        if (empty($header['invoice_type'])) {
            $header['invoice_type'] = 'system';
        }
        if (empty($header['status'])) {
            $header['status'] = 'draft';
        }

        // Calculate totals if missing (try to be helpful)
        if (empty($header['total_amount'])) {
            $calcTotal = 0.0;
            if (!empty($header['subtotal'])) {
                $calcTotal = (float)$header['subtotal'];
            } elseif (!empty($lines)) {
                foreach ($lines as $ln) {
                    $calcTotal += (float)($ln['line_total'] ?? ((float)($ln['quantity'] ?? 0) * (float)($ln['unit_price'] ?? 0)));
                }
            }
            $calcTax = isset($header['tax_total']) ? (float)$header['tax_total'] : 0.0;
            $calcShipping = isset($header['shipping_cost']) ? (float)$header['shipping_cost'] : 0.0;
            $calcDiscount = isset($header['discount_total']) ? (float)$header['discount_total'] : 0.0;
            $header['subtotal'] = $header['subtotal'] ?? $calcTotal;
            $header['tax_total'] = $header['tax_total'] ?? $calcTax;
            $header['total_amount'] = $header['total_amount'] ?? round($calcTotal - $calcDiscount + $calcTax + $calcShipping, 2);
        }

        // create header
        try {
            $id = $this->invoiceModel->insert($header);
        } catch (\Throwable $e) {
            // capture DB exception and surface message for easier debugging
            $db = \Config\Database::connect();
            $dberr = method_exists($db, 'error') ? $db->error() : null;
            $msg = 'Failed to create invoice header: ' . $e->getMessage();
            if (!empty($dberr)) $msg .= ' | DB error: ' . json_encode($dberr);
            throw new \Exception($msg);
        }

        if (!$id) {
            // gather model validation errors when insert() returns false
            $errs = [];
            try { $errs = $this->invoiceModel->errors() ?: []; } catch (\Throwable $_) { $errs = []; }
            $db = \Config\Database::connect();
            $dberr = method_exists($db, 'error') ? $db->error() : null;
            $msg = 'Failed to create invoice header';
            if (!empty($errs)) $msg .= ': ' . implode('; ', $errs);
            if (!empty($dberr)) $msg .= ' | DB error: ' . json_encode($dberr);
            throw new \Exception($msg);
        }

        // insert lines (do not touch stock/accounting in this simple flow)
        $db = \Config\Database::connect();
        foreach ($lines as $line) {
            $line['invoice_id'] = $id;
            $db->table('customer_invoice_lines')->insert($line);
        }

        // Generate PDF (system)
        $invoice = $this->invoiceModel->find($id);
        $pdf = $this->pdfGen->generateSystemInvoice($this->assembleInvoiceData($invoice));
        if ($pdf) {
            $this->docModel->insert([
                'invoice_id' => $id,
                'document_type' => 'system_invoice',
                'file_path' => $pdf['path'],
                'file_name' => $pdf['name'],
                'generated_at' => date('Y-m-d H:i:s'),
                'generated_by' => $header['created_by'] ?? 0
            ]);
        }

        return $this->invoiceModel->find($id);
    }

    public function createCustomInvoice(int $systemInvoiceId, array $customizations): array
    {
        $system = $this->invoiceModel->find($systemInvoiceId);
        if (!$system) throw new \Exception('System invoice not found');

        // Build custom header: clone system but set invoice_type=custom and parent_invoice_id
        $customHeader = $system;
        unset($customHeader['id']);
        $customHeader['invoice_type'] = 'custom';
        $customHeader['parent_invoice_id'] = $systemInvoiceId;
        $customHeader['is_custom_adjusted'] = 1;
        // Apply provided overrides
        foreach (['issue_date','due_date','shipping_cost','customs_value','export_reference','notes','custom_notes'] as $k) {
            if (isset($customizations[$k])) $customHeader[$k] = $customizations[$k];
        }

        $id = $this->invoiceModel->insert($customHeader);
        if (!$id) throw new \Exception('Failed to create custom invoice');

        // Create lines: allow customizations to provide custom lines, otherwise copy subset
        $lines = $customizations['lines'] ?? [];
        $db = \Config\Database::connect();
        if (!empty($lines)) {
            foreach ($lines as $line) {
                $line['invoice_id'] = $id;
                $db->table('customer_invoice_lines')->insert($line);
            }
        } else {
            // copy lines from system invoice
            $sysLines = $db->table('customer_invoice_lines')
                ->where('invoice_id', $systemInvoiceId)
                ->orderBy('sort_order', 'ASC')
                ->orderBy('id', 'ASC')
                ->get()->getResultArray();
            foreach ($sysLines as $l) {
                unset($l['id']);
                $l['invoice_id'] = $id;
                $db->table('customer_invoice_lines')->insert($l);
            }
        }

        // DO NOT affect stock or accounting for custom invoice
        // Generate PDF
        $invoice = $this->invoiceModel->find($id);
        $pdf = $this->pdfGen->generateCustomInvoice($this->assembleInvoiceData($invoice));
        if ($pdf) {
            $this->docModel->insert([
                'invoice_id' => $id,
                'document_type' => 'custom_invoice',
                'file_path' => $pdf['path'],
                'file_name' => $pdf['name'],
                'generated_at' => date('Y-m-d H:i:s'),
                'generated_by' => $customHeader['created_by'] ?? 0
            ]);
        }

        return $this->invoiceModel->find($id);
    }

    protected function assembleInvoiceData($invoice)
    {
        $db = \Config\Database::connect();
        $lines = $db->table('customer_invoice_lines')
            ->where('invoice_id', $invoice['id'])
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();
        return ['invoice' => $invoice, 'lines' => $lines];
    }

    public function generateInvoicePDF($invoiceId, $type = 'system')
    {
        $invoice = $this->invoiceModel->find($invoiceId);
        if (!$invoice) throw new \Exception('Invoice not found');

        if ($type === 'custom') {
            return $this->pdfGen->generateCustomInvoice($this->assembleInvoiceData($invoice));
        }
        return $this->pdfGen->generateSystemInvoice($this->assembleInvoiceData($invoice));
    }

    private function getDefaultSalesCurrency(): string
    {
        try {
            $company = (new CompanySettingsModel())->first();
            if (!empty($company['default_sales_currency'])) return $company['default_sales_currency'];
            if (!empty($company['base_currency'])) return $company['base_currency'];
            if (!empty($company['secondary_currency'])) return $company['secondary_currency'];
        } catch (\Throwable $_) {
            // ignore and fall through
        }
        return 'USD';
    }
}
