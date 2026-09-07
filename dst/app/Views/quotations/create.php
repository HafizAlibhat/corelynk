<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>
<?php
    $docType = $docType ?? 'quotation'; // 'quotation' | 'sales_order'
    $docTitle = ($docType === 'sales_order') ? 'Sales Order' : 'Quotation';
    $currencyList = $currencies ?? [];
    $defaultCurrency = $defaultCurrency ?? 'USD';
    $selectedCurrency = $quote['currency'] ?? (old('currency') ?? $defaultCurrency);
?>
<?= !empty($mode) && $mode === 'edit' ? ('Edit ' . $docTitle) : ('Create ' . $docTitle) ?>

<script>
// Live validation + formatting for every money/percent box on the quotation.
// Server still revalidates (Quotations::save) - this is UX, not the gate.
(function(){
    var SEL = '.line-qty,.line-price,.line-discount,.line-tax,#document_discount_value,#shipping_amount,#modal-shipping-create-input';

    function pctPartner(el){
        var row = el.closest ? el.closest('tr') : null;
        if (row && el.classList.contains('line-discount')) return (row.querySelector('.line-discount-type')||{}).value;
        if (row && el.classList.contains('line-tax')) return (row.querySelector('.line-tax-type')||{}).value;
        if (el.id === 'document_discount_value') return (document.getElementById('document_discount_type')||{}).value;
        return null;
    }

    function label(el){
        if (el.classList.contains('line-qty')) return 'Qty';
        if (el.classList.contains('line-price')) return 'Unit price';
        if (el.classList.contains('line-discount')) return 'Discount';
        if (el.classList.contains('line-tax')) return 'Tax';
        if (el.id === 'document_discount_value') return 'Document discount';
        return 'Shipping';
    }

    // Returns '' when valid, otherwise the message to show.
    function problem(el){
        // badInput = browser parsed nothing usable ("abc", "-0-0-0", "1e", "--5")
        if (el.validity && el.validity.badInput) return label(el) + ': numbers only';
        var raw = String(el.value).trim();
        if (raw === '') return el.classList.contains('line-qty') ? 'Qty is required' : '';
        if (!/^-?\d*(\.\d*)?$/.test(raw)) return label(el) + ': numbers only';
        var v = parseFloat(raw);
        if (!isFinite(v)) return label(el) + ': numbers only';
        if (v < 0) return label(el) + " can't be negative";
        if (el.classList.contains('line-qty') && v <= 0) return 'Qty must be greater than 0';
        if (pctPartner(el) === 'percent' && v > 100) return label(el) + ': max 100%';
        return '';
    }

    function slot(el){
        var row = el.closest ? el.closest('tr') : null;
        if (row) return row.querySelector('.line-errors');
        var box = document.getElementById('doc-total-errors');
        if (!box) {
            box = document.createElement('div');
            box.id = 'doc-total-errors';
            box.className = 'small text-danger mt-1';
            box.style.fontSize = '0.7rem';
            var card = document.querySelector('.cl-detail-card .card-body');
            if (card) card.appendChild(box);
        }
        return box;
    }

    function paint(el){
        var msg = problem(el);
        el.classList.toggle('is-invalid', msg !== '');
        if (msg) { el.setAttribute('title', msg); } else { el.removeAttribute('title'); }
        var box = slot(el);
        if (!box) return msg;
        // one message list per container, keyed by the field that raised it
        var key = 'v-' + (el.name || el.id || label(el));
        var tag = box.querySelector('[data-vkey="' + key + '"]');
        if (msg) {
            if (!tag) { tag = document.createElement('div'); tag.setAttribute('data-vkey', key); box.appendChild(tag); }
            tag.textContent = msg;
        } else if (tag) {
            tag.remove();
        }
        return msg;
    }

    function invalidFields(){
        return Array.prototype.filter.call(document.querySelectorAll(SEL), function(el){ return paint(el) !== ''; });
    }

    // scrolling over a focused number box silently changes prices - kill it
    document.addEventListener('wheel', function(e){
        var t = e.target;
        if (t && t.type === 'number' && t === document.activeElement) t.blur();
    }, { passive: true });

    document.addEventListener('input', function(e){
        if (e.target && e.target.matches && e.target.matches(SEL)) paint(e.target);
    });

    // format to 2dp on blur, but never silently "fix" a value the user must see is wrong
    document.addEventListener('blur', function(e){
        var el = e.target;
        if (!el || !el.matches || !el.matches(SEL)) return;
        if (paint(el) !== '' || el.value === '') return;
        if (el.classList.contains('line-qty')) return;
        var next = parseFloat(el.value).toFixed(2);
        if (next !== el.value) { el.value = next; el.dispatchEvent(new Event('change', { bubbles: true })); }
    }, true);

    // %/Fixed switch changes what counts as valid (100 cap)
    document.addEventListener('change', function(e){
        var t = e.target;
        if (!t || !t.classList) return;
        if (t.classList.contains('line-discount-type') || t.classList.contains('line-tax-type')) {
            var row = t.closest('tr');
            if (row) ['.line-discount', '.line-tax'].forEach(function(sel){ var f = row.querySelector(sel); if (f) paint(f); });
        } else if (t.id === 'document_discount_type') {
            var d = document.getElementById('document_discount_value'); if (d) paint(d);
        }
    });

    function block(e){
        var bad = invalidFields();
        if (!bad.length) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        bad[0].focus();
        var banner = document.getElementById('doc-total-errors');
        if (banner) banner.scrollIntoView({ block: 'nearest' });
    }

    // capture phase runs before the page's own save handlers
    document.addEventListener('click', function(e){
        var btn = e.target && e.target.closest ? e.target.closest('#btn-save-quote') : null;
        if (btn) block(e);
    }, true);
    document.addEventListener('keydown', function(e){
        if (e.key !== 'Enter') return;
        var t = e.target;
        if (t && t.matches && t.matches(SEL)) block(e);
    }, true);
})();
</script>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<!-- Global JS error catcher: shows fatal JS errors at top of page (dev-friendly, but doesn't alarm users for non-fatal syntax-scoped issues) -->
<div id="js-error-box" style="display:none;background:#ffebee;color:#b71c1c;padding:12px 18px;margin-bottom:18px;border:1px solid #b71c1c;font-weight:bold;font-size:1.1rem;z-index:99999;"></div>
<div class="card cl-document-form cl-form-page">
    <div class="card-header section-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h3 class="section-title"><?= !empty($mode) && $mode === 'edit' ? ('Edit ' . $docTitle) : ('Create ' . $docTitle) ?></h3>
                <div class="section-sub"><?= !empty($mode) && $mode === 'edit' ? 'Update customer and product lines' : 'Fill customer and product lines' ?></div>
            </div>
            <div>
                <a href="<?= site_url($docType === 'sales_order' ? 'sales-orders' : 'quotations') ?>" class="btn btn-outline-secondary">Back to list</a>
            </div>
        </div>
    </div>
    <div class="card-body">
    <?php
        $isEdit = !empty($mode) && $mode === 'edit' && !empty($quote['id']);
        $action = ($docType === 'sales_order')
            ? 'sales-orders/create'
            : ($isEdit ? ('quotations/update/' . (int)$quote['id']) : 'quotations/create');
        $defaultStatus = $isEdit ? ($quote['status'] ?? 'quoted') : 'quoted';
        $defaultCustomerId = $isEdit ? ($quote['customer_id'] ?? '') : (old('customer_id') ?? '');
        $defaultCustomerSearch = $isEdit ? (($quote['customer_code'] ?? '') . (isset($quote['customer_name']) ? (' - ' . $quote['customer_name']) : '')) : (old('customer_search') ?? '');
        $customerLockedOnEdit = $isEdit && !empty($prefillLines);
    // Date display in DD-MM-YYYY for UX (stored as posted string)
    $rawIssueDate = $isEdit ? ($quote['issue_date'] ?? date('Y-m-d')) : date('Y-m-d');
    $defaultIssueDate = date('d-m-Y', strtotime($rawIssueDate));
    // Prefer the explicit shipping_amount field. Do not fall back to legacy shipping_cost.
    $defaultShipping = $isEdit ? (float)($quote['shipping_amount'] ?? 0) : 0.0;
    // For Sales Orders, shipping should be visible on create by default.
    $showShipping = ($docType === 'sales_order') ? true : ($isEdit && $defaultShipping > 0);
        $prefillLines = $isEdit ? ($lines ?? []) : [];
        $defaultSubtotal = $isEdit ? (float)($quote['subtotal'] ?? 0) : 0.0;
        $defaultDiscount = $isEdit ? (float)($quote['discount'] ?? 0) : 0.0;
        $defaultTax = $isEdit ? (float)($quote['tax'] ?? ($quote['tax_total'] ?? 0)) : 0.0;
        $defaultTotal = $isEdit ? (float)($quote['total'] ?? 0) : 0.0;
        $defaultDocDiscountType = $isEdit ? strtolower((string)($quote['document_discount_type'] ?? 'fixed')) : 'fixed';
        if (!in_array($defaultDocDiscountType, ['percent','fixed'], true)) $defaultDocDiscountType = 'fixed';
        $defaultDocDiscountValue = $isEdit ? (float)($quote['document_discount_value'] ?? 0) : 0.0;
        $defaultExcludeShipping = $isEdit ? ((int)($quote['discount_exclude_shipping'] ?? 1) === 1) : true;
    ?>
    <?= form_open($action, ['id' => 'quotation-form', 'class' => 'cl-form-page']) ?>
    <input type="hidden" name="status" id="quote-status" value="<?= esc($defaultStatus) ?>">
        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
        <?php endif; ?>

        <!-- Customer search -->
        <div class="row g-3 mb-3">
            <div class="col-md-5">
                <div class="cl-field-head">
                    <label class="form-label mb-0" for="customer_search">Customer</label>
                    <?php if (!$customerLockedOnEdit): ?>
                        <button type="button" id="btn-add-customer" class="btn btn-outline-primary cl-field-action"><i class="bi bi-plus-circle"></i>Add New Customer</button>
                    <?php endif; ?>
                </div>
                <div class="position-relative">
                    <input type="text" id="customer_search" class="form-control" placeholder="Search customer by code or name" value="<?= esc($defaultCustomerSearch) ?>" <?= $customerLockedOnEdit ? 'readonly' : '' ?>>
                    <input type="hidden" name="customer_id" id="customer_id" value="<?= esc($defaultCustomerId) ?>">
                    <div id="customer_list" class="card autocomplete-list" style="position:absolute;z-index:1200;display:none;width:100%"></div>
                    <div class="invalid-feedback d-block" id="error-customer"><?= esc(session()->getFlashdata('form_errors')['customer_id'] ?? '') ?></div>
                    <?php if ($customerLockedOnEdit): ?>
                        <small class="text-muted">Customer is locked because this quotation already has line items. Create a new quotation to change customer.</small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Price List</label>
                <select name="price_list_id" id="price_list_id" class="form-select">
                    <option value="">Default</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Date (DD-MM-YYYY)</label>
                <input type="text" name="issue_date" class="form-control" placeholder="DD-MM-YYYY" value="<?= esc($defaultIssueDate) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Currency</label>
                <select name="currency" class="form-select" required>
                    <?php if (!empty($currencyList)): ?>
                        <?php foreach ($currencyList as $cur): ?>
                            <option value="<?= esc($cur['code']) ?>" <?= ($selectedCurrency === ($cur['code'] ?? '')) ? 'selected' : '' ?>><?= esc($cur['code']) ?> <?= esc($cur['name'] ?? '') ?></option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="<?= esc($selectedCurrency) ?>" selected><?= esc($selectedCurrency) ?></option>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <!-- Quote lines table -->
        <style>
            .quote-create-lines {
                table-layout: auto;
                width: 100%;
            }
            /* Label row with a trailing action: the action never grows the row,
               so this column lines up with Price List / Date / Currency. */
            .cl-field-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 8px;
                min-height: 24px;
                margin-bottom: .5rem;
            }
            .cl-field-action {
                --bs-btn-padding-y: 0;
                --bs-btn-padding-x: .5rem;
                --bs-btn-font-size: .72rem;
                display: inline-flex;
                align-items: center;
                gap: 5px;
                height: 22px;
                border-radius: 999px;
                font-weight: 600;
                white-space: nowrap;
            }
            .quote-create-lines .line-discount-editor,
            .quote-create-lines .line-tax-editor {
                display: flex;
                align-items: center;
                gap: 4px;
                width: 170px !important;
                min-width: 170px !important;
                max-width: 170px !important;
                box-sizing: border-box;
                flex-wrap: nowrap;
            }
            .quote-create-lines .line-discount-editor > select,
            .quote-create-lines .line-tax-editor > select {
                flex: 0 0 68px;
                width: 68px !important;
                min-width: 68px !important;
                max-width: 68px !important;
                box-sizing: border-box;
            }
            .quote-create-lines .line-discount-editor > input,
            .quote-create-lines .line-tax-editor > input {
                flex: 0 0 88px;
                width: 88px !important;
                min-width: 88px !important;
                max-width: 88px !important;
                box-sizing: border-box;
            }
            /* compact rows */
            .quote-create-lines th,
            .quote-create-lines td {
                padding: 2px 4px !important;
                vertical-align: middle;
            }
            .quote-create-lines th {
                font-size: 0.72rem;
                text-transform: uppercase;
                letter-spacing: .02em;
                white-space: nowrap;
            }
            .quote-create-lines .form-control,
            .quote-create-lines .form-select {
                padding: 0.15rem 0.4rem !important;
                font-size: 0.8rem !important;
                height: auto;
                min-height: 28px;
            }
            .quote-create-lines .product-code {
                width: 100% !important;
                min-width: 104px;
                font-size: 0.78rem !important;
                text-overflow: ellipsis;
            }
            .quote-create-lines .line-meta,
            .quote-create-lines td small {
                font-size: 0.68rem;
            }
            .quote-create-lines input + input,
            .quote-create-lines .form-control + .form-control {
                margin-top: 2px;
            }
            /* money boxes: no spinners, right aligned, tabular digits */
            .quote-create-lines input[type=number]::-webkit-outer-spin-button,
            .quote-create-lines input[type=number]::-webkit-inner-spin-button,
            .cl-detail-card input[type=number]::-webkit-outer-spin-button,
            .cl-detail-card input[type=number]::-webkit-inner-spin-button {
                -webkit-appearance: none;
                margin: 0;
            }
            .quote-create-lines input[type=number],
            .cl-detail-card input[type=number] {
                -moz-appearance: textfield;
                font-variant-numeric: tabular-nums;
            }
            .quote-create-lines .line-price,
            .quote-create-lines .line-discount,
            .quote-create-lines .line-tax,
            .quote-create-lines .line-qty {
                text-align: right !important;
            }
            .quote-create-lines .line-price {
                width: 100% !important;
                font-weight: 600;
            }
            .quote-create-lines .line-total {
                font-variant-numeric: tabular-nums;
                white-space: nowrap;
            }
        </style>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2" data-doc-line-toolbar>
            <h5 class="mb-0">Lines</h5>
            <div class="d-flex align-items-center gap-2">
                <button type="button" id="add-line" class="btn btn-outline-secondary btn-sm">Add Line</button>
            </div>
        </div>
        <div class="table-responsive" data-doc-lines-root>
            <table class="table quote-create-lines" id="quote-lines-table">
                <thead>
                    <tr>
                        <th style="width:9%">Code</th>
                        <th style="width:4%">Img</th>
                        <th style="width:36%">Product / Description / Stock / Weight / Vendor</th>
                        <th style="width:6%">Unit</th>
                        <th style="width:5%">Qty</th>
                        <th style="width:10%">Unit Price</th>
                        <th style="width:12%" class="col-disc">Item Discount</th>
                        <th style="width:12%" class="col-tax">Item Tax</th>
                        <th style="width:5%">Total</th>
                        <th style="width:3%"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($isEdit && !empty($prefillLines)): ?>
                        <?php foreach ($prefillLines as $idx => $ln): ?>
                            <?php
                                $img = $ln['product_image_url'] ?? '';
                                $code = $ln['product_code'] ?? '';
                                $name = $ln['product_name'] ?? '';
                                $desc = $ln['description'] ?? $name;
                                $unit = $ln['unit'] ?? 'pcs';
                                $qty = $ln['quantity'] ?? 1;
                                $price = $ln['unit_price'] ?? 0;
                                $discType = $ln['discount_type'] ?? 'percent';
                                $discVal = $ln['discount_value'] ?? 0;
                                $taxType = strtolower((string)($ln['tax_type'] ?? 'percent'));
                                if (!in_array($taxType, ['percent', 'fixed'], true)) $taxType = 'percent';
                                $taxValue = $ln['tax_value'] ?? ($ln['tax_rate'] ?? 0);
                                $lineTotal = $ln['line_total'] ?? 0;
                                $pid = $ln['product_id'] ?? null;
                                $unitWeight = $ln['unit_weight'] ?? ($ln['weight'] ?? 0);
                                $weightUnit = strtoupper((string)($ln['weight_unit'] ?? 'KG'));
                                $hasImg = !empty($img);
                            ?>
                            <tr class="quote-line">
                                <td style="vertical-align:middle;padding:2px;">
                                    <input type="text" name="lines[<?= (int)$idx ?>][product_code]" class="form-control form-control-sm product-code" placeholder="Code" style="width:95%;padding:0.25rem 0.5rem;font-size:0.85rem;" value="<?= esc($code) ?>">
                                    <input type="hidden" name="lines[<?= (int)$idx ?>][id]" value="<?= esc($ln['id'] ?? '') ?>">
                                    <input type="hidden" name="lines[<?= (int)$idx ?>][product_id]" class="product-id" value="<?= esc($pid) ?>">
                                    <input type="hidden" name="lines[<?= (int)$idx ?>][product_variant_id]" class="product-variant-id" value="<?= esc($ln['product_variant_id'] ?? '') ?>">
                                    <input type="hidden" name="lines[<?= (int)$idx ?>][product_image_url]" class="product-image-url" value="<?= esc($img) ?>">
                                </td>
                                <td style="vertical-align:middle;padding:2px;">
                                    <div class="product-thumb-wrap" style="width:32px;height:32px;border-radius:4px;overflow:hidden;background:#0b1220;display:flex;align-items:center;justify-content:center;position:relative;" data-row-index="<?= (int)$idx ?>">
                                        <img src="<?= esc($img) ?>" class="product-thumb" data-empty-src="" data-row="<?= (int)$idx ?>" style="width:32px;height:32px;object-fit:cover;display:<?= $hasImg ? 'block' : 'none' ?>" alt="" onload="this.style.display='block';if(this.parentElement){var ic=this.parentElement.querySelector('.thumb-icon');if(ic)ic.style.display='none';}" onerror="this.style.display='none';if(this.parentElement){var ic=this.parentElement.querySelector('.thumb-icon');if(ic)ic.style.display='flex';}">
                                        <div class="thumb-icon" style="position:absolute;inset:0;display:<?= $hasImg ? 'none' : 'flex' ?>;align-items:center;justify-content:center;color:#94a3b8;">
                                            <i class="bi bi-image"></i>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding:2px;">
                                    <div style="display:flex;flex-direction:column;gap:1px">
                                        <input type="text" name="lines[<?= (int)$idx ?>][product_name]" class="form-control form-control-sm product-name" placeholder="Name" style="padding:0.2rem 0.35rem;font-size:0.82rem;height:auto;line-height:1.2;" value="<?= esc($name) ?>">
                                        <input type="hidden" name="lines[<?= (int)$idx ?>][unit_weight]" class="unit-weight" value="<?= esc($unitWeight) ?>">
                                        <input type="hidden" name="lines[<?= (int)$idx ?>][weight_unit]" class="weight-unit" value="<?= esc($weightUnit) ?>">
                                        <input type="text" name="lines[<?= (int)$idx ?>][description]" class="form-control form-control-sm line-desc" placeholder="Description" style="padding:0.2rem 0.35rem;font-size:0.75rem;height:auto;line-height:1.2;" value="<?= esc($desc) ?>">
                                        <div class="product-meta" style="font-size:0.65rem;color:#7a8fa3;margin-top:1px;line-height:1.3;">
                                            Stock: <span class="meta-stock">0</span> | Vendor: <span class="meta-vendor">-</span> | Weight: <span class="meta-weight"><?= esc($unitWeight) ?></span>
                                        </div>
                                    </div>
                                    <div class="line-errors small text-danger mt-1" style="font-size:0.65rem;"></div>
                                </td>
                                <td style="vertical-align:middle;padding:2px;"><input type="text" name="lines[<?= (int)$idx ?>][unit]" class="form-control form-control-sm" value="<?= esc($unit) ?>" style="width:95%;padding:0.25rem 0.5rem;font-size:0.85rem;"></td>
                                <td style="vertical-align:middle;padding:2px;"><input type="number" step="0.01" min="0" name="lines[<?= (int)$idx ?>][quantity]" class="form-control form-control-sm line-qty" value="<?= esc($qty) ?>" style="width:95%;padding:0.25rem 0.5rem;font-size:0.85rem;text-align:center"></td>
                                <td style="vertical-align:middle;padding:2px;">
                                    <input type="number" step="0.01" min="0" name="lines[<?= (int)$idx ?>][unit_price]" class="form-control line-price" value="<?= number_format((float)$price, 2, '.', '') ?>" style="width:95%;padding:0.25rem 0.5rem;font-size:0.85rem;font-weight:600">
                                </td>
                                <td style="vertical-align:middle;padding:2px;" class="col-disc">
                                    <div class="line-discount-editor">
                                        <select name="lines[<?= (int)$idx ?>][discount_type]" class="form-select form-select-sm line-discount-type" style="max-width:72px;padding:0.25rem 0.35rem;font-size:0.75rem;">
                                            <option value="percent" <?= $discType === 'percent' ? 'selected' : '' ?>>%</option>
                                            <option value="fixed" <?= $discType === 'fixed' ? 'selected' : '' ?>>Fix</option>
                                        </select>
                                        <input type="number" step="0.01" min="0" name="lines[<?= (int)$idx ?>][discount_value]" class="form-control form-control-sm line-discount" placeholder="0.00" style="width:95%;padding:0.25rem 0.5rem;font-size:0.85rem;text-align:right" value="<?= number_format((float)$discVal, 2, '.', '') ?>">
                                    </div>
                                </td>
                                <td style="vertical-align:middle;padding:2px;" class="col-tax">
                                    <div class="line-tax-editor">
                                        <select name="lines[<?= (int)$idx ?>][tax_type]" class="form-select form-select-sm line-tax-type" style="max-width:72px;padding:0.25rem 0.35rem;font-size:0.75rem;">
                                            <option value="percent" <?= $taxType === 'percent' ? 'selected' : '' ?>>%</option>
                                            <option value="fixed" <?= $taxType === 'fixed' ? 'selected' : '' ?>>Fix</option>
                                        </select>
                                        <input type="number" step="0.01" min="0" name="lines[<?= (int)$idx ?>][tax_value]" class="form-control form-control-sm line-tax" placeholder="0.00" style="width:95%;padding:0.25rem 0.5rem;font-size:0.85rem;text-align:right" value="<?= number_format((float)$taxValue, 2, '.', '') ?>">
                                    </div>
                                </td>
                                <td class="line-total text-end" style="padding:2px 2px;vertical-align:middle;font-size:0.85rem;"><?= number_format((float)$lineTotal, 2) ?></td>
                                <td style="vertical-align:middle;padding:2px;"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-line" style="padding:0.25rem 0.5rem;"><i class="bi bi-trash"></i></button></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <tr class="quote-line">
                        <td style="vertical-align:middle;padding:2px;">
                            <input type="text" name="lines[0][product_code]" class="form-control form-control-sm product-code" placeholder="Code" style="width:95%;padding:0.25rem 0.5rem;font-size:0.85rem;">
                            <input type="hidden" name="lines[0][product_id]" class="product-id">
                            <input type="hidden" name="lines[0][product_variant_id]" class="product-variant-id">
                            <input type="hidden" name="lines[0][product_image_url]" class="product-image-url" value="">
                        </td>
                        <td style="vertical-align:middle;padding:2px;">
                            <div class="product-thumb-wrap" style="width:32px;height:32px;border-radius:4px;overflow:hidden;background:#0b1220;display:flex;align-items:center;justify-content:center;position:relative;" data-row-index="0">
                                <img src="" class="product-thumb" data-empty-src="" data-row="0" style="width:32px;height:32px;object-fit:cover;display:none" alt="" onload="this.style.display='block';if(this.parentElement){var ic=this.parentElement.querySelector('.thumb-icon');if(ic)ic.style.display='none';}" onerror="this.style.display='none';if(this.parentElement){var ic=this.parentElement.querySelector('.thumb-icon');if(ic)ic.style.display='flex';}">
                                <div class="thumb-icon" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#94a3b8;">
                                    <i class="bi bi-image"></i>
                                </div>
                            </div>
                        </td>
                        <td style="padding:2px;">
                            <div style="display:flex;flex-direction:column;gap:1px">
                                <input type="text" name="lines[0][product_name]" class="form-control form-control-sm product-name" placeholder="Name" style="padding:0.2rem 0.35rem;font-size:0.82rem;height:auto;line-height:1.2;">
                                <input type="hidden" name="lines[0][unit_weight]" class="unit-weight">
                                <input type="hidden" name="lines[0][weight_unit]" class="weight-unit" value="KG">
                                <input type="text" name="lines[0][description]" class="form-control form-control-sm line-desc" placeholder="Description" style="padding:0.2rem 0.35rem;font-size:0.75rem;height:auto;line-height:1.2;">
                                <div class="product-meta" style="font-size:0.65rem;color:#7a8fa3;margin-top:1px;line-height:1.3;">
                                    Stock: <span class="meta-stock">0</span> | Vendor: <span class="meta-vendor">-</span> | Weight: <span class="meta-weight">0</span>
                                </div>
                            </div>
                            <div class="line-errors small text-danger mt-1" style="font-size:0.65rem;"></div>
                        </td>
                        <td style="vertical-align:middle;padding:2px;"><input type="text" name="lines[0][unit]" class="form-control form-control-sm" value="pcs" style="width:95%;padding:0.25rem 0.5rem;font-size:0.85rem;"></td>
                        <td style="vertical-align:middle;padding:2px;"><input type="number" step="0.01" min="0" name="lines[0][quantity]" class="form-control form-control-sm line-qty" value="1" style="width:95%;padding:0.25rem 0.5rem;font-size:0.85rem;text-align:center"></td>
                        <td style="vertical-align:middle;padding:2px;">
                            <input type="number" step="0.01" min="0" name="lines[0][unit_price]" class="form-control line-price" value="0.00" style="width:95%;padding:0.25rem 0.5rem;font-size:0.85rem;font-weight:600">
                        </td>
                        <td style="vertical-align:middle;padding:2px;" class="col-disc">
                            <div class="line-discount-editor">
                                <select name="lines[0][discount_type]" class="form-select form-select-sm line-discount-type" style="max-width:72px;padding:0.25rem 0.35rem;font-size:0.75rem;">
                                    <option value="percent" selected>%</option>
                                    <option value="fixed">Fix</option>
                                </select>
                                <input type="number" step="0.01" min="0" name="lines[0][discount_value]" class="form-control form-control-sm line-discount" placeholder="0.00" style="width:95%;padding:0.25rem 0.5rem;font-size:0.85rem;text-align:center">
                            </div>
                        </td>
                        <td style="vertical-align:middle;padding:2px;" class="col-tax">
                            <div class="line-tax-editor">
                                <select name="lines[0][tax_type]" class="form-select form-select-sm line-tax-type" style="max-width:72px;padding:0.25rem 0.35rem;font-size:0.75rem;">
                                    <option value="percent" selected>%</option>
                                    <option value="fixed">Fix</option>
                                </select>
                                <input type="number" step="0.01" min="0" name="lines[0][tax_value]" class="form-control form-control-sm line-tax" placeholder="0.00" style="width:95%;padding:0.25rem 0.5rem;font-size:0.85rem;text-align:center">
                            </div>
                        </td>
                        <td class="line-total text-end" style="padding:2px 2px;vertical-align:middle;font-size:0.85rem;">0.00</td>
                        <td style="vertical-align:middle;padding:2px;"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-line" style="padding:0.25rem 0.5rem;"><i class="bi bi-trash"></i></button></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mt-2">
            <div class="mt-3 d-flex flex-wrap gap-2 align-items-center">
                <!-- Single save action -->
                <button type="button" id="btn-save-quote" class="btn btn-primary" data-status="quoted"><?= $isEdit ? ('Update ' . $docTitle) : ('Save ' . $docTitle) ?></button>
            </div>
            <div class="card cl-detail-card" style="min-width:300px;max-width:340px;">
                <div class="card-header"><strong>Totals</strong></div>
                <div class="card-body">
                    <label class="form-label">Document Discount</label>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <select name="document_discount_type" id="document_discount_type" class="form-select form-select-sm" style="max-width:90px;">
                            <option value="fixed" <?= $defaultDocDiscountType === 'fixed' ? 'selected' : '' ?>>Fixed</option>
                            <option value="percent" <?= $defaultDocDiscountType === 'percent' ? 'selected' : '' ?>>%</option>
                        </select>
                        <input type="number" step="0.01" min="0" name="document_discount_value" id="document_discount_value" class="form-control form-control-sm" value="<?= number_format((float)$defaultDocDiscountValue, 2, '.', '') ?>" placeholder="Doc discount">
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" value="1" id="discount_exclude_shipping" name="discount_exclude_shipping" <?= $defaultExcludeShipping ? 'checked' : '' ?>>
                        <label class="form-check-label" for="discount_exclude_shipping" style="font-size:0.8rem;">Exclude shipping from document discount</label>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-1">
                        <span class="text-secondary">Subtotal</span>
                        <span class="fw-semibold" id="subtotal"><?= number_format((float)$defaultSubtotal, 2) ?> <span class="text-muted currency-code"><?= esc($selectedCurrency) ?></span></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-1">
                        <span class="text-secondary">Discount</span>
                        <span class="fw-semibold" id="discount-total"><?= number_format((float)$defaultDiscount, 2) ?> <span class="text-muted currency-code"><?= esc($selectedCurrency) ?></span></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-1">
                        <span class="text-secondary">Tax</span>
                        <span class="fw-semibold" id="tax"><?= number_format((float)$defaultTax, 2) ?> <span class="text-muted currency-code"><?= esc($selectedCurrency) ?></span></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-1">
                        <span class="text-secondary d-flex align-items-center gap-2">
                            Shipping
                            <button type="button" id="add-shipping" class="btn btn-outline-primary btn-sm py-0 px-2">Edit</button>
                            <input type="hidden" name="shipping_amount" id="shipping_amount" value="<?= number_format((float)$defaultShipping, 2, '.', '') ?>">
                        </span>
                        <span class="fw-semibold" id="shipping-total"><?= number_format((float)$defaultShipping, 2) ?> <span class="text-muted currency-code"><?= esc($selectedCurrency) ?></span></span>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold fs-5">Total</span>
                        <span class="fw-bold fs-5" id="grand-total-wrap"><span id="grand-total"><?= number_format((float)$defaultTotal, 2) ?></span> <span class="text-muted currency-code" style="font-size:0.75rem;"><?= esc($selectedCurrency) ?></span></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tags Section -->
        <?php if ($isEdit && !empty($quote['id'])): ?>
            <?php
                $this->setVar('document_type', 'quotation');
                $this->setVar('document_id', (int)$quote['id']);
                $this->setVar('existing_tags', $tags ?? []);
                $this->setVar('readonly', false);
            ?>
            <?= $this->include('components/tag_input') ?>
        <?php endif; ?>

        <?= form_close() ?>
    </div>
</div>

    <script>
    // Global, dependency-free debounce helper for all quotation scripts. Defines once, does not overwrite if already present.
    (function(){
        if (typeof window.debounce === 'function') return;
        window.debounce = function(fn, delay){
            var timeout;
            var wait = typeof delay === 'number' ? delay : 200;
            return function(){
                var ctx = this, args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(function(){ fn.apply(ctx, args); }, wait);
            };
        };
    })();

    // Keep currency labels in totals aligned with the selected currency
    (function(){
        var currencySelect = document.querySelector('select[name="currency"]');
        var currencyEls = document.querySelectorAll('.currency-code');
        var fallback = '<?= esc($selectedCurrency) ?>';

        function updateCurrency(code){
            var label = code || fallback;
            currencyEls.forEach(function(el){ if (el) el.textContent = label; });
        }

        updateCurrency(currencySelect ? currencySelect.value : fallback);
        if (currencySelect) {
            currencySelect.addEventListener('change', function(ev){ updateCurrency(ev.target.value); });
        }
    })();
    </script>
        <script src="<?= base_url('assets/js/quotation_page_inline.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/quotation_page_inline.js') ?>"></script>
        <script src="<?= base_url('assets/js/corelynk_autocomplete.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/corelynk_autocomplete.js') ?>"></script>
        <script src="<?= base_url('assets/js/quotation_calculator.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/quotation_calculator.js') ?>"></script>
        <script>
            // Reduce noisy false-positive banner for the common "missing ) after argument list" on some pages.
            // This does NOT change app logic. It only prevents the UX from showing a scary red banner for a non-fatal parse error
            // that is typically caused by a browser extension script or a cached/partial asset during reload.
            (function(){
                var box = document.getElementById('js-error-box');
                if (!box) return;
                var last = '';
                function shouldSuppress(message, filename){
                    var msg = String(message || '');
                    var file = String(filename || '');
                    // Suppress only if it's the specific syntax error and it points to this create page.
                    if (msg.toLowerCase().indexOf('missing ) after argument list') !== -1 && file.indexOf('/quotations/create') !== -1) {
                        return true;
                    }
                    return false;
                }
                // Hook *capturing* so we run before the global handler in quotation_page_inline.js.
                window.addEventListener('error', function(e){
                    try {
                        if (shouldSuppress(e.message, e.filename)) {
                            // Remember it for diagnostics but don't show it.
                            last = e.message + ' @ ' + (e.filename||'') + ':' + (e.lineno||'');
                            box.style.display = 'none';
                        }
                    } catch(err) {}
                }, true);
            })();
        </script>
    <?php // NOTE: quotations.js also attaches a submit handler; disabled to prevent double-submit/duplicate lines. ?>

<!-- Shipping modal -->
<div class="modal fade" id="modal-shipping-create" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Shipping Amount</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Amount</label>
                <input type="number" step="0.01" min="0" class="form-control" id="modal-shipping-create-input" value="<?= number_format((float)$defaultShipping, 2, '.', '') ?>">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="modal-shipping-create-save">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Quick Add Customer modal -->
<div class="modal fade" id="modal-add-customer" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus-fill me-2"></i>Add New Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger d-none" id="modal-add-customer-error"></div>
                <div class="mb-3">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="modal-add-customer-name" placeholder="Customer name" autocomplete="off">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Phone</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                        <input type="text" class="form-control" id="modal-add-customer-phone" placeholder="Optional" autocomplete="off">
                    </div>
                </div>
                <div class="mb-1">
                    <label class="form-label">Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" id="modal-add-customer-email" placeholder="Optional" autocomplete="off">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="modal-add-customer-save"><i class="bi bi-check-lg me-1"></i>Save Customer</button>
            </div>
        </div>
    </div>
</div>

<script>
// Money/percent input UX for quotation lines + totals: no scroll-wheel edits,
// 2-decimal normalisation on blur, percent values clamped to 100.
(function(){
    var MONEY = '.line-price,.line-discount,.line-tax,#document_discount_value,#shipping_amount';

    function typeOf(el){
        var row = el.closest ? el.closest('tr') : null;
        if (!row) return null;
        if (el.classList.contains('line-discount')) return (row.querySelector('.line-discount-type')||{}).value;
        if (el.classList.contains('line-tax')) return (row.querySelector('.line-tax-type')||{}).value;
        return null;
    }

    function normalise(el){
        if (el.value === '') return;
        var v = parseFloat(el.value);
        if (isNaN(v) || v < 0) v = 0;
        if (typeOf(el) === 'percent' && v > 100) v = 100;
        var next = v.toFixed(2);
        if (next !== el.value) {
            el.value = next;
            el.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    // scrolling over a focused number box silently changes prices - kill it
    document.addEventListener('wheel', function(e){
        var t = e.target;
        if (t && t.type === 'number' && t === document.activeElement) t.blur();
    }, { passive: true });

    document.addEventListener('blur', function(e){
        var t = e.target;
        if (t && t.matches && t.matches(MONEY)) normalise(t);
    }, true);

    // switching %/Fixed re-clamps the value it applies to
    document.addEventListener('change', function(e){
        var t = e.target;
        if (!t || !t.classList) return;
        if (t.classList.contains('line-discount-type')) {
            var d = t.closest('tr').querySelector('.line-discount'); if (d) normalise(d);
        } else if (t.classList.contains('line-tax-type')) {
            var x = t.closest('tr').querySelector('.line-tax'); if (x) normalise(x);
        }
    });
})();
</script>
<?= $this->endSection() ?>
