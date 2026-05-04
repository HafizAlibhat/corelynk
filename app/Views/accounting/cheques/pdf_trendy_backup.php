<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Payment Voucher - <?= htmlspecialchars($cheque['cheque_number']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    @page { margin: 15mm; size: A4 portrait; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { 
      font-family: Arial, Helvetica, sans-serif; 
      font-size: 10pt;
      line-height: 1.4;
      color: #333;
      background: #fff;
    }
    
    /* Clean Corporate Container */
    .voucher-container { 
      border: 2px solid #1e3a8a;
    }
    
    /* Modern Container with Shadow Effect */
    .voucher-container { 
      border: 1px solid #e0e0e0;
      border-left: 6px solid #2563eb;
      background: #fff;
      position: relative;
    }
    
    /* Stylish Header with Gradient */
    .header-section {
      background: linear-gradient(135deg, #1e40af 0%, #3b82f6 50%, #60a5fa 100%);
      color: #fff;
      padding: 20px;
      border-bottom: 4px solid #1e3a8a;
      position: relative;
    }
    .header-section::after {
      content: '';
      position: absolute;
      bottom: -10px;
      left: 20px;
      right: 20px;
      height: 4px;
      background: linear-gradient(90deg, #fbbf24 0%, #f59e0b 50%, #fbbf24 100%);
    }
    .bank-name {
      font-size: 22pt;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 2px;
      margin-bottom: 5px;
      text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    }
    .account-number {
      font-size: 10pt;
      opacity: 0.95;
      font-weight: 500;
    }
    .cheque-details {
      position: absolute;
      top: 20px;
      right: 20px;
      text-align: right;
      background: rgba(255,255,255,0.15);
      padding: 12px 18px;
      border-radius: 8px;
      backdrop-filter: blur(10px);
    }
    .cheque-number-label {
      font-size: 16pt;
      font-weight: 700;
      display: block;
      margin-bottom: 4px;
    }
    .cheque-date {
      font-size: 10pt;
      margin-bottom: 8px;
      opacity: 0.9;
    }
    .status-badge {
      display: inline-block;
      border: 2px solid #fff;
      padding: 4px 14px;
      font-size: 9pt;
      font-weight: 700;
      background: #10b981;
      border-radius: 20px;
      letter-spacing: 1px;
    }
    
    /* Modern Cheque Section */
    .cheque-section {
      background: #f8fafc;
      border: 2px solid #e2e8f0;
      border-left: 5px solid #3b82f6;
      padding: 25px;
      margin: 20px;
      border-radius: 8px;
      position: relative;
    }
    .cheque-section::before {
      content: '';
      position: absolute;
      top: -2px;
      right: -2px;
      bottom: -2px;
      width: 5px;
      background: linear-gradient(180deg, #3b82f6 0%, #60a5fa 100%);
      border-radius: 0 8px 8px 0;
    }
    .cheque-field {
      margin-bottom: 18px;
      clear: both;
      overflow: hidden;
    }
    .field-label {
      font-weight: 700;
      font-size: 9pt;
      color: #475569;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      display: block;
      margin-bottom: 6px;
    }
    .field-content {
      border-bottom: 2px solid #cbd5e1;
      padding: 8px 0;
      font-size: 13pt;
      font-weight: 600;
      min-height: 32px;
      color: #1e293b;
    }
    .amount-box {
      float: right;
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      color: #fff;
      padding: 15px 25px;
      text-align: center;
      width: 200px;
      margin: -5px 0 20px 25px;
      border-radius: 10px;
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }
    .amount-box-label {
      font-size: 9pt;
      font-weight: 600;
      display: block;
      opacity: 0.9;
      letter-spacing: 1px;
    }
    .amount-box-value {
      font-size: 20pt;
      font-weight: 700;
      margin-top: 6px;
      letter-spacing: 0.5px;
    }
    .rupees-text {
      font-style: italic;
      text-transform: uppercase;
      font-size: 10pt;
      color: #334155;
      font-weight: 500;
    }
    .cheque-meta {
      font-size: 9pt;
      color: #64748b;
      padding: 12px;
      margin-top: 15px;
      background: #f1f5f9;
      border-radius: 6px;
      border-left: 3px solid #fbbf24;
    }
    
    /* Modern Expense Table */
    .expenses-section {
      margin: 20px;
    }
    .section-title {
      font-size: 12pt;
      font-weight: 700;
      margin-bottom: 12px;
      text-transform: uppercase;
      color: #1e40af;
      border-bottom: 3px solid #3b82f6;
      padding-bottom: 8px;
      letter-spacing: 1px;
    }
    .expense-table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      margin-bottom: 20px;
      border-radius: 8px;
      overflow: hidden;
    }
    .expense-table th {
      background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
      color: #fff;
      padding: 12px;
      font-weight: 600;
      text-align: left;
      font-size: 10pt;
      letter-spacing: 0.5px;
    }
    .expense-table th:first-child {
      border-radius: 8px 0 0 0;
    }
    .expense-table th:last-child {
      border-radius: 0 8px 0 0;
    }
    .expense-table td {
      border: 1px solid #e2e8f0;
      border-top: none;
      padding: 12px;
      background: #fff;
      font-size: 10pt;
    }
    .expense-table tr:nth-child(even) td {
      background: #f8fafc;
    }
    .expense-table .amount-column {
      text-align: right;
      width: 140px;
      font-weight: 700;
      color: #059669;
      font-family: 'Courier New', monospace;
      font-size: 11pt;
    }
    .expense-table tfoot td {
      background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
      border-top: 3px solid #3b82f6;
      font-weight: 700;
      font-size: 11pt;
      padding: 15px 12px;
      color: #1e293b;
    }
    .expense-table tfoot td:last-child {
      border-radius: 0 0 8px 0;
    }
    .expense-table tfoot td:first-child {
      border-radius: 0 0 0 8px;
    }
    .expense-description {
      color: #64748b;
      font-size: 9pt;
      font-style: italic;
      display: block;
      margin-top: 4px;
    }
    
    /* Stylish Receiver Section */
    .receiver-section {
      background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
      border: 2px solid #f59e0b;
      border-radius: 10px;
      padding: 18px;
      margin: 20px;
      position: relative;
    }
    .receiver-section::before {
      content: '✓';
      position: absolute;
      top: -15px;
      left: 20px;
      background: #f59e0b;
      color: #fff;
      width: 30px;
      height: 30px;
      border-radius: 50%;
      text-align: center;
      line-height: 30px;
      font-weight: bold;
      font-size: 16pt;
    }
    .receiver-title {
      font-weight: 700;
      font-size: 11pt;
      margin-bottom: 12px;
      text-transform: uppercase;
      color: #92400e;
      letter-spacing: 0.5px;
    }
    .receiver-fields {
      width: 100%;
    }
    .receiver-field {
      display: inline-block;
      width: 32%;
      margin-right: 1%;
      vertical-align: top;
      margin-bottom: 5px;
    }
    .receiver-field-label {
      font-size: 9pt;
      font-weight: 600;
      color: #78350f;
      display: block;
      margin-bottom: 5px;
    }
    .receiver-field-line {
      border-bottom: 2px solid #d97706;
      height: 26px;
      background: rgba(255,255,255,0.5);
    }
    
    /* Modern Signatures */
    .signatures-section {
      margin: 30px 20px 20px;
      padding-top: 20px;
      border-top: 2px dashed #cbd5e1;
    }
    .signature-column {
      display: inline-block;
      width: 48%;
      text-align: center;
      position: relative;
    }
    .signature-column:last-child {
      float: right;
    }
    .signature-line {
      border-top: 2px solid #1e40af;
      width: 200px;
      margin: 0 auto 10px;
      padding-top: 10px;
      font-size: 10pt;
      font-weight: 600;
      color: #1e293b;
      position: relative;
    }
    .signature-line::before {
      content: '✎';
      position: absolute;
      top: -20px;
      left: 50%;
      transform: translateX(-50%);
      color: #3b82f6;
      font-size: 18pt;
    }
    
    /* Elegant Notes */
    .notes-section {
      background: linear-gradient(135deg, #fef9c3 0%, #fef3c7 100%);
      border: 2px dashed #ca8a04;
      border-radius: 8px;
      padding: 15px;
      margin: 20px;
      position: relative;
    }
    .notes-section::before {
      content: '📝';
      position: absolute;
      top: 12px;
      left: 15px;
      font-size: 14pt;
    }
    .notes-title {
      font-weight: 700;
      font-size: 10pt;
      margin-bottom: 8px;
      margin-left: 35px;
      text-transform: uppercase;
      color: #78350f;
      letter-spacing: 0.5px;
    }
    .notes-content {
      font-size: 9pt;
      line-height: 1.6;
      color: #451a03;
      margin-left: 35px;
    }
  </style>
</head>
<body>
  <div class="voucher-container">
    
    <!-- Header Section -->
    <div class="header-section">
      <div class="bank-name"><?= htmlspecialchars($cheque['bank_name'] ?? 'ISLAMIC PARTNER - REGAL') ?></div>
      <?php if (!empty($cheque['account_number'])): ?>
        <div class="account-number"><strong>Account Number:</strong> <?= htmlspecialchars($cheque['account_number']) ?></div>
      <?php endif; ?>
      
      <div class="cheque-details">
        <span class="cheque-number-label">Cheque <?= htmlspecialchars($cheque['cheque_number']) ?></span>
        <div class="cheque-date"><?= date('d M, Y', strtotime($cheque['cheque_date'])) ?></div>
        <span class="status-badge"><?= htmlspecialchars(strtoupper($cheque['status'] ?? 'DRAFT')) ?></span>
      </div>
    </div>

    <!-- Cheque Body -->
    <div class="cheque-section">
      <div class="cheque-field">
        <div class="field-label">PAY TO:</div>
        <div class="field-content"><?= htmlspecialchars($cheque['vendor_name'] ?: ($cheque['payee_name'] ?? 'Bearer')) ?></div>
      </div>
      
      <div class="cheque-field">
        <div class="amount-box">
          <span class="amount-box-label">AMOUNT</span>
          <div class="amount-box-value">Rs. <?= htmlspecialchars($amount) ?></div>
        </div>
      </div>
      
      <div class="cheque-field" style="clear: both;">
        <div class="field-label">RUPEES:</div>
        <div class="field-content rupees-text"><?= htmlspecialchars($amountWords) ?> ONLY</div>
      </div>
      
      <div class="cheque-meta">
        <strong>Type:</strong> <?php 
          $deliveryTypes = ['ac_payee' => 'A/C Payee', 'bearer' => 'Bearer', 'self' => 'Self'];
          echo htmlspecialchars($deliveryTypes[$cheque['delivery_type']] ?? ucfirst($cheque['delivery_type']));
        ?> &nbsp;&nbsp;|&nbsp;&nbsp; <strong>Date:</strong> <?= date('d/m/Y', strtotime($cheque['cheque_date'])) ?>
      </div>
    </div>

    <!-- Expense Details -->
    <div class="expenses-section">
      <div class="section-title">Expense Details</div>
      <table class="expense-table">
        <thead>
          <tr>
            <th>ACCOUNT / DESCRIPTION</th>
            <th class="amount-column">AMOUNT</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lines as $line): ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars($line['account_name'] ?? 'N/A') ?></strong>
              <?php if (!empty($line['description'])): ?>
                <span class="expense-description"><?= htmlspecialchars($line['description']) ?></span>
              <?php endif; ?>
            </td>
            <td class="amount-column"><?= number_format((float)($line['amount'] ?? 0), 2) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td style="text-align: right;"><strong>TOTAL</strong></td>
            <td class="amount-column">Rs. <?= htmlspecialchars($amount) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- Notes (if any) -->
    <?php if (!empty($cheque['notes'])): ?>
    <div class="notes-section">
      <div class="notes-title">Notes:</div>
      <div class="notes-content"><?= nl2br(htmlspecialchars($cheque['notes'])) ?></div>
    </div>
    <?php endif; ?>

    <!-- Receiver Acknowledgment -->
    <div class="receiver-section">
      <div class="receiver-title">Receiver's Acknowledgment</div>
      <div class="receiver-fields">
        <div class="receiver-field">
          <span class="receiver-field-label">Full Name:</span>
          <div class="receiver-field-line"></div>
        </div>
        <div class="receiver-field">
          <span class="receiver-field-label">CNIC Number:</span>
          <div class="receiver-field-line"></div>
        </div>
        <div class="receiver-field">
          <span class="receiver-field-label">Contact Number:</span>
          <div class="receiver-field-line"></div>
        </div>
      </div>
    </div>

    <!-- Signatures -->
    <div class="signatures-section">
      <div class="signature-column">
        <div class="signature-line">Authorized Signatory</div>
      </div>
      <div class="signature-column">
        <div class="signature-line">Received By</div>
      </div>
    </div>

  </div>
</body>
</html>
