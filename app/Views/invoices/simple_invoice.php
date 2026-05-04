<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Invoice <?= esc($invoice['invoice_number'] ?? ('INV-' . ($invoice['id'] ?? ''))) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
.so-address-card {
    max-width: 520px;
    border-radius: 10px;
    padding: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #0f172a;
}
.so-address-card textarea {
    border-radius: 8px;
    resize: none;
    background: #ffffff;
    color: #0f172a;
    border: 1px solid #d8e0eb;
    font-size: 13px;
    min-height: 110px;
}
.so-lines-table tbody tr:nth-child(odd) { background: #ffffff; }
.so-lines-table tbody tr:nth-child(even) { background: #f8fafc; }
.so-totals-box {
    background: #f1f5f9;
    border-radius: 8px;
}
@media (prefers-color-scheme: dark) {
    .so-address-card {
        border: 1px solid #243b55;
        background: #0f172a;
        color: #e2e8f0;
        box-shadow: 0 6px 16px rgba(0,0,0,0.35);
    }
    .so-address-card textarea {
        background: #111827;
        color: #e2e8f0;
        border: 1px solid #1f2a44;
    }
    .so-lines-table tbody tr:nth-child(odd) { background: #0d1726; }
    .so-lines-table tbody tr:nth-child(even) { background: #101c30; }
    .so-totals-box {
        background: #101827;
    }
}
</style>
<style>
/* Force light address card on invoice pages to avoid dark-mode rendering */
.so-address-card {
    border: 1px solid #e2e8f0 !important;
    background: #f8fafc !important;
    color: #0f172a !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.04) !important;
}
.so-address-card textarea { background: #fff !important; color: #0f172a !important; border: 1px solid #d8e0eb !important; }

/* Invoice status ribbon */
.inv-ribbon-wrapper {
    position: absolute;
    top: -5px;
    right: -5px;
    width: 110px;
    height: 110px;
    overflow: hidden;
    z-index: 100;
    pointer-events: none;
    border-top-right-radius: var(--bs-card-border-radius);
}
.inv-ribbon {
    position: absolute;
    top: 22px;
    right: -25px;
    font-weight: 700;
    text-transform: uppercase;
    text-align: center;
    line-height: 25px;
    width: 150px;
    display: block;
    transform: rotate(45deg);
    color: white;
    text-shadow: 1px 1px 3px rgba(0,0,0,0.5);
    letter-spacing: 1px;
    font-size: 0.75rem;
    box-shadow: 0 4px 10px rgba(0,0,0,0.3), inset 0 1px 1px rgba(255,255,255,0.4);
}
.inv-ribbon-paid .inv-ribbon {
    background: linear-gradient(135deg, #22c55e 0%, #166534 100%);
    border-top: 2px solid #4ade80;
    border-bottom: 2px solid #064e3b;
}
.inv-ribbon-partial .inv-ribbon {
    background: linear-gradient(135deg, #f59e0b 0%, #92400e 100%);
    border-top: 2px solid #fbbf24;
    border-bottom: 2px solid #78350f;
}
@media (min-width: 768px) {
    .inv-ribbon-offset { margin-right: 60px !important; }
}
@media (max-width: 767px) {
    .inv-ribbon-wrapper { border-top-right-radius: 0; }
    .inv-ribbon-offset { width: 100%; justify-content: flex-start !important; margin-top: 15px; }
}
</style>
<div class="card" style="position: relative; overflow: hidden;">
    <?php
        // Determine payment ribbon state
        $invoiceTotal = (float)($invoice['total_amount'] ?? $invoice['total'] ?? 0);
        $totalPaidCalc = 0;
        if (!empty($invoicePayments)) {
            foreach ($invoicePayments as $_p) {
                $totalPaidCalc += (float)($_p['allocated_to_this_invoice'] ?? 0);
            }
        }
        $invoiceIsPaid = $invoiceTotal > 0 && ($totalPaidCalc >= $invoiceTotal * 0.9999);
        $invoiceIsPartial = !$invoiceIsPaid && $totalPaidCalc > 0;
        $showInvRibbon = $invoiceIsPaid || $invoiceIsPartial;
    ?>
    <?php if ($showInvRibbon): ?>
        <div class="inv-ribbon-wrapper <?= $invoiceIsPaid ? 'inv-ribbon-paid' : 'inv-ribbon-partial' ?>">
            <div class="inv-ribbon"><?= $invoiceIsPaid ? 'Paid' : 'Partial' ?></div>
        </div>
    <?php endif; ?>
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="mb-1">Invoice <?= esc($invoice['invoice_number'] ?? ('INV-' . ($invoice['id'] ?? ''))) ?></h4>
            <?php
                // Normalize status display: prefer explicit non-empty status, otherwise show 'DRAFT'
                $statusRaw = trim((string)($invoice['status'] ?? ''));
                $statusKey = $statusRaw !== '' ? strtolower($statusRaw) : 'draft';
                $statusLabel = strtoupper($statusKey);
                $badgeClass = $statusKey === 'posted' ? 'bg-success' : ($statusKey === 'confirmed' ? 'bg-info' : 'bg-secondary');
                // Workflow guards: only draft/confirmed can be progressed by Confirm/Post button.
                $canProgressInvoice = in_array($statusKey, ['draft', 'confirmed'], true);
                // Edit should be blocked once invoice is finalized or paid.
                $canEditInvoice = in_array($statusKey, ['draft', 'confirmed'], true);
                $hasDraftPayments = false;
                foreach (($invoicePayments ?? []) as $_pay) {
                    $pStatus = strtolower(trim((string)($_pay['status'] ?? '')));
                    if ($pStatus === 'draft') {
                        $hasDraftPayments = true;
                        break;
                    }
                }
            ?>
            <div><span class="badge <?= esc($badgeClass) ?> text-uppercase"><?= esc($statusLabel) ?></span></div>
        </div>
        <div class="d-flex gap-2 flex-wrap <?= $showInvRibbon ? 'inv-ribbon-offset' : '' ?>">
            <?php if ($canProgressInvoice): ?>
                <form method="post" action="<?= site_url('customer-invoices/post/' . ($invoice['id'] ?? 0)) ?>" class="m-0">
                    <?= csrf_field() ?>
                    <?php if ($statusKey === 'confirmed'): ?>
                        <button class="btn btn-primary" type="submit">Post</button>
                    <?php else: ?>
                        <button class="btn btn-success" type="submit">Confirm</button>
                    <?php endif; ?>
                </form>
            <?php endif; ?>

            <?php if ($canEditInvoice): ?>
                <a class="btn btn-outline-secondary" href="<?= site_url('customer-invoices/edit/' . ($invoice['id'] ?? 0)) ?>">Edit</a>
            <?php endif; ?>

                <?php // Show download/print when at least confirmed
                if ($statusKey !== 'draft'): ?>
                <a class="btn btn-outline-primary" href="<?= site_url('customer-invoices/pdf/' . ($invoice['id'] ?? 0)) ?>" target="_blank">Download PDF</a>
                <a class="btn btn-outline-dark" href="<?= site_url('customer-invoices/pdf/' . ($invoice['id'] ?? 0)) ?>" target="_blank">Print</a>
                <?php if (!$invoiceIsPaid): ?>
                    <a class="btn btn-outline-success" href="<?= site_url('accounting/customer-payments/pay?invoice_id=' . ($invoice['id'] ?? 0) . '&customer_id=' . ($invoice['customer_id'] ?? '') . '&amount=' . ($invoice['total_amount'] ?? $invoice['total'] ?? '')) ?>">Receive payment</a>
                <?php endif; ?>
                <?php endif; ?>

                <?php if ($hasDraftPayments): ?>
                    <a class="btn btn-outline-secondary" href="<?= site_url('accounting/customer-payments') . '#drafts-section' ?>">Draft Payments</a>
                <?php endif; ?>
                <a class="btn btn-outline-primary" href="<?= site_url('accounting/customer-payments?invoice_id=' . ($invoice['id'] ?? 0)) ?>">Browse This Invoice Payments</a>

            <button type="button" id="toggle-customer" class="btn btn-outline-secondary">Show Customer</button>
            <button type="button" id="toggle-seller" class="btn btn-outline-secondary">Show Seller</button>
        </div>
    </div>
    <div class="card-body">
        <?php
            $customerLabel = $customer['name'] ?? $customer['company_name'] ?? ($customer['customer_code'] ?? '');
            $companyName = $company['name'] ?? 'Company';
            $companyContact = $company['contact'] ?? '';
            $companyAddress = $company['address'] ?? '';
            $companyEmail = $company['email'] ?? '';
            $companyLogo = !empty($company['logo_path']) ? base_url($company['logo_path']) : null;
            $currencyCode = strtoupper(trim((string)($invoice['currency_code'] ?? $invoice['currency'] ?? $company['default_sales_currency'] ?? $company['base_currency'] ?? $company['secondary_currency'] ?? 'USD')));
            $currencySymbols = [
                'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'PKR' => '₨', 'INR' => '₹', 'JPY' => '¥', 'CNY' => '¥',
            ];
            $symbol = $currencySymbols[$currencyCode] ?? ($currencyCode !== '' ? $currencyCode : '$');
            $fmtMoney = function($val) use ($symbol) {
                return $symbol . number_format((float)$val, 2);
            };
            $addrLines = [];
            if (!empty($customerAddress['line1'])) $addrLines[] = $customerAddress['line1'];
            if (!empty($customerAddress['line2'])) $addrLines[] = $customerAddress['line2'];
            $cityState = trim(($customerAddress['city_name'] ?? '') . ' ' . ($customerAddress['state_name'] ?? ''));
            if ($cityState !== '') $addrLines[] = $cityState;
            if (!empty($customerAddress['postal_code'])) $addrLines[] = 'Postal: ' . $customerAddress['postal_code'];
            if (!empty($customer['phone'])) $addrLines[] = 'Phone: ' . $customer['phone'];
            if (!empty($customer['mobile'])) $addrLines[] = 'Mobile: ' . $customer['mobile'];
            if (!empty($customer['email'])) $addrLines[] = 'Email: ' . $customer['email'];
            $addrText = implode("\n", array_filter($addrLines));
        ?>
        <div class="row g-3 mb-3">
            <div class="col-md-6" id="customer-block" style="display:none;">
                <div class="so-address-card mb-0 shadow-sm">
                    <div class="fw-semibold mb-2">Address &amp; Contact</div>
                    <textarea class="form-control" rows="4" readonly>
<?= esc($addrText ?: 'N/A') ?>
                    </textarea>
                </div>
            </div>
            <div class="col-md-6" id="seller-block" style="display:none;">
                <?php
                    // Build seller address/contact textarea to match the same UI style
                    $sellerLines = [];
                    if (!empty($companyName)) $sellerLines[] = $companyName;
                    if (!empty($companyAddress)) $sellerLines[] = $companyAddress;
                    if (!empty($companyContact)) $sellerLines[] = 'Phone: ' . $companyContact;
                    if (!empty($companyEmail)) $sellerLines[] = 'Email: ' . $companyEmail;
                    $sellerText = implode("\n", array_filter($sellerLines));
                ?>
                <div class="so-address-card mb-0 shadow-sm">
                    <div class="fw-semibold mb-2">Address &amp; Contact</div>
                    <textarea class="form-control" rows="4" readonly>
<?= esc($sellerText ?: 'N/A') ?>
                    </textarea>
                </div>
            </div>
        </div>
        <?php if (($mode ?? 'view') === 'edit'): ?>
            <form method="post" action="<?= site_url('customer-invoices/update/' . ($invoice['id'] ?? 0)) ?>">
                <?= csrf_field() ?>
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Issue Date</label>
                        <input type="date" name="issue_date" class="form-control" value="<?= esc($invoice['issue_date'] ?? date('Y-m-d')) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Due Date</label>
                        <input type="date" name="due_date" class="form-control" value="<?= esc($invoice['due_date'] ?? date('Y-m-d')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"><?= esc($invoice['notes'] ?? '') ?></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Save</button>
            </form>
            <hr>
        <?php else: ?>
            <div class="row mb-3">
                <div class="col-md-3"><strong>Issue Date:</strong> <?= esc($invoice['issue_date'] ?? '') ?></div>
                <div class="col-md-3"><strong>Due Date:</strong> <?= esc($invoice['due_date'] ?? '') ?></div>
                <div class="col-md-6"></div>
            </div>
            <hr>
        <?php endif; ?>

        <h5 class="mb-2" style="font-size:1rem;">Lines</h5>
        <div class="table-responsive mb-3">
            <table class="table table-sm align-middle so-lines-table" style="font-size:0.9rem;">
                <thead>
                    <tr style="white-space:nowrap;">
                        <th style="width:8%">Code</th>
                        <th style="width:5%">Image</th>
                        <th style="width:28%">Product / Description</th>
                        <th style="width:5%">Unit</th>
                        <th style="width:6%" class="text-end">Qty</th>
                        <th style="width:9%" class="text-end">Unit Price (<?= esc($symbol) ?>)</th>
                        <th style="width:7%" class="text-end">Disc %</th>
                        <th style="width:7%" class="text-end">Disc Amt</th>
                        <th style="width:6%" class="text-end">Tax %</th>
                        <th style="width:7%" class="text-end">Tax Amt</th>
                        <th style="width:9%" class="text-end">Line Total (<?= esc($symbol) ?>)</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($lines)): ?>
                    <?php foreach ($lines as $ln): ?>
                        <?php
                            $img = $ln['product_image_url'] ?? base_url('assets/images/no-image.png');
                            $code = $ln['product_code'] ?? ($ln['product_id'] ?? '');
                            $qty = (float)($ln['quantity'] ?? 0);
                            $unitPrice = (float)($ln['unit_price'] ?? 0);
                            $discVal = isset($ln['discount_value']) ? (float)$ln['discount_value'] : null;
                            $discType = $ln['discount_type'] ?? 'percent';
                            $discAmtRaw = isset($ln['discount_amount']) ? (float)$ln['discount_amount'] : null;
                            $taxAmtRaw = isset($ln['tax_amount']) ? (float)$ln['tax_amount'] : null;
                            $taxRate = isset($ln['tax_rate']) ? (float)$ln['tax_rate'] : (isset($ln['tax']) ? (float)$ln['tax'] : 0.0);

                            // Prefer explicit amounts when provided; otherwise derive from rate/value.
                            $discAmt = ($discAmtRaw !== null && abs($discAmtRaw) > 0.00001) ? $discAmtRaw : 0.0;
                            if ((abs($discAmt) < 0.00001) && $discVal !== null) {
                                $discAmt = ($discType === 'percent') ? ($qty * $unitPrice * ($discVal / 100.0)) : $discVal;
                            }

                            $taxable = ($qty * $unitPrice) - $discAmt;
                            $taxAmt = ($taxAmtRaw !== null && abs($taxAmtRaw) > 0.00001) ? $taxAmtRaw : 0.0;
                            if ((abs($taxAmt) < 0.00001) && $taxRate > 0) {
                                $taxAmt = $taxable * ($taxRate / 100.0);
                            }

                            // If this invoice was created via proportional allocation, the stored tax_rate can be a derived value (e.g. 10.56)
                            // while the intended document tax is a flat rate (e.g. 12%). Prefer the document effective rate for display.
                            if (!empty($effectiveTaxRate) && $effectiveTaxRate > 0 && $taxAmt > 0) {
                                $taxRate = $effectiveTaxRate;
                            }

                            // IMPORTANT: For invoices we want the same meaning as Sales Order:
                            // Line Total = (qty * unit_price) - discount_amount + tax_amount.
                            // Some older records/flows may have line_total saved as base-only, so we compute it.
                            $lineTotal = (($qty * $unitPrice) - $discAmt + $taxAmt);

                            // If rates are present but amounts were saved as 0, infer amounts from line_total when possible.
                            // This helps old invoices where only totals existed.
                            // (Legacy fallback removed because we now compute lineTotal from amounts.)

                            $discDisplay = $discVal !== null ? rtrim(rtrim(number_format($discVal, 2), '0'), '.') . '%' : '';
                        ?>
                        <tr>
                            <td><?= esc($code) ?></td>
                            <td><img src="<?= esc($img) ?>" alt="" style="width:46px;height:36px;object-fit:cover;border-radius:4px" onerror="this.onerror=null;this.src='<?= base_url('assets/images/no-image.png') ?>'"></td>
                            <td>
                                <?php
                                    $lineName = $ln['product_name'] ?? '';
                                    $lineDesc = $ln['description'] ?? '';
                                    $lineText = trim($lineName) !== '' ? $lineName : $lineDesc;
                                    if (trim($lineText) === '') {
                                        $lineText = $code !== '' ? $code : '—';
                                    }
                                ?>
                                <div class="fw-semibold" style="line-height:1.2;">
                                    <?= esc($lineText) ?>
                                </div>
                                <?php if (trim($lineDesc) !== '' && $lineDesc !== $lineText): ?>
                                    <div class="text-muted" style="font-size:0.8rem; line-height:1.2;">
                                        <?= esc($lineDesc) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?= esc($ln['unit'] ?? 'pcs') ?></td>
                            <td class="text-end"><?= number_format($qty, 2) ?></td>
                            <td class="text-end"><?= esc($fmtMoney($unitPrice)) ?></td>
                            <td class="text-end"><?= esc($discDisplay) ?></td>
                            <td class="text-end"><?= number_format($discAmt, 2) ?></td>
                            <?php
                                $taxRateDisp = rtrim(rtrim(number_format((float)$taxRate, 2), '0'), '.');
                                if ($taxRateDisp !== '') $taxRateDisp .= '%';
                            ?>
                            <td class="text-end"><?= esc($taxRateDisp) ?></td>
                            <td class="text-end"><?= number_format($taxAmt, 2) ?></td>
                            <td class="text-end fw-semibold"><?= esc($fmtMoney($lineTotal)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="11" class="text-muted">No lines</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php
            $subtotal = (float)($invoice['subtotal'] ?? 0);
            $tax = (float)($invoice['tax_total'] ?? 0);
            $shipping = (float)($invoice['shipping_cost'] ?? 0);
            $discount = (float)($invoice['discount_total'] ?? ($invoice['discount'] ?? 0));
            $total = (float)($invoice['total_amount'] ?? ($subtotal + $tax + $shipping - $discount));
            if ($discount <= 0 && $total > 0) {
                // derive discount if missing but totals are present
                $derived = ($subtotal + $tax + $shipping) - $total;
                if (abs($derived) > 0.0001) $discount = $derived;
            }

            // Derive a single effective document tax rate for display when line tax_rate is unreliable
            // (e.g., invoices created from sales_order_lines without original quotation line rates).
            $effectiveTaxRate = 0.0;
            $taxableDoc = max(0.0, $subtotal - $discount);
            if ($tax > 0 && $taxableDoc > 0) {
                $effectiveTaxRate = round(($tax / $taxableDoc) * 100.0, 2);
            }
        ?>
        <div class="row g-3 align-items-start">
            <div class="col-lg-7">
                <?php if (!empty(trim((string)($invoice['notes'] ?? '')))): ?>
                    <div class="so-address-card shadow-sm">
                        <div class="fw-semibold mb-2">Notes</div>
                        <textarea class="form-control" rows="4" readonly>
<?= esc(trim((string)($invoice['notes'] ?? ''))) ?>
                        </textarea>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-lg-5 d-flex justify-content-end">
                <div style="min-width:320px;" class="table-responsive">
                    <table class="table table-sm table-borderless mb-0 so-totals-box">
                        <tr><td class="text-muted">Subtotal</td><td class="text-end"><?= esc($fmtMoney($subtotal)) ?></td></tr>
                        <tr><td class="text-muted">Discount</td><td class="text-end text-danger">-<?= esc($fmtMoney($discount)) ?></td></tr>
                        <tr><td class="text-muted">Tax</td><td class="text-end"><?= esc($fmtMoney($tax)) ?></td></tr>
                        <tr><td class="text-muted">Shipping</td><td class="text-end"><?= esc($fmtMoney($shipping)) ?></td></tr>
                        <tr><td class="fw-bold">Total</td><td class="text-end fw-bold fs-5"><?= esc($fmtMoney($total)) ?></td></tr>
                    </table>
                </div>
            </div>
        </div>

        <hr>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0" style="font-size:1rem;">Payments For This Invoice</h5>
            <a class="btn btn-sm btn-outline-primary" href="<?= site_url('accounting/customer-payments?invoice_id=' . ($invoice['id'] ?? 0)) ?>">Open Full Payment List</a>
        </div>
        <?php $invoicePayments = $invoicePayments ?? []; ?>
        <?php if (empty($invoicePayments)): ?>
            <div class="text-muted" style="font-size:.9rem;">No payments have been allocated to this invoice yet.</div>
        <?php else: ?>
            <div class="table-responsive mb-2">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Payment #</th>
                            <th>Date</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th class="text-end">Payment Amount</th>
                            <th class="text-end">Allocated Here</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoicePayments as $p): ?>
                            <?php $pStatus = strtolower((string)($p['status'] ?? 'draft')); ?>
                            <tr>
                                <td class="fw-semibold">#<?= (int)($p['id'] ?? 0) ?></td>
                                <td><?= esc($p['payment_date'] ?? '-') ?></td>
                                <td><?= esc(ucfirst(str_replace('_', ' ', (string)($p['payment_method'] ?? '-')))) ?></td>
                                <td>
                                    <span class="badge bg-<?= $pStatus === 'posted' ? 'success' : ($pStatus === 'void' ? 'danger' : 'warning') ?>">
                                        <?= esc(strtoupper($pStatus)) ?>
                                    </span>
                                </td>
                                <td class="text-end"><?= number_format((float)($p['amount'] ?? 0), 2) ?></td>
                                <td class="text-end fw-semibold"><?= number_format((float)($p['allocated_to_this_invoice'] ?? 0), 2) ?></td>
                                <td><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('accounting/customer-payments/view/' . (int)($p['id'] ?? 0)) ?>" target="_blank">View Payment</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function(){
    var customerBlock = document.getElementById('customer-block');
    var sellerBlock = document.getElementById('seller-block');
    var toggleCustomerBtn = document.getElementById('toggle-customer');
    var toggleSellerBtn = document.getElementById('toggle-seller');

    function toggle(section, btn) {
        if (!section || !btn) return;
        var hidden = section.style.display === 'none';
        section.style.display = hidden ? '' : 'none';
        btn.textContent = (hidden ? 'Hide' : 'Show') + ' ' + (section === customerBlock ? 'Customer' : 'Seller');
    }

    if (toggleCustomerBtn && customerBlock) {
        toggleCustomerBtn.addEventListener('click', function(){ toggle(customerBlock, toggleCustomerBtn); });
    }
    if (toggleSellerBtn && sellerBlock) {
        toggleSellerBtn.addEventListener('click', function(){ toggle(sellerBlock, toggleSellerBtn); });
    }
})();
</script>

<?= $this->include('partials/_document_log') ?>

<?= $this->endSection() ?>
