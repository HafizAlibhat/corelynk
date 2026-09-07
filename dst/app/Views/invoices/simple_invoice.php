<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Invoice <?= esc($invoice['invoice_number'] ?? ('INV-' . ($invoice['id'] ?? ''))) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
.so-address-card {
    padding: 14px 16px;
    height: 100%;
}
.so-address-card .addr-title {
    font-size: 0.72rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #94a3b8;
    margin-bottom: 8px;
}
.so-address-card .addr-name { font-weight: 600; line-height: 1.35; }
.so-address-card .addr-lines { color: #cbd5e1; font-size: 0.88rem; line-height: 1.5; margin-top: 2px; }
.so-address-card .addr-grid {
    display: grid;
    grid-template-columns: 64px 1fr;
    gap: 2px 10px;
    margin-top: 8px;
    font-size: 0.85rem;
    align-items: baseline;
}
.so-address-card .addr-grid dt { color: #94a3b8; font-weight: 500; }
.so-address-card .addr-grid dd { margin: 0; color: #e2e8f0; overflow-wrap: anywhere; }
/* invoice_bank_details_html()/invoice_terms_html() carry inline print colours
   for the PDF; on this dark page they need screen values. */
.so-address-card .addr-lines td,
.so-address-card .addr-lines strong { color: #e2e8f0 !important; }
.so-address-card .addr-lines td:first-child { color: #94a3b8 !important; }
.so-address-card .addr-lines td { border-bottom-color: rgba(148, 163, 184, .18) !important; }
/* The app theme is dark regardless of the OS preference, so these are not
   behind prefers-color-scheme any more. */
.so-lines-table tbody tr:nth-child(odd) { background: #0d1726; }
.so-lines-table tbody tr:nth-child(even) { background: #101c30; }
.so-totals-box {
    background: #101827;
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
</style>
<style>
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

            <?php if (($mode ?? 'view') !== 'edit' && $statusKey !== 'cancelled'): ?>
                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#paymentTermsModal">Payment Terms</button>
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
            $orderedWeightUnit = $orderedWeightUnit ?? null;
            $fmtShipmentWeight = static function($kg) use ($orderedWeightUnit) {
                return \App\Helpers\WeightHelper::formatShipment((float)$kg, $orderedWeightUnit);
            };
            // Address in one block, contact details in the label/value grid, the
            // same structure the quotation and sales order pages use.
            $addrLines = [];
            if (!empty($customerAddress['line1'])) $addrLines[] = $customerAddress['line1'];
            if (!empty($customerAddress['line2'])) $addrLines[] = $customerAddress['line2'];
            $cityState = trim(($customerAddress['city_name'] ?? '') . ' ' . ($customerAddress['state_name'] ?? ''));
            if ($cityState !== '') $addrLines[] = $cityState;
            if (!empty($customerAddress['country_name'])) $addrLines[] = $customerAddress['country_name'];
            if (!empty($customerAddress['postal_code'])) $addrLines[] = $customerAddress['postal_code'];
            $customerName = trim((string)($customer['name'] ?? ($customer['company_name'] ?? '')));
            $customerContactRows = array_filter([
                'Phone'  => $customer['phone'] ?? '',
                'Mobile' => $customer['mobile'] ?? '',
                'Email'  => $customer['email'] ?? '',
            ]);
        ?>
        <div class="row g-3 mb-3">
            <div class="col-md-6" id="customer-block" style="display:none;">
                <div class="so-address-card mb-0 shadow-sm">
                    <div class="addr-title">Bill To</div>
                    <?php if ($customerName !== ''): ?>
                        <div class="addr-name"><?= esc($customerName) ?></div>
                    <?php endif; ?>
                    <?php if ($addrLines): ?>
                        <div class="addr-lines"><?= esc(implode(', ', $addrLines)) ?></div>
                    <?php endif; ?>
                    <?php if ($customerContactRows): ?>
                        <dl class="addr-grid mb-0">
                            <?php foreach ($customerContactRows as $label => $value): ?>
                                <dt><?= esc($label) ?></dt>
                                <dd><?= esc($value) ?></dd>
                            <?php endforeach; ?>
                        </dl>
                    <?php endif; ?>
                    <?php if ($customerName === '' && !$addrLines && !$customerContactRows): ?>
                        <div class="addr-lines">No address on file</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6" id="seller-block" style="display:none;">
                <?php
                    $sellerContactRows = array_filter([
                        'Phone' => $companyContact,
                        'Email' => $companyEmail,
                    ]);
                ?>
                <div class="so-address-card mb-0 shadow-sm">
                    <div class="addr-title">From</div>
                    <?php if (!empty($companyName)): ?>
                        <div class="addr-name"><?= esc($companyName) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($companyAddress)): ?>
                        <div class="addr-lines"><?= esc($companyAddress) ?></div>
                    <?php endif; ?>
                    <?php if ($sellerContactRows): ?>
                        <dl class="addr-grid mb-0">
                            <?php foreach ($sellerContactRows as $label => $value): ?>
                                <dt><?= esc($label) ?></dt>
                                <dd><?= esc($value) ?></dd>
                            <?php endforeach; ?>
                        </dl>
                    <?php endif; ?>
                    <?php if (empty($companyName) && empty($companyAddress) && !$sellerContactRows): ?>
                        <div class="addr-lines">No company details configured</div>
                    <?php endif; ?>
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
                    <div class="col-md-6">
                        <label class="form-label">Payment Terms</label>
                        <select name="payment_term_id" class="form-control">
                            <option value="">No payment terms (single due date)</option>
                            <?php foreach (($paymentTermOptions ?? []) as $term): ?>
                                <option value="<?= (int)$term['id'] ?>" <?= (int)($invoice['payment_term_id'] ?? 0) === (int)$term['id'] ? 'selected' : '' ?>>
                                    <?= esc($term['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Saving rebuilds the instalment schedule and the due date from the selected terms.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Customer PO Number <span class="text-muted">(optional)</span></label>
                        <input type="text" name="customer_po_number" class="form-control" maxlength="100" value="<?= esc($invoice['customer_po_number'] ?? '') ?>" placeholder="e.g. PO-2026-0148">
                        <div class="form-text">Printed on the invoice so the customer can match it to their purchase order.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Bank Details</label>
                        <textarea name="bank_details" class="form-control rich-editor" rows="4" placeholder="Leave blank to use the company default from Settings."><?= esc($invoice['bank_details'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Terms &amp; Conditions</label>
                        <textarea name="terms_conditions" class="form-control rich-editor" rows="4" placeholder="Leave blank to use the company default from Settings."><?= esc($invoice['terms_conditions'] ?? '') ?></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Save</button>
            </form>
            <hr>
        <?php else: ?>
            <div class="row mb-3">
                <div class="col-md-3"><strong>Issue Date:</strong> <?= esc($invoice['issue_date'] ?? '') ?></div>
                <div class="col-md-3"><strong>Due Date:</strong> <?= esc($invoice['due_date'] ?? '') ?></div>
                <div class="col-md-6">
                    <?php if (!empty($paymentSchedule['term']['name'])): ?>
                        <strong>Payment Terms:</strong> <?= esc($paymentSchedule['term']['name']) ?>
                    <?php endif; ?>
                    <?php if (!empty($invoice['customer_po_number'])): ?>
                        <br><strong>Customer PO #:</strong> <?= esc($invoice['customer_po_number']) ?>
                    <?php endif; ?>
                </div>
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
                        <div class="addr-title">Notes</div>
                        <div class="addr-lines"><?= nl2br(esc(trim((string)($invoice['notes'] ?? '')))) ?></div>
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

        <?php $schedule = $paymentSchedule ?? ['has_schedule' => false, 'rows' => []]; ?>
        <?php if (!empty($schedule['has_schedule'])): ?>
            <hr>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <?php
                    helper('invoice_terms');
                    $schedNote = invoice_short_note((string)($schedule['term']['description'] ?? ''));
                ?>
                <h5 class="mb-0" style="font-size:1rem;">
                    Payment Schedule<?= !empty($schedule['term']['name']) ? ' - ' . esc($schedule['term']['name']) : '' ?>
                    <?php if ($schedNote !== ''): ?>
                        <span class="text-muted fw-normal" style="font-size:.8rem;">(<?= esc($schedNote) ?>)</span>
                    <?php endif; ?>
                </h5>
            </div>
            <?php
                // Zero-filled Paid/Pending columns read as an error before the
                // first payment, so they only appear once money has arrived.
                $scheduleHasPayment = $schedule['paid'] > 0.005;
                $scheduleNowDue   = (float)($schedule['now_due'] ?? 0);
                $scheduleNowLabel = trim((string)($schedule['now_due_label'] ?? ''));
                $scheduleNowPct   = (float)($schedule['now_due_percentage'] ?? 0);
                if ($scheduleNowPct > 0) {
                    $scheduleNowLabel = trim($scheduleNowLabel . ' (' . rtrim(rtrim(number_format($scheduleNowPct, 2), '0'), '.') . '%)');
                }
            ?>
            <div class="table-responsive mb-2">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th style="width:4%;">#</th>
                            <th>Instalment</th>
                            <th class="text-end" style="width:8%;">%</th>
                            <th style="width:18%;">Due</th>
                            <th class="text-end" style="width:14%;">Amount</th>
                            <?php if ($scheduleHasPayment): ?>
                                <th class="text-end" style="width:14%;">Received</th>
                                <th class="text-end" style="width:14%;">Balance</th>
                            <?php endif; ?>
                            <th class="text-end" style="width:12%;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedule['rows'] as $row): ?>
                            <?php
                                $rowOverdue = $row['is_overdue'] && $row['status'] !== 'paid';
                                if ($row['status'] === 'paid') {
                                    $rowBadge = 'bg-success';
                                    $rowLabel = 'PAID';
                                } elseif ($rowOverdue) {
                                    $rowBadge = 'bg-danger';
                                    $rowLabel = 'OVERDUE';
                                } elseif ($row['paid'] > 0.005) {
                                    $rowBadge = 'bg-warning text-dark';
                                    $rowLabel = 'PART PAID';
                                } else {
                                    $rowBadge = 'bg-secondary';
                                    $rowLabel = 'PAYABLE';
                                }
                            ?>
                            <tr class="<?= $rowOverdue ? 'table-danger' : '' ?>">
                                <td><?= (int)$row['seq'] ?></td>
                                <td class="fw-semibold"><?= esc($row['label']) ?></td>
                                <td class="text-end"><?= esc(rtrim(rtrim(number_format($row['percentage'], 2), '0'), '.')) ?>%</td>
                                <td><?= esc($row['due_label']) ?></td>
                                <td class="text-end"><?= esc($fmtMoney($row['amount'])) ?></td>
                                <?php if ($scheduleHasPayment): ?>
                                    <td class="text-end text-success">
                                        <?php if ($row['paid'] > 0.005): ?>
                                            <?= esc($fmtMoney($row['paid'])) ?>
                                            <?php if (!empty($row['paid_on'])): ?>
                                                <div class="text-muted" style="font-size:.75rem;">paid <?= esc(date('d M Y', strtotime((string)$row['paid_on']))) ?></div>
                                            <?php endif; ?>
                                        <?php else: ?><span class="text-muted">&mdash;</span><?php endif; ?>
                                    </td>
                                    <td class="text-end fw-semibold"><?= esc($fmtMoney($row['balance'])) ?></td>
                                <?php endif; ?>
                                <td class="text-end"><span class="badge <?= $rowBadge ?>"><?= $rowLabel ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-end mb-3">
                <table style="min-width:280px;font-size:.9rem;">
                    <tr>
                        <td class="text-muted py-1">Invoice Total</td>
                        <td class="text-end fw-semibold py-1"><?= esc($fmtMoney($schedule['total'])) ?></td>
                    </tr>
                    <?php if ($scheduleHasPayment): ?>
                    <tr>
                        <td class="text-muted py-1">Amount Received</td>
                        <td class="text-end fw-semibold text-success py-1"><?= esc($fmtMoney($schedule['paid'])) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr style="border-top:1px solid #334155;">
                        <td class="fw-bold pt-2"><?= $scheduleNowDue > 0.005 ? 'Now Payable' . ($scheduleNowLabel !== '' ? ' - ' . esc($scheduleNowLabel) : '') : 'Paid in Full' ?></td>
                        <td class="text-end fw-bold pt-2 <?= $scheduleNowDue > 0.005 ? 'text-danger' : 'text-success' ?>" style="font-size:1.05rem;"><?= esc($fmtMoney($scheduleNowDue > 0.005 ? $scheduleNowDue : 0)) ?></td>
                    </tr>
                    <?php if ($schedule['due'] - $scheduleNowDue > 0.005): ?>
                    <tr>
                        <td class="text-muted py-1">Payable Later</td>
                        <td class="text-end fw-semibold py-1"><?= esc($fmtMoney($schedule['due'] - $scheduleNowDue)) ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        <?php endif; ?>

        <?php helper('invoice_terms'); ?>
        <?php if (!empty(trim((string)($bankDetailsText ?? ''))) || !empty(trim((string)($invoiceTermsText ?? '')))): ?>
            <hr>
            <div class="row g-3">
                <?php if (!empty(trim((string)($bankDetailsText ?? '')))): ?>
                    <div class="col-lg-6">
                        <div class="so-address-card shadow-sm h-100">
                            <div class="addr-title">Bank Details</div>
                            <div class="addr-lines"><?= invoice_bank_details_html((string)$bankDetailsText) ?></div>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (!empty(trim((string)($invoiceTermsText ?? '')))): ?>
                    <div class="col-lg-6">
                        <div class="so-address-card shadow-sm h-100">
                            <div class="addr-title">Terms &amp; Conditions</div>
                            <div class="addr-lines"><?= invoice_terms_html((string)$invoiceTermsText) ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

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

<?php if (($mode ?? 'view') !== 'edit' && $statusKey !== 'cancelled'): ?>
<div class="modal fade" id="paymentTermsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= site_url('customer-invoices/payment-terms/' . ($invoice['id'] ?? 0)) ?>">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title">Payment Terms &amp; Payment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Payment Terms</label>
                    <select name="payment_term_id" class="form-select">
                        <option value="">No payment terms (single due date)</option>
                        <?php foreach (($paymentTermOptions ?? []) as $term): ?>
                            <option value="<?= (int)$term['id'] ?>" <?= (int)($invoice['payment_term_id'] ?? 0) === (int)$term['id'] ? 'selected' : '' ?>>
                                <?= esc($term['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">
                        Rebuilds the instalment schedule and the due date. Invoice amounts, lines and any posted
                        journal entry are not affected. Manage the available terms under Settings &rarr; Payment Terms.
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Customer PO Number <span class="text-muted">(optional)</span></label>
                    <input type="text" name="customer_po_number" class="form-control" maxlength="100" value="<?= esc($invoice['customer_po_number'] ?? '') ?>" placeholder="e.g. PO-2026-0148">
                </div>
                <div class="mb-3">
                    <label class="form-label">Bank Details</label>
                    <textarea name="bank_details" class="form-control rich-editor" rows="5" placeholder="Leave blank to use the company default from Settings."><?= esc($invoice['bank_details'] ?? '') ?></textarea>
                </div>
                <div class="mb-0">
                    <label class="form-label">Terms &amp; Conditions</label>
                    <textarea name="terms_conditions" class="form-control rich-editor" rows="5" placeholder="Leave blank to use the company default from Settings."><?= esc($invoice['terms_conditions'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Payment Terms</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

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

<?= $this->section('js') ?>
<?= $this->include('partials/_rich_editor') ?>
<?= $this->endSection() ?>
