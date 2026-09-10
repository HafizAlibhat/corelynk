<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= empty($priceList) ? 'Create' : 'Edit' ?> Price List<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
    $pl        = $priceList ?? [];
    $partyType = $pl['party_type'] ?? 'customer';
    $mode      = $pl['pricing_mode'] ?? 'fixed';
    $action    = site_url('price-lists/save' . (!empty($pl['id']) ? '/' . (int)$pl['id'] : ''));
?>
<form method="post" action="<?= $action ?>" id="price-list-form">
    <?= csrf_field() ?>
    <div class="card">
        <div class="card-header section-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="section-title"><?= empty($pl) ? 'Create' : 'Edit' ?> Price List</h3>
                <div class="section-sub">Rules used to price documents for a customer or a vendor</div>
            </div>
            <a href="<?= site_url('price-lists') ?>" class="btn btn-outline-secondary">Back to list</a>
        </div>
        <div class="card-body">
            <?php if (session()->getFlashdata('error')): ?>
                <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Applies to</label>
                    <select name="party_type" id="pl-party-type" class="form-select">
                        <option value="customer" <?= $partyType === 'customer' ? 'selected' : '' ?>>Customer (sales)</option>
                        <option value="vendor" <?= $partyType === 'vendor' ? 'selected' : '' ?>>Vendor (purchase)</option>
                    </select>
                </div>
                <div class="col-md-3 pl-party" data-party="customer">
                    <label class="form-label">Customer</label>
                    <select name="customer_id" id="pl-customer" class="form-select pl-searchable">
                        <option value="">All customers</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= (int)($pl['customer_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= esc(($c['customer_code'] ? $c['customer_code'] . ' - ' : '') . $c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 pl-party" data-party="vendor">
                    <label class="form-label">Vendor</label>
                    <select name="vendor_id" id="pl-vendor" class="form-select pl-searchable">
                        <option value="">All vendors</option>
                        <?php foreach ($vendors as $v): ?>
                            <option value="<?= (int)$v['id'] ?>" <?= (int)($pl['vendor_id'] ?? 0) === (int)$v['id'] ? 'selected' : '' ?>><?= esc($v['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" required value="<?= esc($pl['name'] ?? '') ?>" placeholder="e.g. Standard 30% margin">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Currency</label>
                    <select name="currency" class="form-select">
                        <option value="">Document currency</option>
                        <?php foreach ($currencies as $code): ?>
                            <option value="<?= esc($code) ?>" <?= ($pl['currency'] ?? '') === $code ? 'selected' : '' ?>><?= esc($code) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <hr>

            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Pricing rule</label>
                    <select name="pricing_mode" id="pl-mode" class="form-select">
                        <option value="fixed" <?= $mode === 'fixed' ? 'selected' : '' ?>>Fixed prices per product</option>
                        <option value="margin_on_cost" <?= $mode === 'margin_on_cost' ? 'selected' : '' ?>>Margin on cost price</option>
                        <option value="discount_on_base" <?= $mode === 'discount_on_base' ? 'selected' : '' ?>>Discount off base price</option>
                    </select>
                </div>
                <div class="col-md-2 pl-margin">
                    <label class="form-label">Percentage</label>
                    <input type="number" step="0.01" name="margin_percent" id="pl-percent" class="form-control" value="<?= isset($pl['margin_percent']) && $pl['margin_percent'] !== null ? esc((float)$pl['margin_percent']) : '' ?>" placeholder="30">
                </div>
                <div class="col-md-3 pl-margin" id="pl-method-wrap">
                    <label class="form-label">Percentage means</label>
                    <select name="margin_method" class="form-select">
                        <option value="markup" <?= ($pl['margin_method'] ?? 'markup') === 'markup' ? 'selected' : '' ?>>Markup: cost x (1 + %)</option>
                        <option value="margin" <?= ($pl['margin_method'] ?? '') === 'margin' ? 'selected' : '' ?>>Margin: cost / (1 - %)</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Valid from</label>
                    <input type="date" name="valid_from" class="form-control" value="<?= esc($pl['valid_from'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Valid to</label>
                    <input type="date" name="valid_to" class="form-control" value="<?= esc($pl['valid_to'] ?? '') ?>">
                </div>
                <div class="col-md-12">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="pl-active" name="is_active" value="1" <?= (int)($pl['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="pl-active">Active</label>
                    </div>
                    <div class="form-text" id="pl-mode-help"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header section-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="section-title">Product Exceptions</h3>
                <div class="section-sub">Optional. A product listed here overrides the rule above.</div>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm" id="pl-add-row"><i class="bi bi-plus-circle me-1"></i>Add Product</button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle" id="pl-items">
                    <thead>
                        <tr>
                            <th style="width:34%;">Product</th>
                            <th style="width:16%;">Rule</th>
                            <th style="width:16%;">Fixed price <span class="text-muted fw-normal" id="pl-price-ccy"></span></th>
                            <th style="width:14%;">Margin %</th>
                            <th style="width:12%;">Min qty</th>
                            <th style="width:8%;"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $i => $it): ?>
                        <tr>
                            <td>
                                <input type="text" class="form-control form-control-sm pl-prod-search" value="<?= esc(trim($it['product_code'] . ' - ' . $it['product_name'], ' -')) ?>" placeholder="Search product by code or name" autocomplete="off">
                                <input type="hidden" name="items[<?= $i ?>][product_id]" class="pl-prod-id" value="<?= (int)$it['product_id'] ?>">
                                <input type="hidden" name="items[<?= $i ?>][variant_id]" class="pl-variant-id" value="<?= (int)($it['variant_id'] ?? 0) ?>">
                                <div class="small text-muted pl-prod-variant"><?= esc($it['variant_text'] ?? '') ?></div>
                                <div class="small text-muted pl-prod-cost">Cost: <?= esc(number_format((float)$it['cost_price'], 2)) ?> <?= esc($it['cost_currency']) ?></div>
                            </td>
                            <td>
                                <select name="items[<?= $i ?>][pricing_mode]" class="form-select form-select-sm">
                                    <option value="fixed" <?= ($it['pricing_mode'] ?? 'fixed') !== 'margin_on_cost' ? 'selected' : '' ?>>Fixed</option>
                                    <option value="margin_on_cost" <?= ($it['pricing_mode'] ?? '') === 'margin_on_cost' ? 'selected' : '' ?>>Margin on cost</option>
                                </select>
                            </td>
                            <td><input type="number" step="0.01" min="0" name="items[<?= $i ?>][special_price]" class="form-control form-control-sm" value="<?= esc(number_format((float)$it['special_price'], 2, '.', '')) ?>"></td>
                            <td><input type="number" step="0.01" name="items[<?= $i ?>][margin_percent]" class="form-control form-control-sm" value="<?= isset($it['margin_percent']) && $it['margin_percent'] !== null ? esc((float)$it['margin_percent']) : '' ?>"></td>
                            <td><input type="number" min="1" name="items[<?= $i ?>][min_quantity]" class="form-control form-control-sm" value="<?= (int)$it['min_quantity'] ?>"></td>
                            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger pl-del-row"><i class="bi bi-trash"></i></button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="text-muted small">
                Leave empty to price every product by the rule above. <strong>Min qty</strong> makes the row a
                volume tier: add the same product twice (min qty 1 and min qty 100) and the highest tier the
                ordered quantity reaches wins. Picking a variant (size / colour / thickness) prices that
                variant only; pick the plain product to price every variant. Prices are always in the price
                list currency chosen above.
            </div>
        </div>
    </div>

    <div class="mt-3">
        <button type="submit" class="btn btn-primary">Save Price List</button>
    </div>
</form>

<template id="pl-row-template">
    <tr>
        <td>
            <input type="text" class="form-control form-control-sm pl-prod-search" placeholder="Search product by code or name" autocomplete="off">
            <input type="hidden" name="items[IDX][product_id]" class="pl-prod-id" value="">
            <input type="hidden" name="items[IDX][variant_id]" class="pl-variant-id" value="">
            <div class="small text-muted pl-prod-variant"></div>
            <div class="small text-muted pl-prod-cost"></div>
        </td>
        <td>
            <select name="items[IDX][pricing_mode]" class="form-select form-select-sm">
                <option value="fixed">Fixed</option>
                <option value="margin_on_cost">Margin on cost</option>
            </select>
        </td>
        <td><input type="number" step="0.01" min="0" name="items[IDX][special_price]" class="form-control form-control-sm" value="0.00"></td>
        <td><input type="number" step="0.01" name="items[IDX][margin_percent]" class="form-control form-control-sm"></td>
        <td><input type="number" min="1" name="items[IDX][min_quantity]" class="form-control form-control-sm" value="1"></td>
        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger pl-del-row"><i class="bi bi-trash"></i></button></td>
    </tr>
</template>

<script>
(function () {
    var base = '<?= rtrim(site_url(), '/') ?>';
    var tbody = document.querySelector('#pl-items tbody');
    var nextIdx = <?= count($items) ?>;

    function syncParty() {
        var t = document.getElementById('pl-party-type').value;
        document.querySelectorAll('.pl-party').forEach(function (el) {
            el.style.display = el.dataset.party === t ? '' : 'none';
        });
    }
    function syncMode() {
        var m = document.getElementById('pl-mode').value;
        document.querySelectorAll('.pl-margin').forEach(function (el) {
            el.style.display = m === 'fixed' ? 'none' : '';
        });
        document.getElementById('pl-method-wrap').style.display = m === 'margin_on_cost' ? '' : 'none';
        var help = {
            fixed: 'Only the products listed below get a price from this list. Everything else keeps its normal price.',
            margin_on_cost: 'Every product is priced from its cost price. Cost 100 with 30% markup = 130.00; with 30% margin = 142.86.',
            discount_on_base: 'Every product is priced from its normal sale price, less this percentage.'
        };
        document.getElementById('pl-mode-help').textContent = help[m] || '';
    }
    // Customer/vendor lists get long: Select2 gives them a search box.
    function initSearchable() {
        if (typeof $ === 'undefined' || !$.fn.select2) { return false; }
        $('.pl-searchable').each(function () {
            if ($(this).data('select2')) { return; }
            $(this).select2({ width: '100%', dropdownParent: $(this).closest('.card-body') });
        });
        return true;
    }
    if (!initSearchable()) { setTimeout(initSearchable, 300); }

    document.getElementById('pl-party-type').addEventListener('change', syncParty);
    document.getElementById('pl-mode').addEventListener('change', syncMode);
    syncParty();
    syncMode();

    document.getElementById('pl-add-row').addEventListener('click', function () {
        var html = document.getElementById('pl-row-template').innerHTML.replace(/IDX/g, nextIdx++);
        var tr = document.createElement('tbody');
        tr.innerHTML = '<table>' + html + '</table>';
        tbody.appendChild(tr.querySelector('tr'));
    });

    tbody.addEventListener('click', function (e) {
        var btn = e.target.closest('.pl-del-row');
        if (btn) { btn.closest('tr').remove(); }
    });

    // Keep the "Fixed price" header showing which currency those numbers are in.
    var ccySelect = document.querySelector('select[name="currency"]');
    function syncCurrencyLabel() {
        var el = document.getElementById('pl-price-ccy');
        if (el) { el.textContent = '(' + (ccySelect && ccySelect.value ? ccySelect.value : 'document currency') + ')'; }
    }
    if (ccySelect) { ccySelect.addEventListener('change', syncCurrencyLabel); }
    syncCurrencyLabel();

    // Product picker: reuses the quotation product search endpoint. The list is
    // attached to <body> because the table scrolls and would clip it.
    var suggestBox = null;
    var timer = null;

    function esc(v) {
        var d = document.createElement('div');
        d.textContent = v == null ? '' : v;
        return d.innerHTML;
    }

    function closeSuggest() {
        if (suggestBox) { suggestBox.remove(); suggestBox = null; }
    }

    function openSuggest(input, rows) {
        closeSuggest();
        var rect = input.getBoundingClientRect();
        suggestBox = document.createElement('div');
        suggestBox.className = 'card shadow';
        suggestBox.style.cssText = 'position:fixed;z-index:2000;max-height:260px;overflow:auto;'
            + 'top:' + (rect.bottom + 2) + 'px;left:' + rect.left + 'px;width:' + Math.max(rect.width, 320) + 'px;';

        if (!rows.length) {
            var empty = document.createElement('div');
            empty.className = 'p-2 small text-muted';
            empty.textContent = 'No products found';
            suggestBox.appendChild(empty);
        }
        rows.slice(0, 20).forEach(function (p) {
            var row = document.createElement('div');
            row.className = 'p-2 small border-bottom';
            row.style.cursor = 'pointer';
            var cost = (p.cost_price !== undefined && p.cost_price !== null) ? p.cost_price : '';
            var attrs = p.attributes_text || p.variant_name || '';
            row.innerHTML = '<strong>' + esc(p.code || '') + '</strong> ' + esc(p.name || '')
                + (attrs ? '<div class="text-primary">' + esc(attrs) + '</div>' : '')
                + (cost !== '' ? '<div class="text-muted">Cost: ' + cost + ' ' + esc(p.cost_currency || '') + '</div>' : '');
            row.addEventListener('mousedown', function (e) {
                e.preventDefault();
                input.value = (p.code || '') + ' - ' + (p.name || '');
                var td = input.closest('td');
                td.querySelector('.pl-prod-id').value = p.product_id || p.id;
                td.querySelector('.pl-variant-id').value = p.variant_id || '';
                var attrEl = td.querySelector('.pl-prod-variant');
                if (attrEl) { attrEl.textContent = attrs; }
                var costEl = td.querySelector('.pl-prod-cost');
                if (costEl) { costEl.textContent = cost !== '' ? ('Cost: ' + cost + ' ' + (p.cost_currency || '')) : ''; }
                closeSuggest();
            });
            suggestBox.appendChild(row);
        });
        document.body.appendChild(suggestBox);
    }

    tbody.addEventListener('input', function (e) {
        var input = e.target.closest('.pl-prod-search');
        if (!input) { return; }
        input.closest('td').querySelector('.pl-prod-id').value = '';
        input.closest('td').querySelector('.pl-variant-id').value = '';
        var attrEl = input.closest('td').querySelector('.pl-prod-variant');
        if (attrEl) { attrEl.textContent = ''; }
        clearTimeout(timer);
        var term = input.value.trim();
        if (term.length < 2) { closeSuggest(); return; }
        timer = setTimeout(function () {
            fetch(base + '/quotations/search-products?q=' + encodeURIComponent(term), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (r) { return r.json(); })
                .then(function (rows) { openSuggest(input, Array.isArray(rows) ? rows : (rows && rows.data ? rows.data : [])); })
                .catch(function () { closeSuggest(); });
        }, 250);
    });

    window.addEventListener('scroll', closeSuggest, true);
    window.addEventListener('resize', closeSuggest);

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.pl-prod-search')) { closeSuggest(); }
    });
})();
</script>
<?= $this->endSection() ?>
