<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>
Quotation <?= esc($quote['quote_number']) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
    $quoteStatusRaw = trim((string)($quote['status'] ?? ''));
    if ($quoteStatusRaw === '') $quoteStatusRaw = 'draft';
    $quoteStatus = strtolower($quoteStatusRaw);
?>
<div class="card">
    <?php 
        $candidateDates = [
            $quote['issue_date'] ?? '',
            $quote['quote_date'] ?? '',
            $quote['created_at'] ?? '',
        ];
        $fmtDate = '';
        foreach ($candidateDates as $d) {
            if (!empty($d) && strtotime($d) !== false) {
                $ts = strtotime($d);
                $year = (int)date('Y', $ts);
                if ($year >= 1900) {
                    $fmtDate = date('d-m-Y', $ts);
                    break;
                }
            }
        }
        $customerLabel = '';
        if (!empty($customer['customer_code']) && !empty($customer['name'])) {
            $customerLabel = $customer['customer_code'] . ' - ' . $customer['name'];
        } elseif (!empty($customer['name'])) {
            $customerLabel = $customer['name'];
        } elseif (!empty($quote['customer_code']) && !empty($quote['customer_name'])) {
            $customerLabel = $quote['customer_code'] . ' - ' . $quote['customer_name'];
        } elseif (!empty($quote['customer_name'])) {
            $customerLabel = $quote['customer_name'];
        } else {
            $customerLabel = '';
        }
    ?>
    <div class="card-header section-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h3 class="section-title mb-0">Quotation <?= esc($quote['quote_number']) ?></h3>
            <div class="section-sub"></div>
        </div>
        <div class="d-flex flex-column align-items-end gap-1 ms-auto">
            <div class="d-flex align-items-center gap-2">
                <span class="badge <?= $quoteStatus === 'draft' ? 'bg-secondary' : 'bg-success' ?>" style="text-transform:uppercase; min-width:120px; text-align:center;">Status: <?= esc($quoteStatusRaw) ?></span>
                <a href="<?= site_url('quotations/pdf/' . (int)$quote['id']) ?>" class="btn btn-outline-danger btn-sm" title="Download Quotation PDF"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF</a>
                <a href="<?= site_url('document-studio?edit=quotation&id='.(int)$quote['id']) ?>" class="btn btn-outline-primary btn-sm" title="Edit in Document Studio"><i class="bi bi-easel me-1"></i>Edit in Studio</a>
                <a href="<?= site_url('quotations') ?>" class="btn btn-outline-secondary btn-sm">Back</a>
                <?php if (!empty($quote['converted_to_sales_order_id'])): ?>
                    <a href="<?= site_url('sales-orders/view/'.(int)$quote['converted_to_sales_order_id']) ?>" class="btn btn-success btn-sm">View Sales Order</a>
                <?php else: ?>
                    <a href="<?= site_url('sales-orders/create-from-quotation/'.$quote['id']) ?>" class="btn btn-primary btn-sm">Convert to Sales Order</a>
                <?php endif; ?>
            </div>
            <div class="text-muted" style="font-size:0.85rem;"></div>
        </div>
    </div>
    <div class="card-body">
        <style>
            .quote-lines-table th,
            .quote-lines-table td {
                white-space: nowrap;
                vertical-align: middle;
            }
            .quote-lines-table .line-desc {
                max-width: 420px;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .quote-lines-table .line-code {
                max-width: 120px;
                overflow: hidden;
                text-overflow: ellipsis;
            }
        </style>
        <?php
            $customer = $customer ?? null;
            $customerAddress = $customerAddress ?? null;
            $addrLines = [];
            if (!empty($customer['name'])) $addrLines[] = $customer['name'];
            if (!empty($customerAddress['line1'])) $addrLines[] = $customerAddress['line1'];
            if (!empty($customerAddress['line2'])) $addrLines[] = $customerAddress['line2'];
            $cityState = trim(($customerAddress['city_name'] ?? '') . ' ' . ($customerAddress['state_name'] ?? ''));
            if ($cityState !== '') $addrLines[] = $cityState;
            if (!empty($customerAddress['postal_code'])) $addrLines[] = 'Postal: ' . $customerAddress['postal_code'];
            if (!empty($customer['phone'])) $addrLines[] = 'Phone: ' . $customer['phone'];
            if (!empty($customer['mobile'])) $addrLines[] = 'Mobile: ' . $customer['mobile'];
            if (!empty($customer['email'])) $addrLines[] = 'Email: ' . $customer['email'];
            $addrText = implode("\n", array_filter($addrLines));
            $addrRows = max(2, min(6, count(array_filter($addrLines)) + 1));
        ?>
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
            <div class="so-address-card shadow-sm" style="min-width:450px;max-width:520px;border-radius:10px;">
                <div class="fw-semibold mb-2">Address &amp; Contact</div>
                <?php $addrRows = max(3, min(8, count(array_filter($addrLines)) + 1)); ?>
                <textarea class="form-control" rows="<?= $addrRows ?>" readonly style="border-radius:8px;min-width:450px;max-width:520px;overflow:hidden;resize:none;"><?= esc($addrText) ?></textarea>
            </div>
            <div class="text-muted" style="font-size:0.9rem;align-self:flex-start;">Date: <span style="font-weight:600;color:#e2e8f0;"><?= esc($fmtDate ?: '-') ?></span></div>
        </div>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0" style="font-size:1rem;">Lines</h5>
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="toggle-discount-tax">
                <label class="form-check-label" for="toggle-discount-tax" style="font-size:0.9rem;">Show Discount &amp; Tax</label>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle so-lines-table" style="font-size:0.9rem;">
                <thead>
                    <tr style="white-space:nowrap;">
                        <th style="width:8%">Code</th>
                        <th style="width:5%">Image</th>
                        <th style="width:28%">Product / Description</th>
                        <th style="width:5%">Unit</th>
                        <th style="width:6%" class="text-end">Qty</th>
                        <th style="width:9%" class="text-end">Unit Price</th>
                        <th style="width:7%" class="text-end col-disc">Disc %</th>
                        <th style="width:7%" class="text-end col-disc">Disc Amt</th>
                        <th style="width:6%" class="text-end col-tax">Tax %</th>
                        <th style="width:7%" class="text-end col-tax">Tax Amt</th>
                        <th style="width:9%" class="text-end">Line Total</th>
                        <th style="width:7%">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                    // Single source of truth per spec
                    // Read shipping only from the canonical 'shipping_amount' column
                    $shippingAmount = isset($quote['shipping_amount']) ? (float)$quote['shipping_amount'] : 0.0;
                    $hasQuote = !empty($quote['id']);
                    $totalWeight = $hasQuote ? (float)($quote['total_weight'] ?? 0) : 0;
                    $formatShipmentWeight = static function (float $kg): string {
                        $kg = max(0.0, $kg);
                        if ($kg >= 1) {
                            return number_format($kg, 3) . ' kg';
                        }
                        $grams = $kg * 1000;
                        return number_format($grams, 0) . ' g';
                    };
                    $displayShipmentWeight = $formatShipmentWeight($totalWeight);
                    $showDiscCols = false;
                    $showTaxCols = false;
                    foreach ($lines as $ln) {
                        if ((float)($ln['discount_value'] ?? 0) > 0 || (float)($ln['discount_amount'] ?? 0) > 0) $showDiscCols = true;
                        if ((float)($ln['tax_rate'] ?? 0) > 0 || (float)($ln['tax_amount'] ?? 0) > 0) $showTaxCols = true;
                    }
                ?>
                <?php foreach ($lines as $l): ?>
                    <?php
                        $img = $l['product_image_url'] ?? base_url('assets/images/no-image.png');
                        $code = $l['product_code'] ?? ($l['sku'] ?? '');
                        $unitWeight = $l['unit_weight'] ?? ($l['weight'] ?? 0);
                        $lineName = $l['product_name'] ?? ($l['name'] ?? '');
                        $lineDesc = $l['description'] ?? '';
                        $lineText = trim($lineName) !== '' ? $lineName : $lineDesc;
                        if (trim($lineText) === '') {
                            $lineText = $code !== '' ? $code : '—';
                        }
                    ?>
                    <tr data-line-id="<?= esc($l['id']) ?>" data-unit-weight="<?= esc($unitWeight) ?>">
                        <td class="line-code"><?= esc($code) ?></td>
                        <td><img src="<?= esc($img) ?>" alt="" style="width:46px;height:36px;object-fit:cover;border-radius:4px" onerror="this.onerror=null;this.src='<?= base_url('assets/images/no-image.png') ?>'"></td>
                        <td class="line-desc">
                            <div class="fw-semibold" style="line-height:1.2;">
                                <?= esc($lineText) ?>
                            </div>
                            <?php if (trim($lineDesc) !== '' && $lineDesc !== $lineText): ?>
                                <div class="text-muted" style="font-size:0.8rem; line-height:1.2;">
                                    <?= esc($lineDesc) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="line-unit"><?= esc($l['unit'] ?? 'pcs') ?></td>
                        <td class="text-end line-qty"><?= esc($l['quantity']) ?></td>
                        <td class="text-end line-unit-price"><?= number_format((float)($l['unit_price'] ?? 0),2) ?></td>
                        <td class="text-end line-disc-percent col-disc"><?= esc($l['discount_value'] ?? '') ?></td>
                        <td class="text-end line-disc-amt col-disc"><?= number_format((float)($l['discount_amount'] ?? 0),2) ?></td>
                        <td class="text-end line-tax-percent col-tax"><?= esc($l['tax_rate'] ?? '') ?></td>
                        <td class="text-end line-tax-amt col-tax"><?= number_format((float)($l['tax_amount'] ?? 0),2) ?></td>
                        <td class="text-end line-total"><?= number_format((float)($l['line_total'] ?? 0),2) ?></td>
                        <td>
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-sm btn-outline-secondary btn-edit-line">Edit</button>
                                <button type="button" class="btn btn-sm btn-success btn-save-line" style="display:none">Save</button>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-cancel-line" style="display:none">Cancel</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
            <div class="mt-3 d-flex justify-content-end">
            <div style="min-width:260px">
                <table class="table table-sm table-borderless mb-0">
                    <tbody>
                        <tr>
                            <td class="text-muted">Subtotal</td>
                            <td class="text-end" id="view-subtotal"><?= number_format((float)($quote['subtotal'] ?? 0),2) ?></td>
                        </tr>
                        <?php if (!empty($quote['discount']) && (float)$quote['discount'] > 0): ?>
                        <tr id="row-discount">
                            <td class="text-muted">Discount</td>
                            <td class="text-end text-danger" id="view-discount">-<?= number_format((float)($quote['discount'] ?? 0),2) ?></td>
                        </tr>
                        <?php else: ?>
                        <tr style="display:none"><td></td><td></td></tr>
                        <?php endif; ?>

                        <?php if (!empty($quote['tax']) && (float)$quote['tax'] > 0): ?>
                        <tr id="row-tax">
                            <td class="text-muted">Tax</td>
                            <td class="text-end" id="view-tax"><?= number_format((float)($quote['tax'] ?? 0),2) ?></td>
                        </tr>
                        <?php else: ?>
                        <tr style="display:none"><td></td><td></td></tr>
                        <?php endif; ?>

                        <?php if ($hasQuote): ?>
                        <tr>
                            <td class="text-muted">Shipment Weight</td>
                            <td class="text-end" id="view-weight" data-weight-mode="calculated" title="Calculated from product unit weight x quantity">
                                Shipment Weight: <?= esc($displayShipmentWeight) ?> (calculated)
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td class="text-muted align-middle">Shipping</td>
                            <td class="text-end" id="view-shipping">
                                <span id="view-shipping-amount" class="me-2"><?= number_format((float)$shippingAmount,2) ?></span>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="btn-edit-shipping">Edit</button>
                            </td>
                        </tr>
                        <tr class="border-top">
                            <td class="fw-bold">Total</td>
                            <td class="text-end fw-bold" id="view-total"><?= number_format((float)($quote['total'] ?? 0),2) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

    <script src="<?= base_url('assets/js/quotation_view_inline.js') ?>"></script>
    <script>
    (function(){
        var quoteId = <?= (int)($quote['id'] ?? 0) ?>;
        var quoteStatus = '<?= $quoteStatus ?>';
        window.quoteStatus = quoteStatus;

        var showDiscCols = <?= $showDiscCols ? 'true' : 'false' ?>;
        var showTaxCols = <?= $showTaxCols ? 'true' : 'false' ?>;
        var toggleEl = document.getElementById('toggle-discount-tax');
        function applyDiscTaxVisibility(show) {
            var discCols = document.querySelectorAll('.col-disc');
            var taxCols = document.querySelectorAll('.col-tax');
            discCols.forEach(function(el){ el.style.display = show ? '' : 'none'; });
            taxCols.forEach(function(el){ el.style.display = show ? '' : 'none'; });
        }
        if (toggleEl) {
            toggleEl.checked = (showDiscCols || showTaxCols);
            applyDiscTaxVisibility(toggleEl.checked);
            toggleEl.addEventListener('change', function(){ applyDiscTaxVisibility(this.checked); });
        } else {
            applyDiscTaxVisibility(showDiscCols || showTaxCols);
        }

        function updateTotalsFromResponse(totals){
            if (!totals) return;
            function formatShipmentWeight(kg){
                var val = parseFloat(kg || 0) || 0;
                if (val >= 1) return val.toFixed(3) + ' kg';
                return Math.round(val * 1000) + ' g';
            }
            var elSub = document.getElementById('view-subtotal');
            if (elSub && totals.subtotal != null) elSub.textContent = parseFloat(totals.subtotal).toFixed(2);
            var rowDisc = document.getElementById('row-discount');
            if (rowDisc && totals.discount != null) {
                if (parseFloat(totals.discount) > 0) {
                    rowDisc.style.display = '';
                    var elDisc = document.getElementById('view-discount');
                    if (elDisc) elDisc.textContent = '-' + parseFloat(totals.discount).toFixed(2);
                } else {
                    rowDisc.style.display = 'none';
                }
            }
            var rowTax = document.getElementById('row-tax');
            if (rowTax && totals.tax != null) {
                if (parseFloat(totals.tax) > 0) {
                    rowTax.style.display = '';
                    var elTax = document.getElementById('view-tax');
                    if (elTax) elTax.textContent = parseFloat(totals.tax).toFixed(2);
                } else {
                    rowTax.style.display = 'none';
                }
            }
            var elTotal = document.getElementById('view-total');
            if (elTotal && totals.total != null) elTotal.textContent = parseFloat(totals.total).toFixed(2);
            var shipText = document.getElementById('view-shipping-amount');
            if (shipText && totals.shipping_amount != null) shipText.textContent = parseFloat(totals.shipping_amount).toFixed(2);
            var weightText = document.getElementById('view-weight');
            if (weightText && totals.total_weight != null) {
                weightText.textContent = 'Shipment Weight: ' + formatShipmentWeight(totals.total_weight) + ' (calculated)';
            }
        }

        function openShippingModal(current){
            var modalEl = document.getElementById('modal-shipping');
            if (!modalEl) return;
            var input = document.getElementById('modal-shipping-input');
            if (input) input.value = (parseFloat(current||0)||0).toFixed(2);
            var modal = null;
            if (window.bootstrap && window.bootstrap.Modal) {
                modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();
            } else {
                // fallback minimal show
                modalEl.style.display = 'block';
                modalEl.classList.add('show');
            }
        }

        function closeShippingModal(){
            var modalEl = document.getElementById('modal-shipping');
            if (!modalEl) return;
            if (window.bootstrap && window.bootstrap.Modal) {
                var modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.hide();
            } else {
                modalEl.style.display = 'none';
                modalEl.classList.remove('show');
            }
        }

        function saveShipping(val){
            // Build a stable base URL that works when hosted under /corelynk
            var appBase = window.location.pathname.split('/quotations/')[0] || '';
            var url = window.location.origin + appBase + '/quotations/update-shipping/' + quoteId;
            return fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: JSON.stringify({ shipping_amount: val })
            }).then(async function(r){
                var ct = r.headers.get('content-type')||'';
                var txt = await r.text();
                if (ct.indexOf('application/json')!==-1) {
                    try { return JSON.parse(txt); } catch(e) { return { success:false, error:'Invalid JSON', raw:txt }; }
                }
                // fallback attempt parse
                try { return JSON.parse(txt); } catch(e) { return { success:false, error:'Non-JSON response', raw:txt }; }
            });
        }

        var editBtn = document.getElementById('btn-edit-shipping');
        var shipText = document.getElementById('view-shipping-amount');
        if (quoteId && editBtn && shipText) {
            editBtn.addEventListener('click', function(){
                openShippingModal(shipText.textContent);
            });
        }

        var btnSaveShip = document.getElementById('modal-shipping-save');
        var btnRemoveShip = document.getElementById('modal-shipping-remove');
        if (quoteId && btnSaveShip) {
            btnSaveShip.addEventListener('click', function(){
                var input = document.getElementById('modal-shipping-input');
                var val = input ? (parseFloat(input.value||0)||0) : 0;
                saveShipping(val).then(function(json){
                    if (!json || !json.success) { alert('Save failed: ' + (json && json.error ? json.error : 'unknown')); return; }
                    updateTotalsFromResponse(json.totals);
                    closeShippingModal();
                }).catch(function(e){ alert('Save failed: ' + e.toString()); });
            });
        }
        if (quoteId && btnRemoveShip) {
            btnRemoveShip.addEventListener('click', function(){
                saveShipping(0).then(function(json){
                    if (!json || !json.success) { alert('Remove failed: ' + (json && json.error ? json.error : 'unknown')); return; }
                    updateTotalsFromResponse(json.totals);
                    closeShippingModal();
                }).catch(function(e){ alert('Remove failed: ' + e.toString()); });
            });
        }

    })();
    </script>

<!-- Shipping modal (simple amount only) -->
<div class="modal fade" id="modal-shipping" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Shipping</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="form-label" for="modal-shipping-input">Shipping Amount</label>
                <input type="number" step="0.01" min="0" class="form-control" id="modal-shipping-input" value="<?= number_format((float)$shippingAmount,2,'.','') ?>">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-danger" id="modal-shipping-remove">Remove</button>
                <button type="button" class="btn btn-primary" id="modal-shipping-save">Save</button>
            </div>
        </div>
    </div>
</div>

<?= $this->include('partials/_document_log') ?>

<?= $this->endSection() ?>
