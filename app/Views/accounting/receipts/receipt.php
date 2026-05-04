<!-- Finalized Cheque Receipt View (Unified Design) -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Journal Voucher</title>
    <style>
    /* Professional typography */
    body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; margin: 0; padding: 0; color: #222; }
    .receipt-container { border: 2px dashed #111; max-width: 700px; margin: 32px auto; background: #fff; border-radius: 6px; box-shadow: 0 2px 12px #0001; }
    .receipt-title-bar { background: #111; color: #fff; text-align: left; padding: 10px 16px; border-radius: 6px 6px 0 0; font-size: 1.25em; font-weight: 700; letter-spacing: 1.5px; display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .receipt-title-bar .title-text { white-space:nowrap; }
    .receipt-title-bar .title-actions { display:flex; gap:8px; align-items:center; }

    .header-row { display: flex; justify-content: space-between; align-items: flex-start; padding: 10px 24px 10px 24px; gap: 12px; }
    .header-left { width: 240px; min-width: 240px; }
    .logo-box { border: 0; border-radius: 0; padding: 0; background:#fff; height: 56px; display:flex; align-items:center; justify-content:flex-start; }
    .company-logo img { max-height: 46px; width: auto; object-fit: contain; display: block; }
    .contact-block { margin-top: 8px; font-size: 11.5px; color:#111; line-height: 1.3; }
    .contact-block .contact-line { white-space: nowrap; overflow:hidden; text-overflow: ellipsis; }

    .voucher-meta { text-align: right; font-size: 0.92em; color: #111; }
    .receipt-meta { text-align: right; font-size: 0.92em; color: #111; }
    .receipt-meta .meta-row { margin-bottom: 6px; }
    .receipt-meta .meta-label { color: #111; font-weight: bold; }
    .receipt-meta .meta-value { font-family: 'Courier New', Courier, monospace; font-weight: bold; color: #111; }

    .voucher-no-badge { display:inline-flex; align-items:center; gap:8px; padding: 7px 12px; border-radius: 10px; border: 2px solid #111; background: #fff7d6; font-weight: 800; }
    .voucher-no-badge .vn-label { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 12px; letter-spacing: .6px; text-transform: uppercase; }
    .voucher-no-badge .vn-value { font-family: 'Courier New', Courier, monospace; font-size: 14px; }
    .timestamp-row { padding: 0 24px; color: #b71c1c; font-weight: bold; font-size: 1em; text-align: right; margin-bottom: 8px; }
    .receipt-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 20px; padding: 0 20px 0 20px; }
    .receipt-grid .label { background: #111; color: #fff; font-weight:700; padding: 6px 10px; border-radius: 3px 3px 0 0; font-size: 0.95em; }
    .receipt-grid .value { border: 1px solid #ddd; border-top: none; padding: 6px 10px; font-family: 'Courier New', Courier, monospace; font-weight: normal; font-size: 0.9em; border-radius: 0 0 3px 3px; position: relative; }
    .receipt-grid .value::after { content: ""; display: block; border-bottom: 1px solid #bbb; margin-top: 4px; }
    .checkbox-group { display: flex; gap: 18px; align-items: center; padding: 8px 0; }
    .checkbox-group label { font-size: 1em; color: #222; display: inline-flex; align-items: center; gap: 8px; margin-right: 18px; }
    .checkbox-group input[type=checkbox] { transform: scale(1.05); margin-right: 4px; }
    .receipt-grid > div { margin-bottom: 12px; }
    .checkbox-group input[type=checkbox] { accent-color: #111; }
    /* moved up 20px from previous layout to reduce gap */
    .amount-row { display: inline-flex; align-items: center; justify-content: flex-end; background: #f7f7f7; border: 1px solid #ccc; border-radius: 4px; margin: -8px 20px 0 20px; padding: 6px 10px; font-size: 0.95em; font-family: 'Courier New', Courier, monospace; font-weight:700; color: #111; width: 340px; float: right; box-sizing: border-box; }
    .amount-row .amount-label { margin-right: 8px; color:#111; font-weight:700; }
    .amount-row .amount-value { background: transparent; border:none; padding:0; min-width:140px; text-align:right; }
    .amount-words-row { display: block; clear: both; margin: 12px 24px 0 24px; padding: 8px 12px; font-size: 0.92em; color: #111; width: calc(100% - 48px); min-height: 36px; font-family: 'Courier New', Courier, monospace; font-weight: normal; border: 1px solid #111; border-top: none; border-radius: 0 0 3px 3px; position: relative; }
    .amount-words-row::after { content: ""; display: block; border-bottom: 1px solid #bbb; margin-top: 4px; }
    .purpose-row { padding: 18px 24px 0 24px; }
    .purpose-label { background: #111; color: #fff; font-weight:700; padding: 6px 10px; border-radius: 3px 3px 0 0; font-size: 0.95em; }
    .purpose-value { border: 1px solid #ddd; border-top: none; padding: 6px 10px; font-family: 'Courier New', Courier, monospace; color: #222; font-weight: normal; font-size: 0.92em; border-radius: 0 0 3px 3px; position: relative; }
    .purpose-value::after { content: ""; display: block; border-bottom: 1px solid #bbb; margin-top: 4px; }
    .signatures-row { display: flex; justify-content: space-between; align-items: flex-end; padding: 48px 24px 32px 24px; }
    .signature-block { text-align: center; flex: 1; }
    .signature-line { border-bottom: 1.5px solid #888; width: 220px; margin: 0 auto 12px auto; height: 0; }
    .signature-label { font-size: 13px; color: #888; }
    @media print { .receipt-container { box-shadow: none; border: 2px dashed #111; } }
    /* Force A4 page size for printing */
    @page { size: A4 portrait; margin: 10mm; }
    /* Make container suitable for A4 print when printed, but keep flexible for html2pdf capture */
    .receipt-container { max-width: 800px; width: auto; }
    /* Accounting table wrapper and styles: plain table border (no red) */
    .accounting-table-wrapper { border: none; padding: 0; margin: 6px 24px 12px 24px; }
    .accounting-table { width:100%; border-collapse:collapse; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; border:1px solid #e6e6e6; }
    .accounting-table th { text-align:left; padding:10px 12px; border:1px solid #eee; background:#fafafa; font-size:13px; font-weight:700; color:#333; }
    .accounting-table td { padding:8px 10px; border:1px solid #eee; font-size:12px; color:#333; line-height:1.25; }
    .accounting-table td.account-name { font-size:12px; }
    .accounting-table tfoot td { border-top:2px solid #ddd; }
    .balanced-badge { color: #2e7d32; font-weight:700; }
    .print-controls { display:flex; gap:10px; justify-content:flex-end; padding:0; }
    .print-controls .action-btn,
    .print-controls a.action-btn {
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:8px;
        padding:8px 12px;
        border-radius:8px;
        font-weight:700;
        font-size:13px;
        letter-spacing:.2px;
        border:1px solid #3a3a3a;
        color:#fff;
        background: linear-gradient(180deg, #2b2b2b, #111);
        cursor:pointer;
        text-decoration:none;
        line-height:1;
        box-shadow: 0 1px 0 #0006;
    }
    .print-controls .action-btn:hover,
    .print-controls a.action-btn:hover { filter: brightness(1.05); text-decoration:none; }
    .print-controls .action-btn.secondary,
    .print-controls a.action-btn.secondary {
        background: linear-gradient(180deg, #6a6a6a, #404040);
        border-color:#5a5a5a;
    }
    @media print { .print-controls { display:none !important; } }
    /* Hide controls on PDF render too */
    .is-pdf .print-controls { display:none !important; }
    </style>
</head>
<body class="<?= empty($pdf) ? '' : 'is-pdf' ?>">
    <div class="receipt-container">
    <div class="receipt-title-bar">
        <div class="title-text"><?= 'JOURNAL VOUCHER' ?></div>

        <?php if (empty($pdf)): ?>
        <div class="title-actions">
            <div class="print-controls">
                <button id="btnPrint" type="button" class="action-btn"><span>Print</span></button>
                <button id="btnPdf" type="button" class="action-btn secondary"><span>Save as PDF</span></button>
                <a id="btnServerPdf" class="action-btn secondary" href="<?= site_url('accounting/journals/receipt/' . (int)($id ?? 0) . '/pdf') ?>">Download PDF (Server)</a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if (empty($pdf)): ?>
    <!-- html2pdf library for client-side PDF generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.2/html2pdf.bundle.min.js"></script>
    <?php endif; ?>

    <?php
    // Robust timestamp fallback
    $ts_raw = $created_at ?? $timestamp ?? $created_on ?? $time ?? null;
    $ts_display = $ts_raw ? date('d-m-Y h:i:s A', strtotime($ts_raw)) : '';
    $date_display = isset($date) ? date('d-m-Y', strtotime($date)) : ($ts_raw ? date('d-m-Y', strtotime($ts_raw)) : '');

    // Receipt number: prefer system-generated receipt_number; if missing show a safe AUTO- id
    $receipt_display = !empty($receipt_number ?? '') ? $receipt_number : ('AUTO-' . (isset($id) ? $id : uniqid()));

    // Party label/value: if a payer/customer exists show 'Received From', if vendor exists assume payment to vendor and show 'Paid To'
    $party_label = (!empty($payer ?? '') || !empty($customer_name ?? '')) ? 'Received From' : (!empty($vendor_name ?? '') ? 'Paid To' : 'Received From');
    $party_value = $payer ?? $customer_name ?? $vendor_name ?? $vendor ?? '';
    ?>

    <div class="header-row">
        <div class="header-left">
            <div class="logo-box">
                <?php if (!empty($company_logo ?? '')): ?>
                    <div class="company-logo">
                        <img src="<?= htmlspecialchars($company_logo) ?>" alt="Company Logo">
                    </div>
                <?php endif; ?>
            </div>

            <div class="contact-block">
                <?php
                    $contactLines = [];
                    if (!empty($company_address ?? '')) $contactLines[] = (string)$company_address;
                    if (!empty($company_phone ?? '')) $contactLines[] = 'Ph: ' . (string)$company_phone;
                    if (!empty($company_mobile ?? '')) $contactLines[] = 'Mob: ' . (string)$company_mobile;
                    if (!empty($company_email ?? '')) $contactLines[] = (string)$company_email;
                ?>
                <?php if (!empty($contactLines)): ?>
                    <?php foreach ($contactLines as $ln): ?>
                        <div class="contact-line"><?= htmlspecialchars($ln) ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div style="flex:1"></div>

        <div class="receipt-meta">
            <?php if ($ts_display): ?>
                <div class="meta-row"><strong style="color:#b71c1c">Time Stamp:</strong> <?= $ts_display ?></div>
            <?php endif; ?>
            <div class="meta-row"><span class="meta-label">Voucher Date:</span> <span class="meta-value"> <?= $date_display ?> </span></div>
            <div class="meta-row">
                <span class="voucher-no-badge">
                    <span class="vn-label">Voucher No</span>
                    <span class="vn-value"><?= htmlspecialchars($receipt_number ?? ($receipt_display ?? '')) ?></span>
                </span>
            </div>
        </div>
    </div>
    <!-- High-level transaction summary removed per request; show technical table only -->
    <div style="padding: 0 24px; margin-top:6px;">
    <div id="accountingDetails" style="display:block; padding: 0; margin-top:8px;">
    <div class="accounting-table-wrapper">
    <table class="accounting-table">
            <thead>
                <tr>
                    <th style="text-align:left; padding:6px 8px; border-bottom:1px solid #ddd;">Account</th>
                    <th style="text-align:left; padding:6px 8px; border-bottom:1px solid #ddd;">Description</th>
                    <th style="text-align:right; padding:6px 8px; border-bottom:1px solid #ddd;">Debit</th>
                    <th style="text-align:right; padding:6px 8px; border-bottom:1px solid #ddd;">Credit</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($lines) && is_array($lines)): ?>
                    <?php foreach ($lines as $ln): ?>
                        <tr>
                            <td class="account-name" style="padding:6px 8px; border-bottom:1px solid #f3f3f3;"><?php echo htmlspecialchars(($ln['account_code'] ?? '') . ' - ' . ($ln['account_name'] ?? '')); ?></td>
                            <td style="padding:6px 8px; border-bottom:1px solid #f3f3f3;"><?php echo htmlspecialchars($ln['description'] ?? ''); ?></td>
                            <td style="padding:6px 8px; text-align:right; border-bottom:1px solid #f3f3f3;"><?php echo number_format((float)($ln['debit'] ?? 0),2); ?></td>
                            <td style="padding:6px 8px; text-align:right; border-bottom:1px solid #f3f3f3;"><?php echo number_format((float)($ln['credit'] ?? 0),2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" style="text-align:right; padding:6px 8px; font-weight:600;">Totals:</td>
                    <td style="padding:6px 8px; text-align:right; font-weight:600;"><?= number_format((float)($sum_debit ?? 0),2) ?></td>
                    <td style="padding:6px 8px; text-align:right; font-weight:600;"><?= number_format((float)($sum_credit ?? 0),2) ?></td>
                </tr>
            </tfoot>
        </table>
        </div>
    </div>
    <div class="amount-row">
        <div class="amount-label">Amount:</div>
        <div class="amount-value">
            <?php
                $cur = strtoupper(trim($currency_code ?? 'PKR'));
                $amt = number_format((float)($amount ?? 0), 2);
                $out = '';
                if ($cur === 'USD') {
                    $out = '$' . $amt;
                } elseif ($cur === 'PKR') {
                    $out = 'PKR ' . $amt . ' /-';
                } elseif ($cur === 'EUR') {
                    $out = '€' . $amt;
                } elseif ($cur === 'GBP') {
                    $out = '£' . $amt;
                } else {
                    $out = htmlspecialchars($cur) . ' ' . $amt;
                }
                echo $out;
            ?>
        </div>
    </div>
    <div class="purpose-row" style="margin-top:48px;">
        <div class="purpose-label">Amount in words</div>
        <div class="purpose-value"> <?= htmlspecialchars($amount_words ?? $amount_in_words ?? '') ?>. </div>
    </div>
    <div class="purpose-row" style="margin-top:5px;">
        <div class="purpose-label">Narration / Purpose</div>
        <div class="purpose-value"> <?= htmlspecialchars($description ?? $payment_description ?? $for ?? $remarks ?? $reference ?? '') ?>. </div>
    </div>


    <div class="signatures-row">
        <div class="signature-block">
            <div class="signature-line"></div>
            <div class="signature-label">Received by</div>
        </div>
        
        <div class="signature-block">
            <div class="signature-line"></div>
            <div class="signature-label">Authorized by</div>
        </div>
    </div>
</div>
</body>
</html>
<?php if (empty($pdf)): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    const btnPrint = document.getElementById('btnPrint');
    const btnPdf = document.getElementById('btnPdf');
    if (btnPrint) btnPrint.addEventListener('click', function(){ window.print(); });
    if (btnPdf) btnPdf.addEventListener('click', function(){
        // If html2pdf is available, use it to generate a PDF with a filename.
        const controls = document.querySelector('.print-controls');
        const filename = <?= json_encode('JV-' . ($receipt_number ?? $receipt_display)) ?> + '.pdf';
        if (window.html2pdf) {
            try {
                if (controls) controls.style.display = 'none';
                const opt = {
                    margin:       10,
                    filename:     filename,
                    image:        { type: 'jpeg', quality: 0.98 },
                    html2canvas:  { scale: 2, useCORS: true, allowTaint: true },
                    jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
                };
                const element = document.querySelector('.receipt-container');
                // Use the live element (hide controls) so html2canvas can capture it correctly
                window.html2pdf().set(opt).from(element).save().then(function(){
                    if (controls) controls.style.display = 'flex';
                }).catch(function(err){
                    if (controls) controls.style.display = 'flex';
                    console.error('html2pdf error', err);
                    window.print();
                });
            } catch (e) {
                if (controls) controls.style.display = 'flex';
                window.print();
            }
        } else {
            // fallback
            window.print();
        }
    });
});
</script>
<?php endif; ?>

