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
.inv-image-thumb {
    cursor: zoom-in;
    transition: transform .14s ease, box-shadow .14s ease;
}
.inv-image-thumb:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 18px rgba(2, 6, 23, 0.45);
}
.inv-image-hover-card {
    position: fixed;
    z-index: 3000;
    display: none;
    pointer-events: none;
    border-radius: 12px;
    border: 1px solid rgba(148, 163, 184, 0.35);
    background: rgba(15, 23, 42, 0.96);
    box-shadow: 0 24px 42px rgba(2, 6, 23, 0.55);
    padding: 6px;
    max-width: 340px;
    max-height: 280px;
}
.inv-image-hover-card img {
    display: block;
    max-width: 328px;
    max-height: 268px;
    border-radius: 8px;
    object-fit: contain;
}
.inv-section-row td {
    background: linear-gradient(90deg, #1e293b 0%, #0f172a 100%);
    color: #f8fafc;
    border-top: 1px solid #0f172a;
    border-bottom: 1px solid #0f172a;
    padding-top: 8px;
    padding-bottom: 8px;
}
.inv-section-row .inv-section-title {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    text-transform: uppercase;
}
.inv-section-row .inv-section-title::before {
    content: '';
    width: 10px;
    height: 10px;
    border-radius: 999px;
    background: #f59e0b;
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.16);
}
.inv-section-subtotal td {
    background: rgba(15, 23, 42, 0.72);
    color: #cbd5e1;
    padding-top: 5px;
    padding-bottom: 5px;
    font-size: 0.76rem;
    letter-spacing: 0.01em;
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
                <a class="btn btn-outline-dark" href="<?= site_url('customer-invoices/print/' . ($invoice['id'] ?? 0)) ?>" target="_blank">Print</a>
                <?php if (!$invoiceIsPaid): ?>
                    <a class="btn btn-outline-success" href="<?= site_url('accounting/customer-payments/pay?invoice_id=' . ($invoice['id'] ?? 0) . '&customer_id=' . ($invoice['customer_id'] ?? '') . '&amount=' . ($invoice['total_amount'] ?? $invoice['total'] ?? '')) ?>">Receive payment</a>
                <?php endif; ?>
                <?php endif; ?>

                <?php if ($hasDraftPayments): ?>
                    <a class="btn btn-outline-secondary" href="<?= site_url('accounting/customer-payments') . '#drafts-section' ?>">Draft Payments</a>
                <?php endif; ?>
                <button type="button" class="btn btn-outline-info" onclick="createCustomsInvoiceFromOriginal('VALUE_ONLY')">Create Customs (Value Only)</button>
                <button type="button" class="btn btn-outline-info" onclick="createCustomsInvoiceFromOriginal('FULL_REWRITE')">Create Customs (Full Rewrite)</button>
                <a class="btn btn-outline-primary" href="<?= site_url('accounting/customer-payments?invoice_id=' . ($invoice['id'] ?? 0)) ?>">Browse This Invoice Payments</a>
                <form id="createCustomsInvoiceForm" method="post" action="<?= site_url('customs-invoices/create-from-invoice/' . ($invoice['id'] ?? 0)) ?>" style="display:none;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="mode" id="customsModeInput" value="VALUE_ONLY">
                </form>

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
            $fmtShipmentWeight = static function($kg) {
                $kg = max(0.0, (float)$kg);
                if ($kg >= 1) {
                    return number_format($kg, 3) . ' kg';
                }
                return number_format($kg * 1000, 0) . ' g';
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

        <?php
            $invoiceSubtotal = (float)($invoice['subtotal'] ?? 0);
            $invoiceTax = (float)($invoice['tax_total'] ?? 0);
            $invoiceShipping = (float)($invoice['shipping_cost'] ?? 0);
            $invoiceDiscount = (float)($invoice['discount_total'] ?? ($invoice['discount'] ?? 0));
            $lineDiscountTotal = (float)($displayLineDiscount ?? 0);
            $documentDiscountAmount = (float)($displayDocumentDiscountAmount ?? 0);
            $documentDiscountType = strtolower((string)($displayDocumentDiscountType ?? $invoice['document_discount_type'] ?? 'fixed'));
            if (!in_array($documentDiscountType, ['percent', 'fixed'], true)) {
                $documentDiscountType = 'fixed';
            }
            $documentDiscountValue = (float)($displayDocumentDiscountValue ?? ($invoice['document_discount_value'] ?? 0));
            $discountExcludeShipping = ((int)($displayDiscountExcludeShipping ?? ($invoice['discount_exclude_shipping'] ?? 1)) === 1);
            $discountSourceLabel = 'No discount';
            if ($lineDiscountTotal > 0 && $documentDiscountAmount > 0) {
                $discountSourceLabel = 'Line + Document';
            } elseif ($lineDiscountTotal > 0) {
                $discountSourceLabel = 'Line only';
            } elseif ($documentDiscountAmount > 0) {
                $discountSourceLabel = 'Document only';
            }
            $effectiveTaxRate = 0.0;
            $taxableDoc = max(0.0, $invoiceSubtotal - $invoiceDiscount);
            if ($invoiceTax > 0 && $taxableDoc > 0) {
                $effectiveTaxRate = round(($invoiceTax / $taxableDoc) * 100.0, 2);
            }
            $invoiceProductCount = is_array($lines ?? null) ? count($lines) : 0;
        ?>

        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
            <h5 class="mb-0" style="font-size:1rem;">Lines</h5>
            <span class="badge bg-primary-subtle text-primary-emphasis">Products: <?= (int)$invoiceProductCount ?></span>
        </div>
        <div class="table-responsive mb-3" <?= !empty($canEditInvoice) ? 'data-doc-lines-root' : '' ?>>
            <table class="table table-sm align-middle so-lines-table" style="font-size:0.9rem;" <?= !empty($canEditInvoice) ? ('data-doc-line-type="customer_invoice" data-doc-id="' . esc((string)($invoice['public_id'] ?? ($invoice['id'] ?? ''))) . '"') : '' ?>>
                <thead>
                    <tr style="white-space:nowrap;">
                        <th style="width:4%" class="text-center">No.</th>
                        <th style="width:8%">Code</th>
                        <th style="width:5%">Image</th>
                        <th style="width:28%">Product / Description</th>
                        <th style="width:5%">Unit</th>
                        <th style="width:6%" class="text-end">Qty</th>
                        <th style="width:9%" class="text-end">Unit Price (<?= esc($symbol) ?>)</th>
                        <th style="width:10%" class="text-end">Disc Type / Value</th>
                        <th style="width:7%" class="text-end">Disc Amt</th>
                        <th style="width:6%" class="text-end">Tax %</th>
                        <th style="width:7%" class="text-end">Tax Amt</th>
                        <th style="width:9%" class="text-end">Line Total (<?= esc($symbol) ?>)</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                    echo view('partials/document_lines/rows', [
                        'docType' => 'customer_invoice',
                        'lines' => $lines ?? [],
                        'sectionSubtotals' => [],
                    ]);
                ?>
                </tbody>
            </table>
        </div>

        <?php
            $subtotal = $invoiceSubtotal;
            $tax = $invoiceTax;
            $shipping = $invoiceShipping;
            $discount = $invoiceDiscount;
            $total = (float)($invoice['total_amount'] ?? ($subtotal + $tax + $shipping - $discount));
            if ($discount <= 0 && $total > 0) {
                // derive discount if missing but totals are present
                $derived = ($subtotal + $tax + $shipping) - $total;
                if (abs($derived) > 0.0001) $discount = $derived;
            }
            $showDiscountBreakdown = ($lineDiscountTotal > 0) || ($documentDiscountAmount > 0) || ($documentDiscountValue > 0);
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
                        <?php if ($showDiscountBreakdown): ?>
                            <tr><td class="text-muted">Discount Source</td><td class="text-end"><?= esc($discountSourceLabel) ?></td></tr>
                            <?php if ($lineDiscountTotal > 0): ?>
                                <tr><td class="text-muted">Line Discount</td><td class="text-end text-danger">-<?= esc($fmtMoney($lineDiscountTotal)) ?></td></tr>
                            <?php endif; ?>
                            <?php if ($documentDiscountAmount > 0 || $documentDiscountValue > 0): ?>
                                <tr><td class="text-muted">Document Discount</td><td class="text-end text-danger">-<?= esc($fmtMoney($documentDiscountAmount)) ?></td></tr>
                                <tr>
                                    <td class="text-muted">Doc Disc Type</td>
                                    <td class="text-end">
                                        <?= esc($documentDiscountType === 'fixed' ? 'Fixed' : 'Percent') ?>
                                        <?= esc($documentDiscountType === 'fixed' ? $fmtMoney($documentDiscountValue) : (rtrim(rtrim(number_format($documentDiscountValue, 2), '0'), '.') . '%')) ?>
                                        <?= esc($discountExcludeShipping ? '(Excl Shipping)' : '(Incl Shipping)') ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php elseif ($discount > 0): ?>
                            <tr><td class="text-muted">Discount</td><td class="text-end text-danger">-<?= esc($fmtMoney($discount)) ?></td></tr>
                        <?php endif; ?>
                        <tr><td class="text-muted">Tax</td><td class="text-end"><?= esc($fmtMoney($tax)) ?></td></tr>
                        <tr><td class="text-muted">Shipping</td><td class="text-end"><?= esc($fmtMoney($shipping)) ?></td></tr>
                        <?php if (!empty($orderedWeightKg) && (float)$orderedWeightKg > 0): ?>
                            <tr><td class="text-muted">Shipment Weight</td><td class="text-end"><?= esc($fmtShipmentWeight($orderedWeightKg)) ?></td></tr>
                        <?php endif; ?>
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

    function initInvoiceImageHoverPreview(){
        if (window.__invoiceImagePreviewBound) return;
        window.__invoiceImagePreviewBound = true;

        var card = document.createElement('div');
        card.className = 'inv-image-hover-card';
        card.id = 'inv-image-hover-card';
        card.innerHTML = '<img alt="Preview" id="inv-image-hover-img">';
        document.body.appendChild(card);

        var cardImg = document.getElementById('inv-image-hover-img');
        var active = null;

        function positionCard(x, y){
            var gap = 14;
            var maxLeft = window.innerWidth - card.offsetWidth - 10;
            var maxTop = window.innerHeight - card.offsetHeight - 10;
            var left = Math.min(Math.max(10, x + gap), Math.max(10, maxLeft));
            var top = Math.min(Math.max(10, y + gap), Math.max(10, maxTop));
            card.style.left = left + 'px';
            card.style.top = top + 'px';
        }

        function hideCard(){
            card.style.display = 'none';
            active = null;
        }

        document.addEventListener('mouseover', function(e){
            var img = e.target && e.target.closest ? e.target.closest('img.inv-line-image') : null;
            if (!img) return;
            var src = img.getAttribute('data-preview-src') || img.getAttribute('src') || '';
            if (!src) return;
            active = img;
            cardImg.src = src;
            card.style.display = 'block';
            positionCard(e.clientX || 0, e.clientY || 0);
        });

        document.addEventListener('mousemove', function(e){
            if (!active || card.style.display === 'none') return;
            positionCard(e.clientX || 0, e.clientY || 0);
        });

        document.addEventListener('mouseout', function(e){
            if (!active) return;
            if (!e.target || e.target !== active) return;
            hideCard();
        });

        document.addEventListener('scroll', function(){ if (active) hideCard(); }, true);
        window.addEventListener('blur', hideCard);
    }

    function createCustomsInvoiceFromOriginal(mode){
        var form = document.getElementById('createCustomsInvoiceForm');
        var modeInput = document.getElementById('customsModeInput');
        if (!form || !modeInput) {
            alert('Customs invoice form is missing.');
            return;
        }
        modeInput.value = (mode === 'FULL_REWRITE') ? 'FULL_REWRITE' : 'VALUE_ONLY';
        form.submit();
    }
    window.createCustomsInvoiceFromOriginal = createCustomsInvoiceFromOriginal;

    initInvoiceImageHoverPreview();
})();
</script>

<?php if (!empty($canEditInvoice)): ?>
<script src="<?= base_url('assets/js/document_line_tools.js') ?>"></script>
<?php endif; ?>

<?= $this->include('partials/_document_log') ?>

<?= $this->endSection() ?>
