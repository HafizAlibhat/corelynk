<?php
$invoice = $invoice ?? ($payload['invoice'] ?? []);
$lines = $lines ?? ($payload['lines'] ?? []);
$company = $company ?? ($payload['company'] ?? []);
$customer = $customer ?? ($payload['customer'] ?? []);
$customerAddress = $customerAddress ?? ($payload['customerAddress'] ?? []);
$hideCompanyLogo = !empty($hide_company_logo);
$hideCompanyWebsite = !empty($hide_company_website);
$pdfShowHeader = isset($pdf_show_header_address) ? (bool)$pdf_show_header_address : (!isset($company['pdf_show_header_address']) || $company['pdf_show_header_address']);
$pdfShowFooter = isset($pdf_show_footer) ? (bool)$pdf_show_footer : (!isset($company['pdf_show_footer']) || $company['pdf_show_footer']);
$warehousePdf = isset($warehouse_pdf) ? ((int)$warehouse_pdf === 1) : false;
$normalizePickText = static function (string $text): string {
    // Drop leading symbol/punctuation glyphs that can render as '?' in PDF fonts.
    $clean = preg_replace('/^[\p{P}\p{S}\s]+/u', '', trim($text));
    $clean = is_string($clean) ? trim($clean) : trim($text);
    return $clean !== '' ? $clean : 'Not in stock';
};

$gdLoaded = extension_loaded('gd');
$canRenderImages = true;

// Resolve logo: generator converts to base64 data URI; fall back to file:// path only if needed
$logoSrc = '';
$logoRaw = trim((string)($company['logo_path'] ?? ($company['company_logo'] ?? ($company['logo'] ?? ''))));
if (!$hideCompanyLogo && strncmp($logoRaw, 'data:', 5) === 0) {
    $logoSrc = $logoRaw;
} elseif (!$hideCompanyLogo && $logoRaw !== '') {
    // Resolve the exact file — never swap extensions to avoid picking up stale files
    $logoNorm = ltrim(str_replace(['\\','/'], DIRECTORY_SEPARATOR, $logoRaw), DIRECTORY_SEPARATOR);
    $logoDir = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'company' . DIRECTORY_SEPARATOR;
    $candidates = [
        rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . $logoNorm,
        $logoDir . $logoNorm,
        $logoDir . basename($logoNorm),
    ];
    foreach ($candidates as $cand) {
        if (is_file($cand)) {
            $real = str_replace('\\', '/', realpath($cand) ?: $cand);
            $logoSrc = preg_match('#^[A-Za-z]:/#', $real) ? ('file:///' . $real) : ('file://' . $real);
            break;
        }
    }
}

$documentTitle = trim((string)($document_title ?? 'Invoice'));
if ($documentTitle === '') $documentTitle = 'Invoice';
$documentNumberLabel = trim((string)($document_number_label ?? ($documentTitle . ' #')));
if ($documentNumberLabel === '') $documentNumberLabel = $documentTitle . ' #';
$documentDateLabel = trim((string)($document_date_label ?? 'Date:'));
if ($documentDateLabel === '') $documentDateLabel = 'Date:';
$partyLabel = trim((string)($party_label ?? 'Bill To'));
if ($partyLabel === '') $partyLabel = 'Bill To';
$documentPrefix = (string)($document_prefix ?? 'INV-');

$invoiceNoRaw = $invoice['invoice_number'] ?? ($invoice['id'] ?? '');
$invoiceNo = $invoiceNoRaw;
if ($invoiceNo !== '') {
    if ($documentPrefix !== '') {
        $invoiceNo = (stripos($invoiceNo, $documentPrefix) === 0) ? $invoiceNo : $documentPrefix . ltrim($invoiceNo, '# ');
    }
}
// Format issue_date properly: parse raw date and format as d-m-Y
$issueDateRaw = trim((string)($invoice['issue_date'] ?? ''));
$issueDate = '';
if (!empty($issueDateRaw) && $issueDateRaw !== '0000-00-00' && $issueDateRaw !== '0000-00-00 00:00:00') {
    // Try parsing as YYYY-MM-DD or YYYY-MM-DD HH:MM:SS
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $issueDateRaw, $m)) {
        // Direct format without strtotime to avoid timezone issues
        $issueDate = sprintf('%02d-%02d-%04d', (int)$m[3], (int)$m[2], (int)$m[1]);
    } elseif ($ts = strtotime($issueDateRaw)) {
        $issueDate = date('d-m-Y', $ts);
    }
}
$currency = 'USD';
$currencyCandidates = [
    $invoice['currency_code'] ?? null,
    $invoice['currency'] ?? null,
    $invoice['so_currency_code'] ?? null,
    $invoice['so_currency'] ?? null,
    $invoice['sales_order_currency'] ?? null,
    $invoice['default_currency'] ?? null,
    $company['default_sales_currency'] ?? null,
    $company['currency_code'] ?? null,
    $company['default_currency'] ?? null,
    $company['currency'] ?? null,
];
foreach ($currencyCandidates as $cand) {
    $cand = strtoupper(trim((string)$cand));
    if ($cand !== '') {
        $currency = $cand;
        break;
    }
}
$currencySymbols = [
    'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'PKR' => '₨', 'INR' => '₹', 'JPY' => '¥', 'CNY' => '¥',
];
$currencySymbol = $currencySymbols[$currency] ?? ($currency !== '' ? $currency : '$');
$paymentTerms = trim((string)($invoice['payment_terms'] ?? ($invoice['payment_terms_code'] ?? '')));

$customerLabel = trim((string)($customer['name'] ?? ($customer['company_name'] ?? ($customer['customer_code'] ?? ($customer['customer_name'] ?? 'Customer')))));
$customerLines = [];
if (is_array($customerAddress)) {
    if (!empty($customerAddress['line1'])) $customerLines[] = $customerAddress['line1'];
    if (!empty($customerAddress['line2'])) $customerLines[] = $customerAddress['line2'];
}
$cityState = '';
if (is_array($customerAddress)) {
    $cityState = trim(($customerAddress['city_name'] ?? '') . ' ' . ($customerAddress['state_name'] ?? ''));
}
if ($cityState !== '') $customerLines[] = $cityState;
if (is_array($customerAddress) && !empty($customerAddress['postal_code'])) $customerLines[] = 'Postal: ' . $customerAddress['postal_code'];
if (!empty($customer['phone'])) $customerLines[] = 'Phone: ' . $customer['phone'];
if (!empty($customer['mobile'])) $customerLines[] = 'Mobile: ' . $customer['mobile'];
if (!empty($customer['email'])) $customerLines[] = 'Email: ' . $customer['email'];
// Fallback if address table is empty: use customer record address fields if present
if (empty($customerLines)) {
    if (!empty($customer['address'])) $customerLines[] = $customer['address'];
    if (!empty($customer['city'])) $customerLines[] = $customer['city'];
}

// Determine a representative (single) discount/tax % for headings when present.
$discHeadingPct = null;
$taxHeadingPct = null;
$hasAnyDiscount = false;
$hasAnyTax = false;
if (!empty($lines)) {
    $discVals = [];
    $taxVals = [];
    foreach ($lines as $ln) {
        $dv = (float)($ln['discount_value'] ?? 0);
        $da = (float)($ln['discount_amount'] ?? 0);
        $tr = (float)($ln['tax_rate'] ?? ($ln['tax'] ?? 0));
        $ta = (float)($ln['tax_amount'] ?? 0);
        if ($dv > 0 || $da > 0) $hasAnyDiscount = true;
        if ($tr > 0 || $ta > 0) $hasAnyTax = true;
        if ($dv > 0) $discVals[] = $dv;
        if ($tr > 0) $taxVals[] = $tr;
    }
    if (!empty($discVals)) {
        $discHeadingPct = round($discVals[0], 2);
        foreach ($discVals as $v) {
            if (abs($v - $discHeadingPct) > 0.0001) { $discHeadingPct = null; break; }
        }
    }
    if (!empty($taxVals)) {
        $taxHeadingPct = round($taxVals[0], 2);
        foreach ($taxVals as $v) {
            if (abs($v - $taxHeadingPct) > 0.0001) { $taxHeadingPct = null; break; }
        }
    }
}

$fmtDate = function ($value) {
    $value = trim((string)$value);
    if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') return '';
    $ts = strtotime($value);
    if ($ts === false || $ts <= 0) return '';
    return date('d-m-Y', $ts);
};

$subtotal = isset($invoice['subtotal']) ? (float)$invoice['subtotal'] : 0.0;
$tax = isset($invoice['tax_total']) ? (float)$invoice['tax_total'] : 0.0;
$shipping = 0.0;
// Prefer shipping_amount (sales orders) then shipping_cost; also allow plain 'shipping' if present
if (isset($invoice['shipping_amount'])) {
    $shipping = (float)$invoice['shipping_amount'];
} elseif (isset($invoice['shipping_cost'])) {
    $shipping = (float)$invoice['shipping_cost'];
} elseif (isset($invoice['shipping'])) {
    $shipping = (float)$invoice['shipping'];
}
// Separate line-level and document-level discounts
$documentDiscountType = isset($invoice['document_discount_type']) ? (string)$invoice['document_discount_type'] : 'fixed';
$documentDiscountValue = isset($invoice['document_discount_value']) ? (float)$invoice['document_discount_value'] : 0.0;
$documentDiscountAmount = 0.0;

$computedSubtotal = 0.0;
$computedLineDiscount = 0.0;
$computedTax = 0.0;
if (!empty($lines)) {
    foreach ($lines as $ln) {
        $qty = (float)($ln['quantity'] ?? 0);
        $unitPrice = (float)($ln['unit_price'] ?? 0);
        $lineBase = $qty * $unitPrice;
        // Only count line discount if the line actually has discount data
        $discAmt = isset($ln['discount_amount']) ? (float)$ln['discount_amount'] : 0.0;
        if ($discAmt === 0.0 && isset($ln['discount_value'])) {
            $discValue = (float)$ln['discount_value'];
            if ($discValue > 0) {
                $discAmt = $lineBase * ($discValue / 100.0);
            }
        }
        $taxRate = isset($ln['tax_rate']) ? (float)$ln['tax_rate'] : (isset($ln['tax']) ? (float)$ln['tax'] : 0.0);
        $taxAmt = isset($ln['tax_amount']) ? (float)$ln['tax_amount'] : (($lineBase - $discAmt) * ($taxRate / 100.0));
        $computedSubtotal += $lineBase;
        $computedLineDiscount += $discAmt;
        $computedTax += $taxAmt;
    }
}

$lineDiscountTotal = $computedLineDiscount;

// Calculate document-level discount if present
// IMPORTANT: Match QuotationModel::calculateTotals() logic
// documentBase = (subtotal - lineDiscount) + tax + (shipping if not excluded)
if ($documentDiscountValue > 0) {
    // Get discount_exclude_shipping flag
    $discountExcludeShipping = (int)($invoice['discount_exclude_shipping'] ?? 1);
    
    // Build the document discount base: lineNet + tax + (shipping if applicable)
    $lineNet = $computedSubtotal - $lineDiscountTotal;
    $documentBase = $lineNet + $computedTax + ($discountExcludeShipping ? 0.0 : $shipping);
    
    // Calculate document discount from correct base
    if ($documentDiscountType === 'percent') {
        $documentDiscountAmount = $documentBase * ($documentDiscountValue / 100.0);
    } else {
        $documentDiscountAmount = $documentDiscountValue;
    }
    // Ensure document discount doesn't exceed the base
    $documentDiscountAmount = min(max(0.0, $documentDiscountAmount), $documentBase);
} else {
    $documentDiscountAmount = 0.0;
}

// Ensure subtotal reflects computed value if not set
if ($subtotal == 0.0 && $computedSubtotal > 0) {
    $subtotal = $computedSubtotal;
}
if ($tax == 0.0 && $computedTax > 0) {
    $tax = $computedTax;
}

// Total discount = line-level + document-level
$discountTotal = $lineDiscountTotal + $documentDiscountAmount;
$totalRowLabel = ($discountTotal > 0.0005) ? 'Total (After Discounts)' : 'Total';

// If shipping wasn't provided but total_amount exists, derive shipping so the PDF shows the real charge
$derivedTotal = isset($invoice['total_amount']) ? (float)$invoice['total_amount'] : null;
if ($shipping == 0.0 && $derivedTotal !== null) {
    $calcWithoutShipping = $subtotal - $discountTotal + $tax;
    $candidateShip = $derivedTotal - $calcWithoutShipping;
    if (abs($candidateShip) > 0.0001) {
        $shipping = $candidateShip;
    }
}

$total = (float)($derivedTotal ?? ($subtotal - $discountTotal + $tax + $shipping));

$fmtMoney = function ($value = 0.0) use ($currencySymbol) {
    $formatted = number_format((float)$value, 2);
    $space = strlen($currencySymbol) > 1 ? ' ' : '';
    return $currencySymbol . $space . $formatted;
};

$customerLabel = trim($customer['name'] ?? $customer['company_name'] ?? $customer['customer_name'] ?? 'Customer');
$customerCode = trim((string)($customer['customer_code'] ?? ''));

// Build a compact address block
$addr1 = trim((string)($customerAddress['line1'] ?? ''));
$addr2 = trim((string)($customerAddress['line2'] ?? ''));
$addrCombined = '';
if ($addr1 !== '' && $addr2 !== '') {
    $addrCombined = $addr1 . ', ' . $addr2;
} elseif ($addr1 !== '') {
    $addrCombined = $addr1;
} elseif ($addr2 !== '') {
    $addrCombined = $addr2;
}
$cityState = trim((string)(($customerAddress['city_name'] ?? '') . ' ' . ($customerAddress['state_name'] ?? '')));
if ($cityState !== '') {
    $addrCombined = $addrCombined !== '' ? ($addrCombined . ', ' . $cityState) : $cityState;
}
if (!empty($customerAddress['postal_code'])) {
    $addrCombined = $addrCombined !== '' ? ($addrCombined . ', Postal: ' . $customerAddress['postal_code']) : ('Postal: ' . $customerAddress['postal_code']);
}
// Resolve country name from country_name, country_id, or country field
$countryNameForAddr = '';
if (!empty($customerAddress['country_name'])) {
    $countryNameForAddr = $customerAddress['country_name'];
} elseif (!empty($customerAddress['country_id'])) {
    try {
        $cr = \Config\Database::connect()->table('countries')->select('name')->where('id', (int)$customerAddress['country_id'])->get()->getRowArray();
        if ($cr) $countryNameForAddr = $cr['name'];
    } catch (\Throwable $e) {}
} elseif (!empty($customerAddress['country']) && strlen((string)$customerAddress['country']) > 2) {
    $countryNameForAddr = $customerAddress['country'];
}
if (!empty($countryNameForAddr)) {
    $addrCombined = $addrCombined !== '' ? ($addrCombined . ', ' . $countryNameForAddr) : $countryNameForAddr;
}

$contactParts = [];
if (!empty($customer['mobile'])) $contactParts[] = 'Mob: ' . $customer['mobile'];
if (!empty($customer['phone'])) $contactParts[] = 'Ph: ' . $customer['phone'];
$contactLine = !empty($contactParts) ? implode(', ', $contactParts) : null;

$customerLines = array_filter([$addrCombined ?: null, $contactLine]);

$companyLines = array_filter([
    trim($company['address'] ?? ''),
    !empty($company['phone']) ? ('Phone: ' . trim($company['phone'])) : (!empty($company['contact']) ? ('Phone: ' . trim($company['contact'])) : null),
    !empty($company['email']) ? trim($company['email']) : null,
]);

// Printed company contact block for the invoice header. This is presentation
// content only; it does not change the company record or invoice payload.
$headerCompanyAddressLines = [
    'REHMAT PURA, NEAR CHIRAGAH MASJID DALOWALI',
    'PO BOX 51310, SIALKOT',
];
$headerCompanyPhone = '+92 300 6111 852';

// Build professional footer with company info
$footerParts = [];
if (!$hideCompanyWebsite && !empty($company['website'])) $footerParts[] = $company['website'];
if (!empty($company['email'])) $footerParts[] = $company['email'];
$phoneForFooter = $company['phone'] ?? ($company['contact'] ?? '');
if (!empty($phoneForFooter)) $footerParts[] = $phoneForFooter;
$footerText = trim((string)($company['invoice_footer'] ?? ''));
if ($footerText === '' && !empty($footerParts)) {
    $footerText = implode('  |  ', $footerParts);
}
$showCompanyNameInLogoArea = (empty($logoSrc) && !empty($company['name']));
$companyInitials = '';
foreach (preg_split('/\s+/', trim((string)($company['name'] ?? ''))) ?: [] as $companyWord) {
    if ($companyWord !== '') {
        $companyInitials .= strtoupper(substr($companyWord, 0, 1));
    }
    if (strlen($companyInitials) >= 2) {
        break;
    }
}
if ($companyInitials === '') {
    $companyInitials = 'CO';
}

$paymentSnapshot = $paymentSnapshot ?? ($payload['paymentSnapshot'] ?? []);
$paymentStatus = strtolower(trim((string)($paymentSnapshot['status'] ?? 'unpaid')));
$paymentInvoiceTotal = isset($paymentSnapshot['invoice_total']) ? (float)$paymentSnapshot['invoice_total'] : $total;
$paymentPaidTotal = isset($paymentSnapshot['paid_total']) ? (float)$paymentSnapshot['paid_total'] : 0.0;
$paymentBalanceDue = isset($paymentSnapshot['balance_due']) ? (float)$paymentSnapshot['balance_due'] : max(0.0, $paymentInvoiceTotal - $paymentPaidTotal);
$paymentPaidOn = trim((string)($paymentSnapshot['paid_on'] ?? ''));
$paymentRows = is_array($paymentSnapshot['payments'] ?? null) ? $paymentSnapshot['payments'] : [];
$hasAnyPayment = ($paymentPaidTotal > 0.005) || !empty($paymentRows);
if (($paymentStatus !== 'paid' && $paymentStatus !== 'partial') && $paymentPaidTotal > 0.005) {
    $paymentStatus = ($paymentBalanceDue <= 0.005) ? 'paid' : 'partial';
}

$paymentStatusLabel = 'Unpaid';
if ($paymentStatus === 'paid') {
    $paymentStatusLabel = 'Paid';
} elseif ($paymentStatus === 'partial') {
    $paymentStatusLabel = 'Partially Paid';
}

// Milestone payment terms: instalment schedule, bank details and T&C blocks.
$paymentSchedule  = $paymentSchedule ?? ($payload['paymentSchedule'] ?? []);
$scheduleRows     = is_array($paymentSchedule['rows'] ?? null) ? $paymentSchedule['rows'] : [];
$hasSchedule      = !empty($paymentSchedule['has_schedule']) && !empty($scheduleRows);
$scheduleTermName = trim((string)($paymentSchedule['term']['name'] ?? ''));
$scheduleTotal    = (float)($paymentSchedule['total'] ?? 0);
$schedulePaid     = (float)($paymentSchedule['paid'] ?? 0);
$scheduleDue      = (float)($paymentSchedule['due'] ?? 0);
$scheduleNowDue   = (float)($paymentSchedule['now_due'] ?? 0);
$scheduleNowLabel = trim((string)($paymentSchedule['now_due_label'] ?? ''));
$scheduleNowPct   = (float)($paymentSchedule['now_due_percentage'] ?? 0);
helper('invoice_terms');
$scheduleTermNote = invoice_short_note((string)($paymentSchedule['term']['description'] ?? ''));
if ($scheduleNowPct > 0) {
    $scheduleNowLabel = trim($scheduleNowLabel . ' (' . rtrim(rtrim(number_format($scheduleNowPct, 2), '0'), '.') . '%)');
}

// The named term is more useful in the meta strip than a bare terms code.
if ($scheduleTermName !== '') {
    $paymentTerms = $scheduleTermName;
}

$bankDetailsText  = trim((string)($bankDetailsText ?? ($company['bank_details'] ?? '')));
$invoiceTermsText = trim((string)($invoiceTermsText ?? ($company['invoice_terms'] ?? '')));
helper('invoice_terms');
$bankDetailsHtml  = invoice_bank_details_html($bankDetailsText);
$invoiceTermsHtml = invoice_terms_html($invoiceTermsText);

// The block below is pinned to the foot of the page, so the flow has to keep
// that band free or a long invoice would print its last rows underneath it.
$termsRowCount = static function (string $html): int {
    return max(
        substr_count($html, '<tr'),
        substr_count($html, '<li') + substr_count($html, '<p') + substr_count($html, '<br')
    );
};
$termsReserve = 156
    + ($termsRowCount($bankDetailsHtml) * 11)
    + ($termsRowCount($invoiceTermsHtml) * 18);
$termsReserve = max(170, min(400, $termsReserve));
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 20px 24px 56px 24px; }
        body {
            font-family: 'DejaVu Sans', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 0;
            background: #fff;
            color: #1e293b;
        }
        .page { max-width: 780px; margin: 0 auto; }

        /* === HEADER === */
        .header-table { width: 100%; margin-bottom: 0; }
        .logo-box img {
            max-height: 60px;
            max-width: 180px;
            object-fit: contain;
        }
        .company-name-text {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: 0.02em;
        }
        .brand-lockup {
            white-space: nowrap;
        }
        .brand-lockup .company-name-text {
            display: inline-block;
            vertical-align: middle;
        }
        .brand-fallback {
            display: inline-block;
            width: 30px;
            height: 30px;
            margin-right: 7px;
            line-height: 30px;
            vertical-align: middle;
            text-align: center;
            background: #183153;
            color: #ffffff;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.04em;
        }
        .company-header-details {
            margin-top: 4px;
            font-size: 8.5px;
            line-height: 1.35;
            color: #64748b;
        }
        .company-header-details .phone {
            margin-top: 2px;
            color: #334155;
            font-weight: 600;
        }
        .company-info { text-align: right; }
        .company-name-right {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
        }
        .company-line {
            font-size: 9px;
            color: #64748b;
            line-height: 1.4;
        }

        /* === TITLE BAND === */
        .doc-title-band {
            background: #f3f4f6;
            text-align: center;
            padding: 10px 0 9px 0;
            margin: 10px 0 0 0;
            border-bottom: 1px solid #d1d5db;
        }
        .doc-title-band .doc-type-title {
            font-size: 16px;
            font-weight: 700;
            color: #374151;
            letter-spacing: 0.28em;
            text-transform: uppercase;
        }

        /* === META TABLE ===
           A labelled table, so the reader looks up the invoice number, date
           and terms in one place instead of reading a run-on strip. */
        .header-split {
            width: 100%;
            max-width: 100%;
            margin: 12px 0 14px 0;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .header-split,
        .header-split td,
        .header-split table,
        .terms-block,
        .terms-block table,
        .terms-block td,
        .terms-block th,
        .terms-block div {
            box-sizing: border-box;
        }
        .header-split .hs-left {
            width: 30%;
            vertical-align: top;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
        }
        .header-split .hs-gap {
            width: 45%;
        }
        .header-split .hs-right {
            width: 25%;
            vertical-align: top;
            border: 0;
            background: transparent;
        }
        .invoice-meta {
            width: 100%;
            max-width: 100%;
            margin: 0;
            border: 1px solid #cbd5e1;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .invoice-meta td {
            font-size: 7.2px;
            color: #0f172a;
            border-bottom: 1px solid #cbd5e1;
            padding: 3px 4px;
            vertical-align: top;
        }
        .invoice-meta td.meta-lbl {
            border-right: 1px solid #cbd5e1;
        }
        .invoice-meta tr.meta-last td {
            border-bottom: none;
        }
        .invoice-meta td.meta-lbl {
            width: 43%;
            background: #f1f5f9;
            color: #475569;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-size: 7.2px;
            white-space: nowrap;
        }
        .invoice-meta td.meta-val {
            width: 57%;
            font-weight: bold;
            white-space: normal;
            overflow-wrap: anywhere;
            word-wrap: break-word;
            word-break: normal;
        }

        /* === BILL TO === */
        .billto-wrap {
            width: 100%;
            margin-bottom: 0;
        }
        .customer-card {
            padding: 7px 8px;
            margin-bottom: 0;
            word-wrap: break-word;
        }
        .customer-card .title {
            font-size: 7.4px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            font-weight: 700;
            color: #64748b;
            margin-bottom: 3px;
        }
        .customer-card .customer-code-line {
            font-size: 7.4px;
            color: #64748b;
            margin-bottom: 3px;
        }
        .customer-card .customer-code-line strong {
            color: #334155;
            font-weight: 600;
        }
        .customer-card .name {
            font-size: 10.5px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .customer-card p {
            margin: 1px 0;
            font-size: 7.8px;
            color: #475569;
            line-height: 1.25;
        }

        /* === ITEMS TABLE === */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 60px;
            margin-bottom: 14px;
        }
        .items-table th {
            background: #f3f4f6;
            color: #374151;
            font-size: 8.5px;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            padding: 6px 5px;
            text-align: left;
            border-bottom: 2px solid #9ca3af;
        }
        .items-table td {
            border-bottom: 1px solid #e2e8f0;
            padding: 5px 5px;
            font-size: 9.5px;
            line-height: 1.3;
            background: #fff;
            vertical-align: middle;
        }

        .items-table .image-cell {
            text-align: center;
            width: 36px;
            padding: 3px 2px;
        }
        .items-table .image-cell img {
            max-height: 30px;
            max-width: 30px;
            object-fit: contain;
        }
        .items-table .no-img {
            display: inline-block;
            width: 28px;
            height: 28px;
            line-height: 28px;
            border: 1px solid #e2e8f0;
            color: #94a3b8;
            font-size: 7px;
            text-align: center;
            background: #f1f5f9;
            vertical-align: middle;
        }
        .items-table .numeric { text-align: right; }
        .items-table th.numeric { text-align: right; }

        /* === TOTALS === */
        .totals-wrap {
            width: 44%;
            margin-left: auto;
        }
        .totals {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            margin-top: 0;
            border: 1px solid #cbd5e1;
        }
        .totals td {
            padding: 5px 8px;
            border: 1px solid #cbd5e1;
        }
        .totals .label {
            text-align: right;
            color: #64748b;
            font-weight: 500;
            background: #f8fafc;
        }
        .totals .value {
            text-align: right;
            font-weight: 600;
            color: #1e293b;
            min-width: 110px;
        }
        .total-row td {
            border: 1px solid #b7dfc5;
            padding: 7px 8px;
            background: #ecfdf3;
        }
        .shipping-row td {
            background: #f1f5f9;
            color: #334155;
        }
        .total-row .label {
            font-weight: 700;
            color: #111827;
            font-size: 11px;
        }
        .total-row .value {
            color: #111827;
            font-size: 13px;
            font-weight: 700;
        }

        /* === PAYMENT SUMMARY === */
        .payment-summary-wrap {
            width: 44%;
            margin-left: auto;
            margin-top: 8px;
        }
        .schedule-block {
            margin-top: 16px;
            page-break-inside: avoid;
        }
        .schedule-block h4 {
            margin: 0 0 4px 0;
            font-size: 9.6px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #0f172a;
        }
        .sched-note-inline {
            font-weight: normal;
            text-transform: none;
            letter-spacing: 0;
            color: #64748b;
            font-size: 8.6px;
        }
        .schedule-note {
            font-size: 8.4px;
            color: #64748b;
            margin-bottom: 4px;
        }
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
        }
        .schedule-table th {
            font-size: 8.2px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #475569;
            background: #f1f5f9;
            padding: 3px 5px;
            text-align: left;
            border-bottom: 1px solid #cbd5e1;
        }
        .schedule-table td {
            font-size: 8.6px;
            color: #0f172a;
            padding: 3px 5px;
            border-bottom: 1px dashed #e2e8f0;
        }
        .schedule-table .num {
            text-align: right;
        }
        .schedule-table tr.sched-overdue td {
            color: #b91c1c;
        }
        .schedule-table tfoot td {
            font-weight: bold;
            border-top: 1px solid #94a3b8;
            border-bottom: none;
            background: #f8fafc;
        }
        .sched-status {
            font-weight: bold;
            font-size: 8.2px;
            letter-spacing: 0.03em;
        }
        /* A plain right-aligned list: the customer reads what is owed now
           without the totals looking like a second invoice. */
        .sched-totals {
            width: 46%;
            margin-left: auto;
            margin-top: 6px;
            border-collapse: collapse;
        }
        .sched-totals td {
            font-size: 8.8px;
            color: #475569;
            padding: 2px 0;
        }
        .sched-totals td.val {
            text-align: right;
            font-weight: bold;
            color: #0f172a;
        }
        .sched-totals tr.now td {
            font-size: 10px;
            color: #111827;
            font-weight: bold;
            border: 1px solid #b7dfc5;
            background: #ecfdf3;
            padding: 5px 6px;
        }
        .sched-paidon {
            font-size: 7.6px;
            color: #64748b;
        }
        /* Pinned to the foot of the page so the schedule above it is not
           crowded. Dompdf places an absolute block on the page its normal
           flow position falls on, so a spilled invoice keeps it on the last
           page. */
        .terms-block {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 40px;
            width: 100%;
            page-break-inside: avoid;
        }
        .terms-layout {
            width: 100%;
            max-width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .terms-layout .bank-spacer {
            width: 70%;
            padding: 0;
            border: 0;
        }
        .terms-cell {
            vertical-align: top;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            padding: 0;
        }
        .terms-cell-left { width: 30%; }
        .terms-cell-full { width: 100%; }
        .terms-below {
            width: 100%;
        }
        .terms-below-spaced {
            margin-top: 100px;
        }
        .terms-cell h4 {
            margin: 0;
            background: #f1f5f9;
            border-bottom: 1px solid #cbd5e1;
            padding: 3px 5px;
            font-size: 7.8px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #0f172a;
        }
        .terms-cell .terms-body {
            padding: 3px 5px;
            font-size: 7px;
            line-height: 1.25;
            color: #334155;
            min-width: 0;
            white-space: normal;
            overflow-wrap: anywhere;
            word-wrap: break-word;
            word-break: normal;
        }
        .terms-cell .terms-body table {
            width: 100% !important;
            max-width: 100%;
            table-layout: fixed;
        }
        .conditions-cell .terms-body table {
            table-layout: auto;
        }
        .terms-below .terms-body {
            font-size: 7.4px;
        }
        .terms-cell .terms-body td,
        .terms-cell .terms-body th,
        .terms-cell .terms-body p,
        .terms-cell .terms-body li,
        .terms-cell .terms-body div,
        .terms-cell .terms-body span,
        .terms-cell .terms-body strong {
            max-width: 100%;
            white-space: normal !important;
            overflow-wrap: anywhere;
            word-wrap: break-word;
            word-break: normal;
        }
        .bank-details-cell .terms-body td:first-child {
            width: 40%;
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            border-right: 1px solid #dbe4ee !important;
        }
        .bank-details-cell .terms-body td:last-child {
            width: 60%;
            background: #ffffff;
            color: #0f172a;
            font-weight: 700;
        }
        .bank-details-cell .terms-body {
            padding: 0;
        }
        .bank-details-cell .terms-body table {
            border-collapse: collapse;
        }
        .bank-details-cell .terms-body td {
            padding: 4px 6px !important;
            border-bottom: 1px solid #dbe4ee !important;
            vertical-align: middle;
        }
        .bank-details-cell .terms-body tr:last-child td {
            border-bottom: 0 !important;
        }
        .bank-details-cell .terms-body td:first-child::after {
            content: ':';
        }
        .payment-summary {
            border: 1px solid #d4dde8;
            background: #fdfefe;
            border-radius: 6px;
            border-left: 5px solid #183153;
            padding: 7px 9px;
            /* #183153 is a matte dark blue */
        }
        .payment-summary h4 {
            margin: 0 0 5px 0;
            font-size: 9.6px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #0f172a;
        }
        .payment-status-line {
            margin-bottom: 5px;
            font-size: 9.2px;
            color: #1f2937;
        }
        .payment-chip {
            display: inline-block;
            font-size: 9.2px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 0 2px;
            border-radius: 0;
            border: none;
            background: none;
            color: #183153;
            margin-left: 4px;
        }
        .payment-chip-paid,
        .payment-chip-partial,
        .payment-chip-unpaid {
            background: none;
            border: none;
            color: #183153;
        }
        .payment-metrics {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2px;
        }
        .payment-metrics td {
            font-size: 8.8px;
            padding: 2px 0;
            border-bottom: 1px dashed #dbe4ef;
        }
        .payment-metrics td:first-child {
            color: #64748b;
            width: 55%;
        }
        .payment-metrics td:last-child {
            text-align: right;
            color: #0f172a;
            font-weight: 600;
        }
        .payment-balance-row td {
            background: #fff7ed;
            border-top: 1px solid #fed7aa;
            border-bottom: 1px solid #fed7aa;
            font-weight: 700;
            padding-top: 4px;
            padding-bottom: 4px;
        }
        .payment-balance-row td:first-child {
            color: #9a3412;
            padding-left: 6px;
            font-size: 9.2px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .payment-balance-row td:last-child {
            color: #9a3412;
            padding-right: 6px;
            font-size: 10.2px;
            font-weight: 800;
        }
        .payment-balance-row-settled td {
            background: #ecfdf5;
            border-top: 1px solid #a7f3d0;
            border-bottom: 1px solid #a7f3d0;
        }
        .payment-balance-row-settled td:first-child,
        .payment-balance-row-settled td:last-child {
            color: #065f46;
        }
        .payment-ref-strip {
            margin-top: 6px;
            padding: 4px 0 0 0;
            border-top: 1px dashed #dbe4ef;
            font-size: 8.7px;
            color: #334155;
            line-height: 1.4;
        }
        .payment-ref-strip strong {
            color: #0f172a;
            font-weight: 700;
            margin-right: 4px;
        }
        .payment-ref-numbers {
            color: #0f172a;
            font-weight: 600;
            letter-spacing: 0.01em;
        }

        /* === FOOTER === */
        .footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            padding: 7px 24px 8px 24px;
            text-align: center;
            font-size: 8.5px;
            color: #6b7280;
            background: #fff;
            border-top: 1px solid #d1d5db;
        }
        .footer-inner {
            display: inline-block;
            letter-spacing: 0.03em;
        }
    </style>
    <link rel="stylesheet" href="<?= base_url('assets/css/product-image-hover-preview.css') ?>?v=1">
</head>
<body>
    <div class="page">
        <!-- HEADER: Logo + Company Info -->
        <table class="header-table" cellpadding="0" cellspacing="0">
            <tr>
                <td width="50%" valign="middle">
                    <?php if (!empty($logoSrc) && $canRenderImages): ?>
                        <div class="logo-box">
                            <img src="<?= esc($logoSrc) ?>" alt="Logo">
                        </div>
                    <?php elseif ($showCompanyNameInLogoArea): ?>
                        <div class="brand-lockup">
                            <span class="brand-fallback" aria-hidden="true"><?= esc($companyInitials) ?></span>
                            <span class="company-name-text"><?= esc($company['name']) ?></span>
                        </div>
                        <div class="company-header-details">
                            <?php foreach ($headerCompanyAddressLines as $headerLine): ?>
                                <div><?= esc($headerLine) ?></div>
                            <?php endforeach; ?>
                            <div class="phone">Mobile: <?= esc($headerCompanyPhone) ?></div>
                        </div>
                    <?php endif; ?>
                </td>
                <td width="50%" align="right" valign="top">
                    <?php if ($pdfShowHeader): ?>
                    <div class="company-info">
                        <?php if (!$showCompanyNameInLogoArea): ?>
                            <div class="company-name-right"><?= esc($company['name'] ?? 'Company Name') ?></div>
                        <?php endif; ?>
                        <?php if (!empty($logoSrc) && $canRenderImages): ?>
                            <div class="company-header-details">
                                <?php foreach ($headerCompanyAddressLines as $headerLine): ?>
                                    <div><?= esc($headerLine) ?></div>
                                <?php endforeach; ?>
                                <div class="phone">Mobile: <?= esc($headerCompanyPhone) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <!-- DOCUMENT TITLE BAND -->
        <div class="doc-title-band">
            <div class="doc-type-title"><?= esc($documentTitle) ?></div>
        </div>

        <!-- HEADER: customer on the left, document facts on the right -->
        <table class="header-split" cellpadding="0" cellspacing="0">
            <tr>
                <td class="hs-left">
            <!-- BILL TO -->
            <div class="billto-wrap">
                <div class="customer-card">
                    <div class="title"><?= esc($partyLabel) ?></div>
                    <?php if ($customerCode !== ''): ?>
                        <div class="customer-code-line">Customer #: <strong><?= esc($customerCode) ?></strong></div>
                    <?php endif; ?>
                    <div class="name"><?= esc($customerLabel) ?></div>
                    <?php foreach ($customerLines as $line): ?>
                        <p><?= esc($line) ?></p>
                    <?php endforeach; ?>
                </div>
            </div>
                </td>
                <td class="hs-gap"></td>
                <td class="hs-right">
            <!-- META: the document facts, beside the customer -->
            <?php
            // Built as a list so the last row can drop its divider: the cell
            // border already closes the box, whatever rows the invoice has.
            $metaRows = [];
            if ($issueDate !== '') {
                $metaRows[] = [rtrim($documentDateLabel, ':'), $issueDate];
            }
            if ($invoiceNo !== '') {
                $metaRows[] = [rtrim($documentNumberLabel, ':'), $invoiceNo];
            }
            if ($paymentTerms !== '' && $paymentTerms !== $currency) {
                $metaRows[] = ['Payment Terms', $paymentTerms];
            }
            if (!empty($invoice['customer_po_number'])) {
                $metaRows[] = ['Customer PO #', $invoice['customer_po_number']];
            }
            if (!empty($invoice['delivery_date']) && strpos($invoice['delivery_date'], '0000') === false) {
                $metaRows[] = ['Delivery Date', $fmtDate($invoice['delivery_date'])];
            }
            if (!empty($invoice['salesperson'])) {
                $metaRows[] = ['Salesperson', $invoice['salesperson']];
            }
            $metaLast = count($metaRows) - 1;
            ?>
            <table class="invoice-meta" cellpadding="0" cellspacing="0">
                <?php foreach ($metaRows as $mi => $mr): ?>
                <tr class="<?= $mi === $metaLast ? 'meta-last' : '' ?>">
                    <td class="meta-lbl"><?= esc($mr[0]) ?></td>
                    <td class="meta-val"><?= esc($mr[1]) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
                </td>
            </tr>
        </table>

        <!-- LINE ITEMS -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width:14%;">Code</th>
                    <?php if ($canRenderImages): ?>
                        <th style="width:7%;">Image</th>
                    <?php endif; ?>
                    <th>Description</th>
                    <th style="width:8%;" class="numeric">Qty</th>
                    <th style="width:12%;" class="numeric">Unit Price</th>
                    <?php if ($hasAnyDiscount): ?>
                    <th style="width:10%;" class="numeric">Disc<?= $discHeadingPct !== null ? ' (' . rtrim(rtrim(number_format($discHeadingPct, 2), '0'), '.') . '%)' : '' ?></th>
                    <?php endif; ?>
                    <?php if ($hasAnyTax): ?>
                    <th style="width:10%;" class="numeric">Tax<?= $taxHeadingPct !== null ? ' (' . rtrim(rtrim(number_format($taxHeadingPct, 2), '0'), '.') . '%)' : '' ?></th>
                    <?php endif; ?>
                    <th style="width:12%;" class="numeric">Line Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($lines)): ?>
                    <?php
                        $activeSectionTitle = '';
                        $activeSectionSubtotal = 0.0;
                        $totalColumns = 5 + ($canRenderImages ? 1 : 0) + ($hasAnyDiscount ? 1 : 0) + ($hasAnyTax ? 1 : 0);
                    ?>
                    <?php foreach ($lines as $idx => $ln):
                        $displayType = strtolower((string)($ln['display_type'] ?? 'line'));
                        $isSection = $displayType === 'section';
                        if ($isSection):
                            continue;
                        endif;
                        $qty = (float)($ln['quantity'] ?? 0);
                        $unitPrice = (float)($ln['unit_price'] ?? 0);
                        $lineBase = $qty * $unitPrice;
                        $discAmt = isset($ln['discount_amount']) ? (float)$ln['discount_amount'] : 0.0;
                        if ($discAmt === 0.0 && isset($ln['discount_value'])) {
                            $discAmt = $lineBase * ((float)$ln['discount_value'] / 100.0);
                        }
                        $taxRate = isset($ln['tax_rate']) ? (float)$ln['tax_rate'] : (isset($ln['tax']) ? (float)$ln['tax'] : 0.0);
                        $taxAmt = isset($ln['tax_amount']) ? (float)$ln['tax_amount'] : (($lineBase - $discAmt) * ($taxRate / 100.0));
                        $lineTotalRaw = isset($ln['line_total']) ? (float)$ln['line_total'] : null;
                        $lineTotalComputed = ($lineBase - $discAmt + $taxAmt);
                        $lineTotal = ($lineTotalRaw !== null && $lineTotalRaw > 0)
                            ? $lineTotalRaw
                            : $lineTotalComputed;


                        // PRODUCT IMAGE: Prefer controller-enriched paths (variant first, then product).
                        // Images are converted to base64 data URIs for reliable Dompdf rendering on all platforms.
                        $imgPath = '';
                        $filename = '';
                        $maxImageBytes = 2 * 1024 * 1024; // 2MB safety cap
                        $resolvedLocalFile = '';

                        // Resolve local file from controller-enriched file:// path
                        $_enrichedPath = $ln['variant_image_path'] ?? ($ln['product_image_path'] ?? '');
                        if (!empty($_enrichedPath)) {
                            $_localTmp = (string)$_enrichedPath;
                            if (strncmp($_localTmp, 'file://', 7) === 0) {
                                // Strip only the scheme. Stripping every slash (old '#^file://+#')
                                // turned 'file:///var/www/...' into a relative 'var/www/...' and the
                                // image silently vanished from the PDF on POSIX hosts.
                                $_localTmp = substr($_localTmp, 7);
                                if ($_localTmp !== '' && $_localTmp[0] !== '/' && !preg_match('#^[A-Za-z]:#', $_localTmp)) {
                                    $_localTmp = '/' . $_localTmp;
                                }
                                // 'file:///D:/path' -> '/D:/path' -> 'D:/path'
                                if (preg_match('#^/[A-Za-z]:#', $_localTmp)) {
                                    $_localTmp = ltrim($_localTmp, '/');
                                }
                            }
                            $_localTmp = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $_localTmp);
                            if ($_localTmp !== '' && is_file($_localTmp)) {
                                $resolvedLocalFile = $_localTmp;
                            }
                        }

                        // Fallback: build filename from various line keys and search both image folders
                        if ($resolvedLocalFile === '') {
                            if (!empty($ln['product_images'])) {
                                $arr = is_string($ln['product_images']) ? json_decode($ln['product_images'], true) : $ln['product_images'];
                                if (is_array($arr) && !empty($arr[0]) && is_string($arr[0])) {
                                    $filename = basename($arr[0]);
                                }
                            }
                            if ($filename === '' && !empty($ln['product_image'])) {
                                $filename = basename((string)$ln['product_image']);
                            }
                            if ($filename === '' && !empty($ln['image'])) {
                                $filename = basename((string)$ln['image']);
                            }
                            // Document lines store the picture as a full URL - use its basename
                            // when the enriched absolute path could not be resolved.
                            foreach (['variant_image_path', 'product_image_path', 'product_image_url', 'image_url'] as $_urlKey) {
                                if ($filename !== '') break;
                                if (!empty($ln[$_urlKey])) {
                                    $filename = basename(parse_url((string)$ln[$_urlKey], PHP_URL_PATH) ?: (string)$ln[$_urlKey]);
                                }
                            }
                            if ($filename !== '') {
                                foreach (['variants', 'products'] as $_imgFolder) {
                                    if ($resolvedLocalFile !== '') break;
                                    $_raw = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $_imgFolder . DIRECTORY_SEPARATOR . $filename;
                                    if (is_file($_raw)) {
                                        $resolvedLocalFile = $_raw;
                                    }
                                }
                            }
                        }

                        // Convert resolved local file to base64 data URI for reliable Dompdf rendering
                        if ($resolvedLocalFile !== '') {
                            $_size = @filesize($resolvedLocalFile);
                            if ($_size !== false && $_size <= $maxImageBytes) {
                                $_imgContent = @file_get_contents($resolvedLocalFile);
                                if ($_imgContent !== false) {
                                    $_ext = strtolower(pathinfo($resolvedLocalFile, PATHINFO_EXTENSION));
                                    $_mimeMap = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'bmp' => 'image/bmp'];
                                    $_mime = $_mimeMap[$_ext] ?? 'image/jpeg';
                                    $imgPath = 'data:' . $_mime . ';base64,' . base64_encode($_imgContent);
                                }
                            }
                        }

                        $code = trim((string)($ln['product_code'] ?? $ln['code'] ?? ($ln['product_sku'] ?? ($ln['sku'] ?? ($ln['item_code'] ?? '')))));
                        $description = trim((string)($ln['product_name'] ?? ($ln['description'] ?? '')));
                        if ($description === '') {
                            $description = trim((string)($ln['name'] ?? ($ln['item_name'] ?? '')));
                        }
                        $variantAttrs = !empty($ln['variant_attrs']) && is_array($ln['variant_attrs']) ? $ln['variant_attrs'] : [];
                    ?>
                        <tr>
                            <td><?= esc($code) ?></td>
                            <?php if ($canRenderImages): ?>
                                <td class="image-cell">
                                    <?php if (!empty($imgPath)): ?>
                                        <img src="<?= esc($imgPath) ?>" width="40" height="40" class="js-product-hover-thumb" data-preview-src="<?= esc($imgPath) ?>">
                                    <?php else: ?>
                                        <span class="no-img">No Img</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td>
                                <?= esc($description) ?>
                                <?php if ($warehousePdf): ?>
                                    <?php
                                        $pickLocations = (array)($ln['pick_locations'] ?? []);
                                        $pickLocations = array_values(array_filter(array_map(static function ($loc) use ($normalizePickText) {
                                            return $normalizePickText((string)$loc);
                                        }, $pickLocations), static fn($v) => $v !== ''));
                                    ?>
                                    <div style="margin-top:3px; font-size:7.5px; color:#0f766e; line-height:1.45;">
                                        <strong>Pick Location:</strong>
                                        <?php if (!empty($pickLocations)): ?>
                                            <?= esc(implode(' | ', $pickLocations)) ?>
                                        <?php else: ?>
                                            <?= esc($normalizePickText((string)($ln['pick_location_text'] ?? 'Not in stock'))) ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($variantAttrs)): ?>
                                    <div style="margin-top:3px; font-size:7.5px; color:#64748b; line-height:1.5;">
                                        <?php foreach ($variantAttrs as $_attrK => $_attrV): ?>
                                            <span style="margin-right:8px;"><strong><?= esc($_attrK) ?>:</strong> <?= esc($_attrV) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="numeric"><?= number_format($qty, 2) ?></td>
                            <td class="numeric"><?= esc($fmtMoney($unitPrice)) ?></td>
                            <?php if ($hasAnyDiscount): ?>
                            <td class="numeric"><?= esc($fmtMoney($discAmt)) ?></td>
                            <?php endif; ?>
                            <?php if ($hasAnyTax): ?>
                            <td class="numeric"><?= esc($fmtMoney($taxAmt)) ?></td>
                            <?php endif; ?>
                            <td class="numeric"><?= esc($fmtMoney($lineTotal)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?= 4 + ($canRenderImages ? 1 : 0) + ($hasAnyDiscount ? 1 : 0) + ($hasAnyTax ? 1 : 0) ?>" class="numeric">No lines</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <!-- TOTALS -->
        <div class="totals-wrap">
            <table class="totals">
                <tr>
                    <td class="label">Subtotal</td>
                    <td class="value"><?= esc($fmtMoney($subtotal)) ?></td>
                </tr>
                <?php if ($lineDiscountTotal > 0.0005): ?>
                <tr>
                    <td class="label">Line Discounts</td>
                    <td class="value">-<?= esc($fmtMoney($lineDiscountTotal)) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($documentDiscountAmount > 0.0005): ?>
                <tr>
                    <td class="label">
                        Document Discount
                        <?php if ($documentDiscountType === 'percent'): ?>
                            (<?= number_format($documentDiscountValue, 1) ?>%)
                        <?php endif; ?>
                    </td>
                    <td class="value">-<?= esc($fmtMoney($documentDiscountAmount)) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($discountTotal > 0.0005): ?>
                <tr style="background: #f0fdf4; font-weight: 700;">
                    <td class="label" style="color: #166534; font-weight: 700;">Total Discount</td>
                    <td class="value" style="color: #166534; font-weight: 700;">-<?= esc($fmtMoney($discountTotal)) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($tax > 0): ?>
                <tr>
                    <td class="label">Tax</td>
                    <td class="value"><?= esc($fmtMoney($tax)) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($shipping > 0): ?>
                <tr class="shipping-row">
                    <td class="label">Shipping</td>
                    <td class="value"><?= esc($fmtMoney($shipping)) ?></td>
                </tr>
                <?php endif; ?>
                <tr class="total-row">
                    <td class="label" style="font-weight:700; color:#0f172a;"><?= esc($totalRowLabel) ?></td>
                    <td class="value" style="font-weight:700; color:#0f172a; font-size:13px;">
                        <?= esc($fmtMoney($total)) ?>
                    </td>
                </tr>
            </table>
        </div>

        <?php if ($hasAnyPayment && ($paymentStatus === 'paid' || $paymentStatus === 'partial')): ?>
        <div class="payment-summary-wrap">
            <div class="payment-summary">
                <h4>Payment Status</h4>
                <div class="payment-status-line">Status: <span class="payment-chip <?= $paymentStatus === 'paid' ? 'payment-chip-paid' : ($paymentStatus === 'partial' ? 'payment-chip-partial' : 'payment-chip-unpaid') ?>"><?= esc($paymentStatusLabel) ?></span></div>

                <table class="payment-metrics">
                    <tr>
                        <td>Invoice Total</td>
                        <td><?= esc($fmtMoney($paymentInvoiceTotal)) ?></td>
                    </tr>
                    <tr>
                        <td><?= $paymentStatus === 'partial' ? 'Amount Partially Paid' : 'Amount Paid' ?></td>
                        <td><?= esc($fmtMoney($paymentPaidTotal)) ?></td>
                    </tr>
                    <tr class="payment-balance-row <?= $paymentBalanceDue <= 0.005 ? 'payment-balance-row-settled' : '' ?>">
                        <td>Pending Balance</td>
                        <td><?= esc($fmtMoney($paymentBalanceDue)) ?></td>
                    </tr>
                    <?php if ($paymentPaidOn !== ''): ?>
                    <tr>
                        <td>Paid On</td>
                        <td><?= esc(date('d M Y', strtotime($paymentPaidOn))) ?></td>
                    </tr>
                    <?php endif; ?>
                </table>

                <?php if (!empty($paymentRows)): ?>
                    <div class="payment-ref-strip" style="padding-top:5px;">
                        <strong style="display:block;margin-bottom:3px;font-size:8.8px;text-transform:uppercase;letter-spacing:.04em;">Payment History</strong>
                        <table style="width:100%;border-collapse:collapse;">
                            <thead>
                                <tr style="background:#f1f5f9;">
                                    <th style="font-size:8px;font-weight:700;color:#475569;padding:2px 4px;text-align:left;border-bottom:1px solid #cbd5e1;">#</th>
                                    <th style="font-size:8px;font-weight:700;color:#475569;padding:2px 4px;text-align:left;border-bottom:1px solid #cbd5e1;">Date</th>
                                    <th style="font-size:8px;font-weight:700;color:#475569;padding:2px 4px;text-align:left;border-bottom:1px solid #cbd5e1;">Reference</th>
                                    <th style="font-size:8px;font-weight:700;color:#475569;padding:2px 4px;text-align:right;border-bottom:1px solid #cbd5e1;">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($paymentRows as $pi => $pay):
                                $payRef = trim((string)($pay['payment_reference'] ?? ''));
                                if ($payRef === '') {
                                    $payRef = 'PAY-' . str_pad((string)((int)($pay['payment_id'] ?? 0)), 5, '0', STR_PAD_LEFT);
                                }
                                $payDateRaw = trim((string)($pay['payment_date'] ?? ''));
                                $payDateFmt = ($payDateRaw !== '' && $payDateRaw !== '0000-00-00')
                                    ? date('d M Y', strtotime($payDateRaw)) : '—';
                                $payAmt = (float)($pay['allocated_amount'] ?? 0);
                            ?>
                                <tr style="<?= ($pi % 2 === 1) ? 'background:#f8fafc;' : '' ?>">
                                    <td style="font-size:8.4px;padding:2px 4px;color:#64748b;border-bottom:1px dashed #e2e8f0;"><?= $pi + 1 ?></td>
                                    <td style="font-size:8.4px;padding:2px 4px;color:#0f172a;border-bottom:1px dashed #e2e8f0;"><?= esc($payDateFmt) ?></td>
                                    <td style="font-size:8.4px;padding:2px 4px;color:#1e40af;font-weight:600;border-bottom:1px dashed #e2e8f0;"><?= esc($payRef) ?></td>
                                    <td style="font-size:8.4px;padding:2px 4px;text-align:right;color:#0f172a;font-weight:700;border-bottom:1px dashed #e2e8f0;"><?= esc($fmtMoney($payAmt)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($hasSchedule): ?>
        <div class="schedule-block">
            <h4>Payment Schedule<?= $scheduleTermName !== '' ? ' - ' . esc($scheduleTermName) : '' ?><?= $scheduleTermNote !== '' ? ' <span class="sched-note-inline">(' . esc($scheduleTermNote) . ')</span>' : '' ?></h4>
            <?php
            // Columns of zeros read as an error to a customer who has not paid
            // yet, so the paid/balance pair only appears once money has arrived.
            $scheduleHasPayment = $schedulePaid > 0.005;
            $scheduleStatus = static function (array $row): array {
                if ($row['status'] === 'paid') {
                    return ['Paid', '#15803d'];
                }
                if (!empty($row['is_overdue'])) {
                    return ['Overdue', '#b91c1c'];
                }
                if ($row['paid'] > 0.005) {
                    return ['Part paid', '#b45309'];
                }
                return ['Payable', '#475569'];
            };
            ?>
            <table class="schedule-table">
                <thead>
                    <tr>
                        <th style="width:4%;">#</th>
                        <th style="width:<?= $scheduleHasPayment ? '26%' : '34%' ?>;">Instalment</th>
                        <th class="num" style="width:7%;">%</th>
                        <th style="width:<?= $scheduleHasPayment ? '18%' : '25%' ?>;">Due</th>
                        <th class="num" style="width:14%;">Amount</th>
                        <?php if ($scheduleHasPayment): ?>
                            <th class="num" style="width:16%;">Received</th>
                            <th class="num" style="width:13%;">Balance</th>
                        <?php endif; ?>
                        <th style="width:<?= $scheduleHasPayment ? '12%' : '16%' ?>; text-align:right;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($scheduleRows as $sr): ?>
                        <?php
                        $srOverdue = !empty($sr['is_overdue']) && $sr['status'] !== 'paid';
                        [$srStatusLabel, $srStatusColor] = $scheduleStatus($sr);
                        $srPaidOn = !empty($sr['paid_on']) ? date('d M Y', strtotime((string)$sr['paid_on'])) : '';
                        ?>
                        <tr class="<?= $srOverdue ? 'sched-overdue' : '' ?>">
                            <td><?= (int)$sr['seq'] ?></td>
                            <td><?= esc($sr['label']) ?></td>
                            <td class="num"><?= esc(rtrim(rtrim(number_format((float)$sr['percentage'], 2), '0'), '.')) ?>%</td>
                            <td><?= esc($sr['due_label']) ?></td>
                            <td class="num"><?= esc($fmtMoney($sr['amount'])) ?></td>
                            <?php if ($scheduleHasPayment): ?>
                                <td class="num">
                                    <?php if ($sr['paid'] > 0.005): ?>
                                        <?= esc($fmtMoney($sr['paid'])) ?>
                                        <?php if ($srPaidOn !== ''): ?><div class="sched-paidon">paid <?= esc($srPaidOn) ?></div><?php endif; ?>
                                    <?php else: ?>-<?php endif; ?>
                                </td>
                                <td class="num"><?= esc($fmtMoney($sr['balance'])) ?></td>
                            <?php endif; ?>
                            <td class="sched-status" style="text-align:right; color:<?= $srStatusColor ?>;"><?= esc($srStatusLabel) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <table class="sched-totals">
                <tr>
                    <td><?= esc($documentTitle) ?> Total</td>
                    <td class="val"><?= esc($fmtMoney($scheduleTotal)) ?></td>
                </tr>
                <?php if ($scheduleHasPayment): ?>
                <tr>
                    <td>Amount Received</td>
                    <td class="val"><?= esc($fmtMoney($schedulePaid)) ?></td>
                </tr>
                <?php endif; ?>
                <tr class="now">
                    <td><?= $scheduleNowDue > 0.005 ? 'Now Payable' . ($scheduleNowLabel !== '' ? ' - ' . esc($scheduleNowLabel) : '') : 'Paid in Full' ?></td>
                    <td class="val"><?= esc($fmtMoney($scheduleNowDue > 0.005 ? $scheduleNowDue : 0)) ?></td>
                </tr>
                <?php if ($scheduleDue - $scheduleNowDue > 0.005): ?>
                <tr>
                    <td>Payable Later</td>
                    <td class="val"><?= esc($fmtMoney($scheduleDue - $scheduleNowDue)) ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($bankDetailsText !== '' || $invoiceTermsText !== ''): ?>
        <div style="height:<?= (int)$termsReserve ?>px;"></div>
        <div class="terms-block">
            <?php if ($bankDetailsText !== ''): ?>
            <table class="terms-layout" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="terms-cell bank-details-cell terms-cell-left">
                        <h4>Bank Details</h4>
                        <div class="terms-body"><?= $bankDetailsHtml ?></div>
                    </td>
                    <td class="bank-spacer"></td>
                </tr>
            </table>
            <?php endif; ?>
            <?php if ($invoiceTermsText !== ''): ?>
            <div class="terms-cell conditions-cell terms-cell-full terms-below<?= $bankDetailsText !== '' ? ' terms-below-spaced' : '' ?>">
                <h4>Terms &amp; Conditions</h4>
                <div class="terms-body"><?= $invoiceTermsHtml ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php if ($pdfShowFooter): ?>
        <div class="footer">
            <?php if ($footerText !== ''): ?>
                <div class="footer-inner"><?= esc($footerText) ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <script src="<?= base_url('assets/js/product-image-hover-preview.js') ?>?v=1"></script>
</body>
</html>
