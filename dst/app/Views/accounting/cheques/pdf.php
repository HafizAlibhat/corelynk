<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Cheque Voucher - <?= htmlspecialchars($cheque['cheque_number']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    @page { 
      margin: 15mm 12mm; 
      size: A4 portrait;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { 
      font-family: Arial, Helvetica, sans-serif; 
      font-size: 10pt;
      line-height: 1.4;
      color: #000;
    }
    .container { 
      width: 100%; 
      max-width: 100%;
      border: 3px double #000;
      padding: 12px;
    }
    
    /* Header Section */
    .voucher-header {
      border-bottom: 3px solid #000;
      padding-bottom: 12px;
      margin-bottom: 15px;
      background: #f8f8f8;
      padding: 12px;
    }
    .header-row {
      width: 100%;
      margin-bottom: 4px;
    }
    .bank-title {
      font-size: 16pt;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }
    .account-text {
      font-size: 9pt;
      color: #333;
      margin-top: 3px;
    }
    .cheque-info {
      text-align: right;
      font-size: 10pt;
      line-height: 1.5;
    }
    .cheque-info strong {
      font-size: 13pt;
      display: block;
    }
    .status {
      display: inline-block;
      border: 2px solid #000;
      padding: 3px 10px;
      font-size: 9pt;
      font-weight: bold;
      margin-top: 4px;
      background: #fff;
    }
    
    /* Cheque Body */
    .cheque-box {
      background: #f9f9f9;
      border: 3px solid #000;
      padding: 12px;
      margin: 12px 0;
    }
    .field-row {
      margin-bottom: 10px;
      clear: both;
      min-height: 22px;
    }
    .field-label {
      font-weight: bold;
      font-size: 9pt;
      display: inline-block;
      width: 75px;
      vertical-align: top;
    }
    .field-value {
      border-bottom: 2px dotted #000;
      display: inline-block;
      padding: 3px 0;
      min-height: 20px;
    }
    .pay-to-line {
      width: calc(100% - 80px);
      font-size: 11pt;
      font-weight: bold;
    }
    .amount-figure {
      float: right;
      border: 3px solid #000;
      padding: 8px 12px;
      background: #fff;
      text-align: center;
      width: 140px;
      margin-left: 10px;
    }
    .amount-figure .label {
      font-size: 8pt;
      font-weight: bold;
    }
    .amount-figure .value {
      font-size: 14pt;
      font-weight: bold;
    }
    .rupees-line {
      width: calc(100% - 80px);
      font-size: 9pt;
      font-style: italic;
      text-transform: uppercase;
    }
    .meta-info {
      font-size: 8pt;
      color: #666;
      margin-top: 8px;
    }
    
    /* Table */
    .detail-table {
      width: 100%;
      border-collapse: collapse;
      margin: 10px 0;
      font-size: 9pt;
    }
    .detail-table th {
      background: #e0e0e0;
      border: 1px solid #999;
      padding: 5px;
      font-weight: bold;
      text-align: left;
    }
    .detail-table td {
      border: 1px solid #ccc;
      padding: 5px;
    }
    .detail-table .amount-col {
      text-align: right;
      width: 100px;
    }
    .detail-table tfoot td {
      background: #f0f0f0;
      border-top: 2px solid #000;
      font-weight: bold;
    }
    .description {
      color: #666;
      font-size: 8pt;
    }
    
    /* Receiver Box */
    .receiver-box {
      background: #fafafa;
      border: 1px solid #999;
      padding: 8px;
      margin: 10px 0;
    }
    .receiver-title {
      font-weight: bold;
      font-size: 9pt;
      margin-bottom: 6px;
    }
    .receiver-row {
      width: 100%;
      margin-bottom: 2px;
    }
    .receiver-col {
      display: inline-block;
      width: 32%;
      margin-right: 1%;
    }
    .receiver-label {

<!-- Finalized Cheque Receipt PDF Template (Unified Design) -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Cheque Receipt</title>
    <style>
    body { font-family: Arial, sans-serif; margin: 0; padding: 0; color: #111; }
    .receipt-container { border: 2px solid #4fc3f7; max-width: 700px; margin: 20px auto; background: #fff; box-shadow: 0 2px 16px #0001; }
    .top-bar, .bottom-bar { height: 16px; background: #4fc3f7; }
    .header { display: flex; justify-content: space-between; align-items: flex-start; padding: 18px 24px 8px 24px; background: #fff; }
    .header .logo { width: 120px; display: flex; flex-direction: column; align-items: flex-start; }
    .header .logo span:first-child { font-size: 18px; font-weight: bold; color: #34495e; }
    .header .logo span:last-child { font-size: 11px; color: #888; }
    .header .company-info { text-align: right; font-size: 13px; color: #111; }
    .header .company-info .name { font-weight: bold; color: #039be5; font-size: 1.1em; }
    .header .company-info .address { color: #888; font-size: 12px; }
    .header .contact-info { text-align: center; flex: 1; align-self: flex-start; margin-top: 2px; }
    .receipt-title { font-size: 2.1rem; font-weight: bold; color: #039be5; margin: 0 0 2px 0; letter-spacing: 1px; text-transform: uppercase; }
    .header .contact-info .phone { font-size: 13px; color: #34495e; margin-right: 10px; }
    .header .contact-info .email { font-size: 13px; color: #34495e; }
    .receipt-no-date { display: flex; justify-content: space-between; align-items: center; padding: 0 24px 8px 24px; font-size: 15px; }
    .section { padding: 0 24px; font-size: 1.05em; }
    .section .input-line, .section .amount-box-value { font-family: 'Courier New', Courier, monospace; font-size: 1.08em; letter-spacing: 0.5px; }
    .row { display: flex; align-items: center; margin-bottom: 10px; }
    .row label { min-width: 120px; font-weight: bold; color: #111; font-size: 1.05em; letter-spacing: 0.2px; }
    .row .input-line { flex: 1; border-bottom: 1px dotted #aaa; margin-left: 8px; }
    .row .input-box { border: 1px solid #aaa; padding: 2px 8px; min-width: 80px; background: #f9f9f9; margin-left: 8px; }
    .row .branch { margin-left: 16px; }
    .row .branch label { min-width: 60px; }
    .row .branch .input-line { min-width: 100px; }
    .footer { display: flex; justify-content: space-between; align-items: flex-end; padding: 24px; font-size: 14px; border-top: 1.5px dashed #4fc3f7; margin-top: 24px; }
    .footer .amount-box { border: 1.5px solid #4fc3f7; background: #e8f5e9; padding: 10px 32px; font-size: 1.3rem; font-weight: bold; color: #43a047; border-radius: 6px; box-shadow: 0 1px 4px #4fc3f733; letter-spacing: 1px; }
    .footer .signatures { display: flex; flex-direction: row; align-items: flex-end; gap: 48px; }
    .footer .signatures .sig-block { display: flex; flex-direction: column; align-items: center; }
    .footer .signatures .line { border-bottom: 1px dotted #aaa; width: 160px; margin-bottom: 2px; }
    .footer .signatures label { font-size: 12px; color: #888; }
    @media print { .receipt-container { box-shadow: none; } }
    </style>
</head>
<body>
<div class="receipt-container">
    <div class="top-bar"></div>
    <div class="header">
        <div class="logo">
            <span><?= htmlspecialchars($cheque['bank_name'] ?? 'LOGO HERE') ?></span>
            <span><?= htmlspecialchars($cheque['account_number'] ?? 'TAGLINE') ?></span>
        </div>
        <div class="contact-info">
            <div class="receipt-title" style="display:flex;align-items:center;gap:8px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="#039be5" viewBox="0 0 16 16" aria-hidden="true" role="img" style="vertical-align:middle;">
                    <title>Receipt</title>
                    <path d="M3 0h8a1 1 0 0 1 1 1v13l-1-1-1 1-1-1-1 1-1-1-1 1-1-1V1a1 1 0 0 1 1-1z"/>
                    <path fill-rule="evenodd" d="M4 3h6v1H4V3zm0 2h6v1H4V5zm0 2h4v1H4V7z"/>
                </svg>
                CHEQUE RECEIPT
            </div>
            <div>
                <span class="phone">&#9742; 0000-000000</span>
                <span class="email">Your Mail Here</span>
            </div>
        </div>
        <div class="company-info">
            <div class="name">Company Name Here</div>
            <div class="address">Your Business Address 0000<br>Main Street, Unit 000C FFL, 0000</div>
        </div>
    </div>
    <div class="receipt-no-date" style="margin-bottom:0;">
        <div><span style="font-weight:bold;color:#b71c1c;">Cheque No:</span> <span class="input-line" style="min-width:80px;display:inline-block;color:#b71c1c;font-weight:bold;"> <?= htmlspecialchars($cheque['cheque_number'] ?? '') ?> </span></div>
        <div><span style="font-weight:bold;color:#b71c1c;">Date:</span> <span class="input-line" style="min-width:80px;display:inline-block;color:#b71c1c;font-weight:bold;"> <?= isset($cheque['cheque_date']) ? date('d-m-Y', strtotime($cheque['cheque_date'])) : '' ?> </span></div>
    </div>
    <hr style="border:0;border-top:2px solid #4fc3f7;margin:0 24px 18px 24px;">
    <div class="section">
        <div class="row">
            <label>Vendor Name:</label>
            <span class="input-line"> <span class="amount-box-value"><?= htmlspecialchars($cheque['vendor_name'] ?? $cheque['payee_name'] ?? '') ?></span> </span>
        </div>
        <div class="row" style="align-items:center;">
            <label>Mode:</label>
            <span class="input-line" style="min-width:60px;max-width:90px;"> <span class="amount-box-value">Cheque</span> </span>
            <label style="margin-left:12px;">Cheque#:</label>
            <span class="input-line" style="min-width:60px;max-width:90px;"> <span class="amount-box-value"><?= htmlspecialchars($cheque['cheque_number'] ?? '') ?></span> </span>
            <label style="margin-left:12px;">Account:</label>
            <span class="input-line" style="min-width:60px;max-width:120px;"> <span class="amount-box-value"><?= htmlspecialchars($cheque['account_number'] ?? '') ?></span> </span>
        </div>
        <div class="row">
            <label>Amount (in words):</label>
            <span class="input-line"> <span class="amount-box-value"><?= htmlspecialchars($amount_in_words) ?></span> </span>
        </div>
        <div class="row">
            <label>Purpose / Description:</label>
            <span class="input-line"> <span class="amount-box-value"><?= htmlspecialchars($cheque['notes'] ?? '') ?></span> </span>
        </div>
        <div class="row" style="align-items:center;min-height:48px;">
            <span style="flex:0 0 auto;margin-left:auto;display:flex;align-items:center;height:100%;">
                <span class="amount-box" style="display:inline-block;margin:0 0 0 18px;min-width:160px;text-align:center;background:#e8f5e9;color:#222;font-weight:bold;font-size:1.1em;padding:6px 32px 6px 32px;border:2px dotted #43a047;border-radius:8px;letter-spacing:1px;vertical-align:middle;">
                    Amount: <?= number_format((float)($cheque['amount'] ?? 0), 2) ?>/-
                </span>
            </span>
        </div>
        <div class="row" style="align-items:center;margin-top:2px;">
            <label>Remarks:</label>
            <span class="input-line" style="flex:1;max-width:60%;"> <span class="amount-box-value"> <?= htmlspecialchars($cheque['notes'] ?? '') ?> </span> </span>
        </div>
    </div>
    <!-- Expense Details Table -->
    <div class="section">
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:10px;border-collapse:collapse;">
            <thead>
                <tr style="background:#e3f2fd;">
                    <th style="text-align:left;padding:6px 8px;font-size:13px;border-bottom:1.5px solid #4fc3f7;">ACCOUNT / DESCRIPTION</th>
                    <th style="text-align:right;padding:6px 8px;font-size:13px;border-bottom:1.5px solid #4fc3f7;">AMOUNT</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expense_lines as $l): ?>
                <tr>
                    <td style="padding:6px 8px;">
                        <strong><?= htmlspecialchars($l['account_name'] ?? 'N/A') ?></strong>
                        <?php if (!empty($l['description'])): ?>
                            <br><span style="color:#888;"> <?= htmlspecialchars($l['description']) ?> </span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;padding:6px 8px;"> <?= number_format((float)($l['amount'] ?? 0), 2) ?> </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td style="text-align:right;padding:6px 8px;font-weight:bold;">TOTAL</td>
                    <td style="text-align:right;padding:6px 8px;font-weight:bold;"> <?= number_format((float)($cheque['amount'] ?? 0), 2) ?> </td>
                </tr>
            </tfoot>
        </table>
    </div>
    <div class="footer" style="margin-top:24px;">
        <div style="width:100%;display:flex;justify-content:center;align-items:flex-end;">
            <div style="flex:1;display:flex;justify-content:space-evenly;align-items:flex-end;max-width:700px;margin:0 auto;gap:24px;">
                <div style="flex:1;text-align:center;">
                    <div style="border-bottom:1.5px solid #bbb;width:90%;margin:0 auto 4px auto;height:0;"></div>
                    <div style="font-size:13px;color:#444;">Received by</div>
                </div>
                <div style="flex:1;text-align:center;">
                    <div style="border-bottom:1.5px solid #bbb;width:90%;margin:0 auto 4px auto;height:0;"></div>
                    <div style="font-size:13px;color:#444;">Authorized By</div>
                </div>
            </div>
        </div>
    </div>
    <div class="bottom-bar"></div>
</div>
</body>
</html>