<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Cheque Voucher - <?= htmlspecialchars($cheque['cheque_number']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    @page { 
      margin: 20mm 15mm; 
      size: A4 portrait;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { 
      font-family: Arial, Helvetica, sans-serif; 
      font-size: 11pt;
      line-height: 1.5;
      color: #000;
    }
    .container { 
      width: 100%; 
      border: 4px double #000;
      padding: 15px;
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
      font-size: 18pt;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .account-text {
      font-size: 10pt;
      color: #333;
      margin-top: 4px;
    }
    .cheque-info {
      text-align: right;
      font-size: 11pt;
      line-height: 1.6;
    }
    .cheque-info strong {
      font-size: 15pt;
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
      padding: 15px;
      margin: 15px 0;
    }
    .field-row {
      margin-bottom: 12px;
      clear: both;
      min-height: 25px;
    }
    .field-label {
      font-weight: bold;
      font-size: 10pt;
      display: inline-block;
      width: 85px;
      vertical-align: top;
    }
    .field-value {
      border-bottom: 2px dotted #000;
      display: inline-block;
      padding: 4px 0;
      min-height: 24px;
    }
    .pay-to-line {
      width: calc(100% - 90px);
      font-size: 13pt;
      font-weight: bold;
    }
    .amount-figure {
      float: right;
      border: 3px solid #000;
      padding: 10px 15px;
      background: #fff;
      text-align: center;
      width: 160px;
      margin-left: 10px;
    }
    .amount-figure .label {
      font-size: 8pt;
      font-weight: bold;
    }
    .amount-figure .value {
      font-size: 14pt;
      font-weight: bold;
      margin-top: 2px;
    }
    .rupees-line {
      width: calc(100% - 85px);
      font-size: 10pt;
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
      font-size: 8pt;
      color: #666;
    }
    .receiver-line {
      border-bottom: 1px solid #666;
      height: 18px;
      margin-top: 2px;
    }
    
    /* Signatures */
    .signature-row {
      margin-top: 25px;
      width: 100%;
    }
    .sig-col {
      display: inline-block;
      width: 48%;
      text-align: center;
    }
    .sig-col:last-child {
      float: right;
    }
    .sig-line {
      border-top: 1px solid #000;
      width: 160px;
      margin: 0 auto 4px;
      padding-top: 4px;
      font-size: 9pt;
      font-weight: bold;
    }
    
    /* Notes */
    .notes {
      background: #fffef0;
      border: 1px dashed #c0a060;
      padding: 6px;
      margin: 10px 0;
      font-size: 9pt;
    }
    .notes-label {
      font-weight: bold;
      margin-bottom: 3px;
    }
  </style>
</head>
<body>
  <div class="container">
    <!-- Header -->
    <div class="voucher-header">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td width="65%" valign="top">
            <div class="bank-title"><?= htmlspecialchars($cheque['bank_name'] ?? 'ISLAMIC PARTNER - REGAL') ?></div>
            <?php if (!empty($cheque['account_number'])): ?>
              <div class="account-text">Account: <?= htmlspecialchars($cheque['account_number']) ?></div>
            <?php endif; ?>
          </td>
          <td width="35%" valign="top" style="text-align: right;">
            <div class="cheque-info">
              <strong>Cheque <?= htmlspecialchars($cheque['cheque_number']) ?></strong><br>
              <?= date('d M, Y', strtotime($cheque['cheque_date'])) ?><br>
              <span class="status"><?= htmlspecialchars(strtoupper($cheque['status'] ?? 'DRAFT')) ?></span>
            </div>
          </td>
        </tr>
      </table>
    </div>    </div>

    <!-- Cheque Body -->
    <div class="cheque-box">
      <div class="field-row">
        <span class="field-label">PAY TO:</span>
        <span class="field-value pay-to-line"><?= htmlspecialchars($cheque['vendor_name'] ?: ($cheque['payee_name'] ?? 'Bearer')) ?></span>
      </div>
      
      <div class="field-row" style="min-height: 50px;">
        <div class="amount-figure">
          <div class="label">AMOUNT</div>
          <div class="value">Rs. <?= htmlspecialchars($amount) ?></div>
        </div>
      </div>
      
      <div class="field-row">
        <span class="field-label">RUPEES:</span>
        <span class="field-value rupees-line"><?= htmlspecialchars($amountWords) ?> ONLY</span>
      </div>
      
      <div class="meta-info">
        <strong>Type:</strong> <?php 
          $deliveryLabels = ['ac_payee' => 'A/C Payee', 'bearer' => 'Bearer', 'self' => 'Self'];
          echo htmlspecialchars($deliveryLabels[$cheque['delivery_type']] ?? ucfirst($cheque['delivery_type']));
        ?> &nbsp;&nbsp;|&nbsp;&nbsp; <strong>Date:</strong> <?= date('d/m/Y', strtotime($cheque['cheque_date'])) ?>
      </div>
    </div>

    <!-- Expense Details -->
    <table class="detail-table">
      <thead>
        <tr>
          <th>ACCOUNT / DESCRIPTION</th>
          <th class="amount-col">AMOUNT</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($lines as $l): ?>
        <tr>
          <td>
            <strong><?= htmlspecialchars($l['account_name'] ?? 'N/A') ?></strong>
            <?php if (!empty($l['description'])): ?>
              <br><span class="description"><?= htmlspecialchars($l['description']) ?></span>
            <?php endif; ?>
          </td>
          <td class="amount-col"><?= number_format((float)($l['amount'] ?? 0), 2) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td style="text-align: right;">TOTAL</td>
          <td class="amount-col">Rs. <?= htmlspecialchars($amount) ?></td>
        </tr>
      </tfoot>
    </table>

    <!-- Notes -->
    <?php if (!empty($cheque['notes'])): ?>
    <div class="notes">
      <div class="notes-label">NOTES:</div>
      <?= nl2br(htmlspecialchars($cheque['notes'])) ?>
    </div>
    <?php endif; ?>

    <!-- Receiver Details -->
    <div class="receiver-box">
      <div class="receiver-title">RECEIVER'S ACKNOWLEDGMENT</div>
      <div class="receiver-row">
        <div class="receiver-col">
          <div class="receiver-label">Full Name:</div>
          <div class="receiver-line"></div>
        </div>
        <div class="receiver-col">
          <div class="receiver-label">CNIC Number:</div>
          <div class="receiver-line"></div>
        </div>
        <div class="receiver-col">
          <div class="receiver-label">Contact Number:</div>
          <div class="receiver-line"></div>
        </div>
      </div>
    </div>

    <!-- Signatures -->
    <div class="signature-row">
      <div class="sig-col">
        <div class="sig-line">Authorized Signatory</div>
      </div>
      <div class="sig-col">
        <div class="sig-line">Received By</div>
      </div>
    </div>
  </div>
</body>
</html>