<?php
namespace App\Controllers;

use CodeIgniter\Controller;

class AccountingReceipts extends Controller
{
    public function show($cheque_id = null)
    {
        $cheque = null;
        $vendor = null;
        $purpose = '';
        if ($cheque_id) {
            $chequeModel = new \App\Models\Accounting\ChequeModel();
            $cheque = $chequeModel->where('id', $cheque_id)->first();
            if ($cheque && $cheque['vendor_id']) {
                $vendorModel = new \App\Models\VendorModel();
                $vendor = $vendorModel->find($cheque['vendor_id']);
                // fetch selected contact if exists
                if (!empty($cheque['contact_id'])) {
                    $vc = (new \App\Models\VendorContactModel())->find($cheque['contact_id']);
                    $selectedContact = $vc ? $vc['name'] : null;
                } else { $selectedContact = null; }
            }
            // Fetch first cheque line description for purpose
            $clm = new \App\Models\Accounting\ChequeLineModel();
            $line = $clm->where('cheque_id', $cheque_id)->orderBy('id','ASC')->first();
            if ($line && !empty($line['description'])) {
                $purpose = $line['description'];
            }
        }

        if (!$cheque) {
            return "<h2 style='color:red'>Cheque not found</h2>";
        }

        // Convert amount to words (simple, for demo)
        $amount_words = $this->numberToWords($cheque['amount'] ?? 0);

        // Fetch company info from DB
        $company = (new \App\Models\CompanySettingsModel())->first();

    // Generate a shorter stable receipt number for display: RCPT-{YY}-{ZERO_PAD_4}
    // Example: RCPT-25-0123
        $receipt_number_generated = 'RCPT-' . date('y') . '-' . str_pad((string)($cheque['id'] ?? $cheque_id), 4, '0', STR_PAD_LEFT);

            // Persist generated receipt_number to cheques table if missing
            try {
                if (empty($cheque['receipt_number'] ?? '') && ($cheque['id'] ?? $cheque_id)) {
                    $chequeModel->update($cheque['id'] ?? $cheque_id, ['receipt_number' => $receipt_number_generated]);
                    // update local variable so view gets the saved value
                    $cheque['receipt_number'] = $receipt_number_generated;
                }
            } catch (\Exception $e) {
                // log but don't break rendering
                log_message('error', 'Failed to persist receipt_number: ' . $e->getMessage());
            }

        return view('accounting/receipts/receipt', [
            'company_name' => $company['name'] ?? '',
            'company_tagline' => $company['tagline'] ?? '',
            'company_address' => $company['address'] ?? '',
            'company_phone' => $company['contact'] ?? '',
            'company_email' => $company['email'] ?? '',
            // mode controls cash/cheque behaviour in the view
            'mode' => 'cheque',
            'receipt_type' => ucfirst($cheque['payee_type'] ?? 'Cheque'),
            // Use generated receipt number (do not reuse cheque number here)
            'receipt_number' => $receipt_number_generated,
            'date' => $cheque['cheque_date'] ?? '',
            // Party mapping: vendor is payee in vendor payments
            'vendor_name' => $vendor['name'] ?? ($cheque['payee_name'] ?? ''),
            'vendor_contact_name' => $selectedContact ?? '',
            'payer' => $cheque['payee_name'] ?? '',
            'cheque_no' => $cheque['cheque_number'] ?? '',
            // richer description fields mapped from cheque notes
            'reference' => $cheque['notes'] ?? '',
            'description' => $purpose ?: ($cheque['notes'] ?? ''),
            'payment_description' => $purpose ?: ($cheque['notes'] ?? ''),
            'amount_words' => $amount_words,
            'amount' => $cheque['amount'] ?? '',
            'for' => $cheque['notes'] ?? '',
            'received_by' => '',
            'authorized_by' => '',
            'remarks' => $cheque['notes'] ?? '',
            // pass DB timestamp for view fallback
            'created_at' => $cheque['created_at'] ?? null,
            'id' => $cheque['id'] ?? $cheque_id,
        ]);
    }

    // Simple number to words (for demo, only works for integers up to 9999)
    private function numberToWords($num) {
        $num = (int)$num;
        if ($num == 0) return 'Zero';
        $ones = ["", "One", "Two", "Three", "Four", "Five", "Six", "Seven", "Eight", "Nine", "Ten", "Eleven", "Twelve", "Thirteen", "Fourteen", "Fifteen", "Sixteen", "Seventeen", "Eighteen", "Nineteen"];
        $tens = ["", "", "Twenty", "Thirty", "Forty", "Fifty", "Sixty", "Seventy", "Eighty", "Ninety"];
        $out = "";
        if ($num >= 1000) {
            $out .= $ones[(int)(($num/1000)%10)] . " Thousand ";
            $num %= 1000;
        }
        if ($num >= 100) {
            $out .= $ones[(int)(($num/100)%10)] . " Hundred ";
            $num %= 100;
        }
        if ($num >= 20) {
            $out .= $tens[(int)($num/10)] . " ";
            $num %= 10;
        }
        if ($num > 0) {
            $out .= $ones[$num] . " ";
        }
        return trim($out) . ' Only';
    }
}
