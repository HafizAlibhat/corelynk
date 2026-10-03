<?php

/**
 * Runnable check for the Bank Details / Terms formatting helper.
 *   php tests/app/invoice_terms_format_check.php
 */

if (! function_exists('esc')) {
    function esc($str, $context = 'html')
    {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}

require __DIR__ . '/../../app/Helpers/invoice_terms_helper.php';

$pass = 0;
$fail = 0;

function check(string $label, bool $ok): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo '  ' . ($ok ? 'ok   ' : 'FAIL ') . $label . "\n";
}

$bank = "Bank Name : BankIslami Pakistan Ltd\nAccount Title : Regal Impex\nIBAN No : PK61BKIP0202115846180001\n\nPlease quote the invoice number";
$html = invoice_bank_details_html($bank);

check('bank details render as a table', str_contains($html, '<table'));
check('label and value are split into two cells', str_contains($html, '>Bank Name</td>') && str_contains($html, '>BankIslami Pakistan Ltd</td>'));
check('IBAN keeps its full value', str_contains($html, 'PK61BKIP0202115846180001'));
check('a colon-less line spans both columns', str_contains($html, 'colspan="2"') && str_contains($html, 'Please quote the invoice number'));
check('blank lines are dropped', substr_count($html, '<tr>') === 4);
check('empty input renders nothing', invoice_bank_details_html('   ') === '');

$terms = "Terms and Conditions:\nPayments Term : 80% advance and 20% 30 days upon delivery.\n- Lead Time : 20 to 30 Working Days\n2. Import fees are handled by the customer\nAll prices are quoted in USD";
$html = invoice_terms_html($terms);

check('terms render as bullets', substr_count($html, '&bull;') === 4);
check('the redundant heading line is dropped', ! str_contains($html, 'Terms and Conditions:'));
check('a labelled clause keeps its label in bold', str_contains($html, '<strong style="color:#0f172a;">Payments Term:</strong>'));
check('a hand-typed dash bullet is not doubled', ! str_contains($html, '- Lead Time'));
check('a hand-typed number is stripped', ! str_contains($html, '2. Import'));
check('an unlabelled clause still renders', str_contains($html, 'All prices are quoted in USD'));
check('empty input renders nothing', invoice_terms_html("\n \n") === '');

// A single heading-only line must not vanish entirely.
check('a lone heading line is kept', str_contains(invoice_terms_html('Payment due on receipt:'), 'Payment due on receipt'));

// A long sentence containing a colon must not be mistaken for a label.
$long = invoice_terms_html('Any import fees charged by customs in your respective country must be handled by the customer: see UAE rules.');
check('a long sentence is not treated as a label', ! str_contains($long, '<strong'));

check('html in user text is escaped', str_contains(invoice_terms_html('<script>alert(1)</script>'), '&lt;script&gt;'));
$wrapped = invoice_terms_html("Import Fee : fees are handled by the customer, as per customs
regulations of the UAE.
All prices in USD");
check('a hard-wrapped sentence stays one bullet', substr_count($wrapped, '&bull;') === 2);
check('the wrapped tail joins the clause above', str_contains($wrapped, 'as per customs regulations of the UAE.'));

check('a short note is left alone', 'Net 30' === invoice_short_note('Net 30.'));
check('a long note is cut on a word boundary', str_ends_with(invoice_short_note('80% payable in advance before production, 20% payable 30 days after goods are delivered.'), '...'));
check('the cut note fits the heading', mb_strlen(invoice_short_note('80% payable in advance before production, 20% payable 30 days after goods are delivered.')) <= 58);
check('the note keeps whole words', !str_contains(invoice_short_note('80% payable in advance before production, 20% payable 30 days after delivery.'), 'payab...'));
check('only the first sentence is kept', 'First clause' === invoice_short_note('First clause. Second sentence that is dropped.'));
check('an empty description gives an empty note', '' === invoice_short_note('   '));
check('plain text with angle brackets is escaped', str_contains(invoice_bank_details_html('Bank : a < b'), '&lt;'));
check('rich text is passed through', str_contains(invoice_bank_details_html('<p>Bank: <b>x</b></p>'), '<b>x</b>'));
check('scripts are stripped from rich text', !str_contains(invoice_terms_html('<p>ok</p><script>alert(1)</script>'), '<script'));
check('event handlers are stripped', !str_contains(invoice_terms_html('<p onclick="alert(1)">ok</p>'), 'onclick'));
check('javascript links are stripped', !str_contains(invoice_terms_html('<p><a href="javascript:alert(1)">x</a></p>'), 'javascript:'));
check('plain text still becomes bullets', str_contains(invoice_terms_html("Lead Time: 20 days\nImport Fee: customer"), '&bull;'));

echo "\n" . ($fail === 0 ? "All {$pass} checks passed.\n" : "{$fail} of " . ($pass + $fail) . " checks FAILED.\n");
exit($fail === 0 ? 0 : 1);
