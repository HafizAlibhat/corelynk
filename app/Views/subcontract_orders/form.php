<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php
    $isEdit = !empty($order);
    $action = $isEdit ? base_url('/subcontract-orders/' . $order['id'] . '/update') : base_url('/subcontract-orders/store');
?>

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-0">
                <i class="bi bi-arrow-repeat me-2"></i>
                <?= $isEdit ? 'Edit ' . esc($order['order_number']) : 'New Subcontract Order' ?>
            </h1>
            <small class="text-muted">Send materials to a vendor for processing (e.g., PVD coating, plating, etc.)</small>
        </div>
        <a href="<?= base_url('/subcontract-orders') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to List
        </a>
    </div>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= session()->getFlashdata('error') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= $action ?>" id="scOrderForm">
        <?= csrf_field() ?>

        <div class="row">
            <!-- Left column: Order details -->
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header py-2">
                        <h5 class="card-title mb-0"><i class="bi bi-info-circle me-2"></i>Order Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="vendor_id" class="form-label">Vendor <span class="text-danger">*</span></label>
                                <select name="vendor_id" id="vendor_id" class="form-select" required>
                                    <option value="">— Select Vendor —</option>
                                    <?php foreach ($vendors as $v): ?>
                                        <option value="<?= $v['id'] ?>" <?= (old('vendor_id', $order['vendor_id'] ?? '') == $v['id']) ? 'selected' : '' ?>>
                                            <?= esc($v['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text small">The vendor who will perform the service</div>
                            </div>

                            <div class="col-md-6">
                                <label for="service_product_id" class="form-label">Service Product <span class="text-danger">*</span></label>
                                <select name="service_product_id" id="service_product_id" class="form-select" required>
                                    <option value="">— Select Service —</option>
                                    <?php foreach ($serviceProducts as $sp): ?>
                                        <option value="<?= $sp['id'] ?>"
                                                data-unit="<?= esc($sp['unit'] ?? 'pcs') ?>"
                                                data-price="<?= (float)($sp['cost_price'] ?? $sp['sale_price'] ?? 0) ?>"
                                                <?= (old('service_product_id', $order['service_product_id'] ?? '') == $sp['id']) ? 'selected' : '' ?>>
                                            <?= esc($sp['name']) ?> <?= !empty($sp['code']) ? '(' . esc($sp['code']) . ')' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text small">
                                    Choose a service-type product (e.g., PVD Coating, Laser Marking).
                                    <?php if (empty($serviceProducts)): ?>
                                        <br><span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>No service products found. <a href="<?= base_url('/products/create') ?>">Create one first</a> with type "Service".</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label for="quantity" class="form-label">Service Quantity <span class="text-danger">*</span></label>
                                <input type="number" name="quantity" id="quantity" class="form-control" step="1" min="1" required
                                       value="<?= old('quantity', $order['quantity'] ?? '') ?>" placeholder="e.g., 100">
                                <div class="form-text small" id="unitLabel">pieces</div>
                            </div>

                            <div class="col-md-3">
                                <label for="unit_price" class="form-label">Price per Unit <span class="text-danger">*</span></label>
                                <input type="number" name="unit_price" id="unit_price" class="form-control" step="0.01" min="0" required
                                       value="<?= old('unit_price', $order['unit_price'] ?? '') ?>" placeholder="e.g., 5.00">
                            </div>

                            <div class="col-md-2">
                                <label for="currency" class="form-label">Currency</label>
                                <select name="currency" id="currency" class="form-select">
                                    <?php $selCur = old('currency', $order['currency'] ?? ($defaultCurrency ?? 'PKR')); ?>
                                    <?php foreach ($currencies as $c): ?>
                                        <option value="<?= esc($c['code']) ?>" <?= $selCur === $c['code'] ? 'selected' : '' ?>><?= esc($c['code']) ?></option>
                                    <?php endforeach; ?>
                                    <?php if (empty($currencies)): ?>
                                        <option value="PKR" selected>PKR</option>
                                        <option value="USD">USD</option>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Total</label>
                                <div class="form-control bg-light" id="totalDisplay">
                                    <strong id="totalAmount">0.00</strong>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label for="expected_return_date" class="form-label">Expected Return Date</label>
                                <input type="date" name="expected_return_date" id="expected_return_date" class="form-control"
                                       value="<?= old('expected_return_date', $order['expected_return_date'] ?? '') ?>">
                            </div>

                            <div class="col-12">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea name="notes" id="notes" class="form-control" rows="2" placeholder="Any special instructions for the vendor…"><?= old('notes', $order['notes'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Material Lines -->
                <div class="card mb-3">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"><i class="bi bi-box-seam me-2"></i>Materials to Send</h5>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="addLineBtn">
                            <i class="bi bi-plus-lg me-1"></i> Add Item
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0" id="linesTable">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:35%">Product</th>
                                        <th style="width:25%">Description</th>
                                        <th style="width:15%" class="text-end">Qty to Send</th>
                                        <th style="width:5%"></th>
                                    </tr>
                                </thead>
                                <tbody id="linesBody">
                                    <?php if (!empty($lines)): ?>
                                        <?php foreach ($lines as $idx => $line): ?>
                                            <tr class="line-row">
                                                <td>
                                                    <select name="line_product_id[]" class="form-select form-select-sm line-product" required>
                                                        <option value="">— Select Product —</option>
                                                        <?php foreach ($storableProducts as $sp): ?>
                                                            <option value="<?= $sp['id'] ?>" <?= ($line['product_id'] == $sp['id']) ? 'selected' : '' ?>>
                                                                <?= esc($sp['name']) ?> <?= !empty($sp['code']) ? '(' . esc($sp['code']) . ')' : '' ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <select name="line_variant_id[]" class="form-select form-select-sm line-variant mt-1" data-selected-variant="<?= esc($line['variant_id'] ?? '') ?>">
                                                        <option value="">— Any / Non-variant —</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="text" name="line_description[]" class="form-control form-control-sm" value="<?= esc($line['description'] ?? '') ?>">
                                                </td>
                                                <td>
                                                    <input type="number" name="line_qty_sent[]" class="form-control form-control-sm text-end" step="1" min="1" required value="<?= (float)($line['qty_sent'] ?? 0) ?>">
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-danger remove-line-btn"><i class="bi bi-trash"></i></button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-muted small p-2" id="noLinesMsg" <?= !empty($lines) ? 'style="display:none"' : '' ?>>
                            <i class="bi bi-info-circle me-1"></i> Add the storable products that will be sent to the vendor for processing.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right column: Warehouse and actions -->
            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header py-2">
                        <h5 class="card-title mb-0"><i class="bi bi-building me-2"></i>Source Warehouse</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="warehouse_id" class="form-label small">Warehouse</label>
                            <select name="warehouse_id" id="warehouse_id" class="form-select form-select-sm">
                                <option value="">— Default —</option>
                                <?php foreach ($warehouses as $w): ?>
                                    <option value="<?= $w['id'] ?>" <?= (old('warehouse_id', $order['warehouse_id'] ?? '') == $w['id']) ? 'selected' : '' ?>>
                                        <?= esc($w['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="location_id" class="form-label small">Location</label>
                            <select name="location_id" id="location_id" class="form-select form-select-sm">
                                <option value="">— Default —</option>
                                <?php foreach ($locations as $l): ?>
                                    <option value="<?= $l['id'] ?>" <?= (old('location_id', $order['location_id'] ?? '') == $l['id']) ? 'selected' : '' ?>>
                                        <?= esc($l['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-text small text-muted">
                            Materials will be deducted from this warehouse/location when you issue them.
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> <?= $isEdit ? 'Update Order' : 'Create Order' ?>
                            </button>
                            <a href="<?= base_url('/subcontract-orders') ?>" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </div>
                </div>

                <!-- Info card -->
                <div class="card border-info">
                    <div class="card-body small text-muted">
                        <h6 class="text-info"><i class="bi bi-lightbulb me-1"></i>How it works</h6>
                        <ol class="mb-0 ps-3">
                            <li><strong>Create</strong> — Define the service, vendor, and materials</li>
                            <li><strong>Confirm</strong> — Lock the order details</li>
                            <li><strong>Issue Materials</strong> — Stock is deducted from warehouse</li>
                            <li><strong>Receive Back</strong> — Record received + scrap quantities</li>
                            <li><strong>Done</strong> — Stock restored (minus scrap)</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Line row template -->
<template id="lineRowTemplate">
    <tr class="line-row">
        <td>
            <select name="line_product_id[]" class="form-select form-select-sm line-product" required>
                <option value="">— Select Product —</option>
                <?php foreach ($storableProducts as $sp): ?>
                    <option value="<?= $sp['id'] ?>"><?= esc($sp['name']) ?> <?= !empty($sp['code']) ? '(' . esc($sp['code']) . ')' : '' ?></option>
                <?php endforeach; ?>
            </select>
            <select name="line_variant_id[]" class="form-select form-select-sm line-variant mt-1" data-selected-variant="">
                <option value="">— Any / Non-variant —</option>
            </select>
        </td>
        <td>
            <input type="text" name="line_description[]" class="form-control form-control-sm" placeholder="Optional description">
        </td>
        <td>
            <input type="number" name="line_qty_sent[]" class="form-control form-control-sm text-end" step="1" min="1" required placeholder="0">
        </td>
        <td>
            <button type="button" class="btn btn-sm btn-outline-danger remove-line-btn"><i class="bi bi-trash"></i></button>
        </td>
    </tr>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const linesBody  = document.getElementById('linesBody');
    const addLineBtn = document.getElementById('addLineBtn');
    const template   = document.getElementById('lineRowTemplate');
    const noLinesMsg = document.getElementById('noLinesMsg');
    const qtyInput   = document.getElementById('quantity');
    const priceInput  = document.getElementById('unit_price');
    const totalDisplay = document.getElementById('totalAmount');
    const serviceSelect = document.getElementById('service_product_id');
    const unitLabel     = document.getElementById('unitLabel');

    // Add line
    addLineBtn.addEventListener('click', function() {
        const clone = template.content.cloneNode(true);
        linesBody.appendChild(clone);
        const row = linesBody.lastElementChild;
        if (row) initLineRow(row);
        noLinesMsg.style.display = 'none';
    });

    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"'`]/g, function (s) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','`':'&#96;'})[s];
        });
    }

    function loadVariantsForRow(row) {
        const productSelect = row.querySelector('.line-product');
        const variantSelect = row.querySelector('.line-variant');
        if (!productSelect || !variantSelect) return;

        const selectedVariant = variantSelect.getAttribute('data-selected-variant') || variantSelect.value || '';
        const pid = parseInt(productSelect.value || '0', 10);

        variantSelect.innerHTML = '<option value="">— Any / Non-variant —</option>';
        if (!pid) {
            variantSelect.disabled = true;
            variantSelect.value = '';
            return;
        }

        variantSelect.disabled = false;
        fetch('<?= base_url('/subcontract-orders/product-variants') ?>/' + encodeURIComponent(pid), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(json => {
            const list = (json && json.success && Array.isArray(json.data)) ? json.data : [];
            if (!list.length) {
                variantSelect.innerHTML = '<option value="">— No variants (use product) —</option>';
                variantSelect.value = '';
                return;
            }

            list.forEach(v => {
                const opt = document.createElement('option');
                opt.value = String(v.id || '');
                opt.textContent = (v.label && String(v.label).trim())
                    ? v.label
                    : ((v.art_number || '') + ' ' + (v.name || '')).trim();
                variantSelect.appendChild(opt);
            });

            if (selectedVariant) {
                const wanted = String(selectedVariant);
                const hasWanted = Array.from(variantSelect.options).some(o => String(o.value) === wanted);
                if (hasWanted) {
                    variantSelect.value = wanted;
                }
            }
            variantSelect.setAttribute('data-selected-variant', '');
        })
        .catch(() => {
            variantSelect.innerHTML = '<option value="">— Variant load failed —</option>';
            variantSelect.value = '';
        });
    }

    function initLineRow(row) {
        const productSelect = row.querySelector('.line-product');
        if (!productSelect) return;
        productSelect.addEventListener('change', function () {
            const variantSelect = row.querySelector('.line-variant');
            if (variantSelect) variantSelect.setAttribute('data-selected-variant', '');
            loadVariantsForRow(row);
        });
        loadVariantsForRow(row);
    }

    // Initialize existing rows on edit
    linesBody.querySelectorAll('.line-row').forEach(initLineRow);

    // Remove line
    linesBody.addEventListener('click', function(e) {
        const btn = e.target.closest('.remove-line-btn');
        if (btn) {
            btn.closest('tr').remove();
            if (linesBody.children.length === 0) {
                noLinesMsg.style.display = '';
            }
        }
    });

    // Calculate total
    function updateTotal() {
        const qty   = parseFloat(qtyInput.value) || 0;
        const price = parseFloat(priceInput.value) || 0;
        totalDisplay.textContent = (qty * price).toFixed(2);
    }
    qtyInput.addEventListener('input', updateTotal);
    priceInput.addEventListener('input', updateTotal);
    updateTotal();

    // Update unit label and default price when service product changes
    serviceSelect.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        if (opt && opt.dataset.unit) {
            unitLabel.textContent = opt.dataset.unit;
        }
        if (opt && opt.dataset.price && !priceInput.value) {
            priceInput.value = parseFloat(opt.dataset.price).toFixed(2);
            updateTotal();
        }
    });
    // Trigger on load
    serviceSelect.dispatchEvent(new Event('change'));
});
</script>

<?= $this->endSection() ?>
