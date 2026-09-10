<?php
/**
 * Production / Preparation panel for a Sales Order.
 * One compact row per product (scales to many order lines). A step can only
 * be started once the base material is actually in stock — if it isn't, the
 * row is shown as blocked and points at the existing "Auto-Create RFQ" action
 * above instead of offering vendor/in-house buttons.
 * Expects: $preparationExecution = ['blocks' => [...], 'dest_locations' => [...]], $orderId.
 */
$peDestLocations = $preparationExecution['dest_locations'] ?? [];
$statusMeta = [
    'not_started' => ['label' => 'Not started', 'class' => 'secondary'],
    'in_progress' => ['label' => 'In progress', 'class' => 'warning'],
    'ready_for_qc' => ['label' => 'Ready for QC', 'class' => 'info'],
    'completed' => ['label' => 'Completed', 'class' => 'success'],
];
?>
<div class="so-panel-heading mb-2 d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div class="d-flex align-items-start gap-2">
        <span class="so-panel-icon"><i class="bi bi-gear-wide-connected"></i></span>
        <div><h5>Production / Preparation</h5><p>One row per product — actions only unlock once the material is in stock.</p></div>
    </div>
    <form method="post" action="<?= site_url('sales-orders/preparation/create-job-pos') ?>" class="d-inline">
        <?= csrf_field() ?>
        <input type="hidden" name="sales_order_id" value="<?= (int) $orderId ?>">
        <button type="submit" class="btn btn-sm btn-outline-primary" title="Bundle every un-invoiced vendor lot into one payable PO per vendor">
            <i class="bi bi-receipt me-1"></i>Create Job PO(s)
        </button>
    </form>
</div>

<?php if (! empty($vendorJobPos)): ?>
    <div class="mb-3">
        <div class="pe-req-section-label"><i class="bi bi-receipt-cutoff"></i> Vendor Job POs</div>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($vendorJobPos as $jobPo): ?>
                <?php
                    $jpStatus = strtolower((string)($jobPo['status'] ?? ''));
                    $jpBadge = $jpStatus === 'completed' ? 'success' : ($jpStatus === 'partial_received' ? 'warning' : 'secondary');
                ?>
                <a href="<?= site_url('new-purchase-orders/' . (int) $jobPo['id']) ?>" class="badge bg-<?= $jpBadge ?>-subtle text-<?= $jpBadge ?>-emphasis text-decoration-none p-2"
                   title="<?= (int) $jobPo['lot_count'] ?> lot(s)">
                    <?= esc($jobPo['po_number']) ?> — <?= esc($jobPo['vendor_name']) ?> — <?= number_format((float) $jobPo['total'], 2) ?> (<?= esc(ucfirst(str_replace('_', ' ', $jpStatus))) ?>)
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<style>
.pe-mini-stepper { display: flex; align-items: center; }
.pe-mini-stepper-dot {
    display: inline-flex; align-items: center; justify-content: center;
    width: 1.4rem; height: 1.4rem; border-radius: 50%; font-size: .7rem; font-weight: 600;
    background: #e9ecef; color: #6c757d; flex-shrink: 0;
}
.pe-mini-stepper-dot.is-done { background: #d1e7dd; color: #0f5132; }
.pe-mini-stepper-dot.is-current { background: #cfe2ff; color: #084298; }
.pe-mini-stepper-dot.is-blocked { background: #f8d7da; color: #842029; }
.pe-mini-stepper-arrow { color: #adb5bd; margin: 0 .2rem; font-size: .8rem; }

.pe-req-section-label {
    font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em;
    color: #6c757d; display: flex; align-items: center; gap: .4rem; margin-bottom: .5rem;
}
.pe-req-list { display: flex; flex-direction: column; gap: .4rem; }
.pe-req-empty { font-size: .82rem; color: #adb5bd; font-style: italic; padding: .25rem 0; }
.pe-req-item {
    display: flex; align-items: center; justify-content: space-between; gap: .75rem;
    padding: .5rem .65rem; border: 1px solid #eef0f2; border-radius: .5rem; background: #f8f9fa;
}
.pe-req-item-main { display: flex; align-items: center; gap: .55rem; min-width: 0; }
.pe-req-item-icon {
    width: 1.6rem; height: 1.6rem; border-radius: 50%; flex-shrink: 0;
    display: inline-flex; align-items: center; justify-content: center;
    background: #e7f1ff; color: #0d6efd; font-size: .8rem;
}
.pe-req-item-name { font-size: .83rem; font-weight: 600; color: #212529; }
.pe-req-item-meta { font-size: .74rem; color: #868e96; }
.pe-req-chips { display: flex; gap: .3rem; flex-wrap: wrap; justify-content: flex-end; }
.pe-req-chip {
    font-size: .68rem; font-weight: 600; padding: .18rem .5rem; border-radius: 999px;
    background: #e9ecef; color: #495057; white-space: nowrap;
}
.pe-req-chip.is-inhouse { background: #d1e7dd; color: #0f5132; }
.pe-req-chip.is-vendor { background: #cfe2ff; color: #084298; }
.pe-req-chip.is-optional { background: #fff3cd; color: #664d03; }
.pe-req-chip.is-missing { background: #f8d7da; color: #842029; }
</style>
<div class="table-responsive mb-4">
    <table class="table table-sm align-middle so-prep-exec-table">
        <thead>
            <tr>
                <th>Product</th>
                <th>Current step</th>
                <th>Material</th>
                <th>Status</th>
                <th class="text-end">Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach (($preparationExecution['blocks'] ?? []) as $block): ?>
            <?php
                $steps = $block['steps'];
                $activeIndex = null;
                foreach ($steps as $i => $row) {
                    if (empty($row['is_done'])) { $activeIndex = $i; break; }
                }
                $allDone = $activeIndex === null;
                $activeRow = $allDone ? null : $steps[$activeIndex];
                $meta = $statusMeta[$activeRow['status'] ?? 'completed'] ?? $statusMeta['not_started'];
            ?>
            <tr>
                <td>
                    <div class="fw-semibold">
                        <?= esc($block['product_name']) ?>
                        <?php
                            $profilePayload = [
                                'product_name' => $block['product_name'],
                                'profile_name' => $block['profile']['name'] ?? '',
                                'components' => array_map(function ($c) {
                                    return [
                                        'name' => $c['product_name'] ?? ('Product #' . $c['product_id']),
                                        'variant' => $c['variant_name'] ?? '',
                                        'qty_per_unit' => $c['qty_per_unit'],
                                        'is_optional' => ! empty($c['is_optional']),
                                    ];
                                }, $block['components']),
                                'steps' => array_map(function ($s) {
                                    return [
                                        'name' => $s['step']['name'],
                                        'allow_inhouse' => $s['allow_inhouse'],
                                        'vendor_options' => $s['vendor_options'],
                                    ];
                                }, $steps),
                            ];
                        ?>
                        <button type="button" class="cl-action-icon pe-open-profile" title="View required materials &amp; services"
                                data-payload='<?= esc(json_encode($profilePayload), 'attr') ?>'>
                            <i class="bi bi-info-circle"></i>
                        </button>
                        <button type="button" class="cl-action-icon pe-open-trail" title="Movement trail — where every piece went"
                                data-url="<?= site_url('sales-orders/preparation/trail/' . (int) $orderId . '/' . (int) $block['product_id']) ?>"
                                data-product-name="<?= esc($block['product_name'], 'attr') ?>">
                            <i class="bi bi-signpost-split"></i>
                        </button>
                    </div>
                    <?php if (!empty($block['product_code'])): ?><div class="text-muted small"><?= esc($block['product_code']) ?></div><?php endif; ?>
                </td>
                <td>
                    <div class="pe-mini-stepper" title="<?= esc($block['material_ready'] ? 'Material in stock' : 'Material short: ' . number_format($block['shortage'], 2)) ?>">
                        <span class="pe-mini-stepper-dot <?= $block['material_ready'] ? 'is-done' : 'is-blocked' ?>">
                            <i class="bi <?= $block['material_ready'] ? 'bi-check' : 'bi-x' ?>"></i>
                        </span>
                        <?php foreach ($steps as $i => $stepRow): ?>
                            <span class="pe-mini-stepper-arrow">&rarr;</span>
                            <?php
                                $dotState = ! empty($stepRow['is_done']) ? 'is-done' : ($i === $activeIndex ? 'is-current' : 'is-pending');
                            ?>
                            <span class="pe-mini-stepper-dot <?= $dotState ?>" title="<?= esc($stepRow['step']['name']) ?>">
                                <?= $dotState === 'is-done' ? '<i class="bi bi-check"></i>' : ($i + 1) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-muted small mt-1">
                        <?= $allDone ? 'All ' . count($steps) . ' step(s) complete' : esc($activeRow['step']['name']) ?>
                        <?php if (! $allDone && ($activeRow['done_qty'] > 0 || $activeRow['open_qty'] > 0)): ?>
                            &middot; <?= number_format((float) $activeRow['done_qty'], 2) ?>/<?= number_format((float) $block['line_qty'], 2) ?> done<?php
                            if ($activeRow['open_qty'] > 0): ?>, <?= number_format((float) $activeRow['open_qty'], 2) ?> at vendor<?php endif; ?>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <?php if ($block['material_ready']): ?>
                        <span class="badge bg-success-subtle text-success border">In stock</span>
                    <?php else: ?>
                        <span class="badge bg-danger-subtle text-danger border">Short <?= number_format($block['shortage'], 2) ?></span>
                    <?php endif; ?>
                    <?php if (! empty($block['material_name'])): ?>
                        <div class="text-muted small mt-1">
                            <?= esc($block['material_name']) ?>
                            <?php if (! empty($block['material_code'])): ?>(<?= esc($block['material_code']) ?>)<?php endif; ?>
                            &middot; need <?= number_format((float) $block['material_needed'], 2) ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!$allDone): ?>
                        <span class="badge bg-<?= $meta['class'] ?>-subtle text-<?= $meta['class'] ?> border"><?= $meta['label'] ?></span>
                    <?php else: ?>
                        <span class="badge bg-success-subtle text-success border">Done</span>
                    <?php endif; ?>
                </td>
                <td class="text-end">
                    <?php if ($allDone): ?>
                        <span class="text-muted small">—</span>
                    <?php else: ?>
                        <?php if ($activeRow['open_qty'] > 0 && ($activeRow['record']['status'] ?? '') !== 'completed'): ?>
                            <form method="post" action="<?= site_url('sales-orders/preparation/complete-step') ?>" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="sales_order_id" value="<?= (int) $orderId ?>">
                                <input type="hidden" name="record_id" value="<?= (int) ($activeRow['record']['id'] ?? 0) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-success"
                                        title="Close this step for <?= number_format((float) $activeRow['open_qty'], 2) ?> pcs — vendor work opens the receive form">
                                    <i class="bi bi-check2-circle"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                        <?php
                            $blocked = ! $block['material_ready'] || $activeRow['remaining_qty'] <= 0.0001;
                            $blockedTitle = $activeRow['remaining_qty'] <= 0.0001
                                ? 'Whole quantity is already with the vendor / done — nothing left to send'
                                : 'Needs ' . number_format($block['shortage'], 2) . ' more in stock before this step can start — use Auto-Create RFQ above';
                            $payload = [
                                'sales_order_id' => (int) $orderId,
                                'sales_order_line_id' => (int) $block['sales_order_line_id'],
                                'product_id' => (int) $block['product_id'],
                                'product_name' => $block['product_name'],
                                'material_product_id' => (int) $block['material_product_id'],
                                'material_name' => $block['material_name'],
                                'step_id' => (int) $activeRow['step']['id'],
                                'step_name' => $activeRow['step']['name'],
                                'line_qty' => $block['line_qty'],
                                'remaining_qty' => $activeRow['remaining_qty'],
                                'stock_locations' => $block['stock_locations'],
                                'vendor_options' => $activeRow['vendor_options'],
                            ];
                        ?>
                        <?php if ($activeRow['allow_inhouse']): ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary<?= $blocked ? ' disabled' : ' pe-open-inhouse' ?>"
                                title="<?= $blocked ? esc($blockedTitle) : 'Start in-house' ?>"
                                <?= $blocked ? 'disabled aria-disabled="true"' : '' ?>
                                data-payload='<?= esc(json_encode($payload), 'attr') ?>'>
                                <i class="bi bi-house-gear"></i>
                            </button>
                        <?php endif; ?>
                        <?php if (! empty($activeRow['vendor_options'])): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary<?= $blocked ? ' disabled' : ' pe-open-vendor' ?>"
                                title="<?= $blocked ? esc($blockedTitle) : 'Send to vendor' ?>"
                                <?= $blocked ? 'disabled aria-disabled="true"' : '' ?>
                                data-payload='<?= esc(json_encode($payload), 'attr') ?>'>
                                <i class="bi bi-truck"></i>
                            </button>
                        <?php endif; ?>
                        <?php if (! $activeRow['allow_inhouse'] && empty($activeRow['vendor_options'])): ?>
                            <span class="text-muted small">No route configured</span>
                        <?php elseif ($blocked && ! $block['material_ready']): ?>
                            <div class="text-danger small mt-1">Short <?= number_format($block['shortage'], 2) ?> — RFQ needed</div>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php foreach (($block['open_shipments'] ?? []) as $shipment): ?>
            <tr class="pe-shipment-row">
                <td class="text-muted small ps-4">
                    <i class="bi bi-truck me-1"></i>At <?= esc($shipment['vendor_name'] ?? 'vendor') ?>
                    <div class="text-muted small"><?= esc($shipment['reference_no']) ?></div>
                </td>
                <td class="text-muted small" colspan="2">
                    <?= esc($shipment['step_name'] ?? '-') ?>
                    &middot; <?= number_format((float) $shipment['outstanding_qty'], 2) ?> of <?= number_format((float) $shipment['qty'], 2) ?> pcs still there
                </td>
                <td><span class="badge bg-info-subtle text-info border">With vendor</span></td>
                <td class="text-end">
                    <a class="btn btn-sm btn-outline-secondary" target="_blank"
                       href="<?= site_url('vendor-receive/' . (int) $shipment['id'] . '/send-slip') ?>"
                       title="Print the send note"><i class="bi bi-printer"></i></a>
                    <a class="btn btn-sm btn-outline-success" href="<?= site_url('vendor-receive/' . (int) $shipment['id']) ?>"
                       title="Receive back &amp; run QC"><i class="bi bi-box-arrow-in-down"></i></a>
                    <?php if (! empty($shipment['vendor_options'])): ?>
                        <button type="button" class="btn btn-sm btn-outline-warning pe-open-reroute"
                                title="Vendor has no time — collect and hand to another vendor"
                                data-payload='<?= esc(json_encode([
                                    'send_note_id' => (int) $shipment['id'],
                                    'reference_no' => $shipment['reference_no'],
                                    'vendor_name' => $shipment['vendor_name'],
                                    'outstanding_qty' => (float) $shipment['outstanding_qty'],
                                    'vendor_options' => $shipment['vendor_options'],
                                ]), 'attr') ?>'>
                            <i class="bi bi-arrow-left-right"></i>
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Shared modal: start in-house (populated by JS per row, so markup isn't repeated per product) -->
<div class="modal fade" id="peInhouseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" action="<?= site_url('sales-orders/preparation/start-inhouse') ?>" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="sales_order_id" value="<?= (int) $orderId ?>">
            <input type="hidden" name="sales_order_line_id" id="pe_ih_line_id">
            <input type="hidden" name="product_id" id="pe_ih_product_id">
            <input type="hidden" name="step_id" id="pe_ih_step_id">
            <div class="modal-header">
                <h6 class="modal-title">Start in-house: <span id="pe_ih_title"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label small mb-0">Quantity</label>
                    <input type="number" step="0.0001" min="0.0001" name="qty" id="pe_ih_qty" class="form-control form-control-sm" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-0">Location (has stock)</label>
                    <select name="location_id" id="pe_ih_location" class="form-select form-select-sm" required></select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary">Start</button>
            </div>
        </form>
    </div>
</div>

<!-- Shared modal: send to vendor -->
<div class="modal fade" id="peVendorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" action="<?= site_url('sales-orders/preparation/send-to-vendor') ?>" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="sales_order_id" value="<?= (int) $orderId ?>">
            <input type="hidden" name="sales_order_line_id" id="pe_v_line_id">
            <input type="hidden" name="product_id" id="pe_v_product_id">
            <input type="hidden" name="material_product_id" id="pe_v_material_product_id">
            <input type="hidden" name="step_id" id="pe_v_step_id">
            <input type="hidden" name="unit_price" id="pe_v_unit_price">
            <div class="modal-header">
                <h6 class="modal-title">Send to vendor: <span id="pe_v_title"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label small mb-0">Vendor</label>
                    <select name="vendor_id" id="pe_v_vendor" class="form-select form-select-sm" required></select>
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-0">Quantity</label>
                    <input type="number" step="0.0001" min="0.0001" name="qty" id="pe_v_qty" class="form-control form-control-sm" required>
                </div>
                <div class="text-muted small mb-2" id="pe_v_material_hint"></div>
                <div class="mb-2">
                    <label class="form-label small mb-0">From location (has stock)</label>
                    <select name="from_location_id" id="pe_v_from" class="form-select form-select-sm" required></select>
                </div>
                <div class="mb-2">
                    <div class="d-flex justify-content-between align-items-end">
                        <label class="form-label small mb-0">Vendor drop-off location</label>
                        <button type="button" class="btn btn-link btn-sm p-0" id="pe_v_add_loc_toggle">+ Add location</button>
                    </div>
                    <select name="to_location_id" id="pe_v_to" class="form-select form-select-sm" required></select>
                    <div class="input-group input-group-sm mt-2 d-none" id="pe_v_add_loc_box">
                        <input type="text" class="form-control" id="pe_v_new_loc_name" placeholder="Branch / office / setup name">
                        <button type="button" class="btn btn-outline-primary" id="pe_v_add_loc_save">Save</button>
                    </div>
                    <div class="small text-danger mt-1 d-none" id="pe_v_add_loc_error"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary">Send</button>
            </div>
        </form>
    </div>
</div>

<!-- Shared modal: preparation profile requirements (materials + steps/services) -->
<div class="modal fade" id="peProfileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content pe-profile-modal">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h6 class="modal-title mb-0" id="pe_p_title"></h6>
                    <div class="text-muted small" id="pe_p_subtitle"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="pe-req-section-label"><i class="bi bi-box-seam"></i> Required materials</div>
                <div id="pe_p_materials" class="pe-req-list mb-3"></div>

                <div class="pe-req-section-label"><i class="bi bi-diagram-3"></i> Steps &amp; services</div>
                <div id="pe_p_steps" class="pe-req-list"></div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Shared modal: collect from one vendor and hand to another (no QC, work never happened) -->
<div class="modal fade" id="peRerouteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" action="<?= site_url('sales-orders/preparation/reroute-to-vendor') ?>" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="send_note_id" id="pe_rr_send_note_id">
            <div class="modal-header">
                <h6 class="modal-title">Reroute to another vendor</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-muted small mb-2" id="pe_rr_hint"></div>
                <div class="mb-2">
                    <label class="form-label small mb-0">New vendor</label>
                    <select name="vendor_id" id="pe_rr_vendor" class="form-select form-select-sm" required></select>
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-0">Quantity to move</label>
                    <input type="number" step="0.0001" min="0.0001" name="qty" id="pe_rr_qty" class="form-control form-control-sm" required>
                    <div class="form-text">Partial is fine — the rest stays with the current vendor.</div>
                </div>
                <div class="mb-2">
                    <div class="d-flex justify-content-between align-items-end">
                        <label class="form-label small mb-0">New vendor drop-off location</label>
                        <button type="button" class="btn btn-link btn-sm p-0" id="pe_rr_add_loc_toggle">+ Add location</button>
                    </div>
                    <select name="to_location_id" id="pe_rr_to" class="form-select form-select-sm" required></select>
                    <div class="input-group input-group-sm mt-2 d-none" id="pe_rr_add_loc_box">
                        <input type="text" class="form-control" id="pe_rr_new_loc_name" placeholder="Branch / office / setup name">
                        <button type="button" class="btn btn-outline-primary" id="pe_rr_add_loc_save">Save</button>
                    </div>
                    <div class="small text-danger mt-1 d-none" id="pe_rr_add_loc_error"></div>
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-0">Reason (optional)</label>
                    <input type="text" name="reason" class="form-control form-control-sm" placeholder="e.g. vendor out of capacity">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-warning">Move to new vendor</button>
            </div>
        </form>
    </div>
</div>

<!-- Shared modal: full movement trail for one product -->
<div class="modal fade" id="peTrailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Movement trail: <span id="pe_t_title"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="pe_t_body"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    // Only places that belong to a vendor; the picker narrows this to the vendor
    // selected on the form, and new ones are appended after being saved.
    var peVendorLocations = <?= json_encode(array_map(static function ($l) {
        return [
            'id' => (int) $l['id'],
            'name' => $l['name'],
            'vendor_id' => (int) $l['vendor_id'],
            'warehouse_name' => $l['warehouse_name'] ?? '',
        ];
    }, $peDestLocations)) ?>;
    var peAddLocationUrl = '<?= site_url('sales-orders/preparation/add-vendor-location') ?>';
    var peCsrfName = '<?= csrf_token() ?>';
    var peCsrfHash = '<?= csrf_hash() ?>';

    function fillLocationsFor(vendorId, selectId) {
        var select = document.getElementById(selectId);
        var matches = peVendorLocations.filter(function (l) { return l.vendor_id === parseInt(vendorId, 10); });
        select.innerHTML = matches.length
            ? '<option value="">Select…</option>'
            : '<option value="">No location saved for this vendor — add one</option>';
        matches.forEach(function (l) {
            var opt = document.createElement('option');
            opt.value = l.id;
            opt.textContent = l.warehouse_name ? l.warehouse_name + ' · ' + l.name : l.name;
            select.appendChild(opt);
        });
    }

    function fillVendorLocationSelect(vendorId) { fillLocationsFor(vendorId, 'pe_v_to'); }

    function fillLocationSelect(select, locations, emptyLabel) {
        select.innerHTML = '<option value="">' + emptyLabel + '</option>';
        locations.forEach(function (loc) {
            var opt = document.createElement('option');
            opt.value = loc.location_id;
            opt.textContent = (loc.warehouse_name || '') + ' · ' + loc.location_name + ' (' + parseFloat(loc.qty).toFixed(2) + ' available)';
            select.appendChild(opt);
        });
    }

    document.querySelectorAll('.pe-open-inhouse').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var data = JSON.parse(btn.getAttribute('data-payload'));
            document.getElementById('pe_ih_title').textContent = data.product_name + ' — ' + data.step_name;
            document.getElementById('pe_ih_line_id').value = data.sales_order_line_id;
            document.getElementById('pe_ih_product_id').value = data.product_id;
            document.getElementById('pe_ih_step_id').value = data.step_id;
            var ihRemaining = data.remaining_qty != null ? data.remaining_qty : data.line_qty;
            document.getElementById('pe_ih_qty').value = ihRemaining;
            document.getElementById('pe_ih_qty').max = ihRemaining;
            fillLocationSelect(document.getElementById('pe_ih_location'), data.stock_locations, 'Select…');
            new bootstrap.Modal(document.getElementById('peInhouseModal')).show();
        });
    });

    document.querySelectorAll('.pe-open-vendor').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var data = JSON.parse(btn.getAttribute('data-payload'));
            document.getElementById('pe_v_title').textContent = data.product_name + ' — ' + data.step_name;
            document.getElementById('pe_v_line_id').value = data.sales_order_line_id;
            document.getElementById('pe_v_product_id').value = data.product_id;
            document.getElementById('pe_v_material_product_id').value = data.material_product_id || data.product_id;
            document.getElementById('pe_v_step_id').value = data.step_id;
            document.getElementById('pe_v_material_hint').textContent = data.material_name
                ? 'Shipping material: ' + data.material_name
                : '';
            var vRemaining = data.remaining_qty != null ? data.remaining_qty : data.line_qty;
            document.getElementById('pe_v_qty').value = vRemaining;
            document.getElementById('pe_v_qty').max = vRemaining;
            fillLocationSelect(document.getElementById('pe_v_from'), data.stock_locations, 'Select…');

            var vendorSelect = document.getElementById('pe_v_vendor');
            var priceInput = document.getElementById('pe_v_unit_price');
            vendorSelect.innerHTML = '';
            data.vendor_options.forEach(function (v) {
                var opt = document.createElement('option');
                opt.value = v.vendor_id;
                opt.textContent = v.vendor_name + (v.service_price ? ' (' + v.currency + ' ' + parseFloat(v.service_price).toFixed(2) + '/unit)' : '');
                opt.dataset.price = v.service_price || 0;
                if (v.is_default) opt.selected = true;
                vendorSelect.appendChild(opt);
            });
            priceInput.value = vendorSelect.selectedOptions[0] ? vendorSelect.selectedOptions[0].dataset.price : 0;
            fillVendorLocationSelect(vendorSelect.value);
            vendorSelect.onchange = function () {
                priceInput.value = vendorSelect.selectedOptions[0] ? vendorSelect.selectedOptions[0].dataset.price : 0;
                fillVendorLocationSelect(vendorSelect.value);
            };

            new bootstrap.Modal(document.getElementById('peVendorModal')).show();
        });
    });

    // Inline "add a location for this vendor" — one vendor can have several
    // branches/offices/setups, so the list grows from wherever it is needed.
    function wireAddLocation(toggleId, boxId, nameId, saveId, errorId, vendorSelectId, targetSelectId) {
        var toggle = document.getElementById(toggleId);
        if (! toggle) { return; }
        var box = document.getElementById(boxId);
        var error = document.getElementById(errorId);
        var nameInput = document.getElementById(nameId);

        toggle.addEventListener('click', function () {
            box.classList.toggle('d-none');
            if (! box.classList.contains('d-none')) { nameInput.focus(); }
        });

        document.getElementById(saveId).addEventListener('click', function () {
            var vendorId = document.getElementById(vendorSelectId).value;
            var name = nameInput.value.trim();
            error.classList.add('d-none');
            if (! vendorId || ! name) {
                error.textContent = 'Pick a vendor and type a location name.';
                error.classList.remove('d-none');
                return;
            }

            var body = new FormData();
            body.append('vendor_id', vendorId);
            body.append('name', name);
            body.append(peCsrfName, peCsrfHash);

            fetch(peAddLocationUrl, { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (! res.ok) {
                        error.textContent = res.message || 'Could not save the location.';
                        error.classList.remove('d-none');
                        return;
                    }
                    peVendorLocations.push(res.location);
                    fillLocationsFor(vendorId, targetSelectId);
                    document.getElementById(targetSelectId).value = res.location.id;
                    nameInput.value = '';
                    box.classList.add('d-none');
                })
                .catch(function () {
                    error.textContent = 'Could not save the location.';
                    error.classList.remove('d-none');
                });
        });
    }

    wireAddLocation('pe_v_add_loc_toggle', 'pe_v_add_loc_box', 'pe_v_new_loc_name', 'pe_v_add_loc_save', 'pe_v_add_loc_error', 'pe_v_vendor', 'pe_v_to');


    function peEscapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    function peReqItem(iconClass, name, meta, chipsHtml) {
        name = peEscapeHtml(name);
        meta = peEscapeHtml(meta);
        var div = document.createElement('div');
        div.className = 'pe-req-item';
        div.innerHTML =
            '<div class="pe-req-item-main">' +
                '<span class="pe-req-item-icon"><i class="bi ' + iconClass + '"></i></span>' +
                '<div class="min-w-0">' +
                    '<div class="pe-req-item-name">' + name + '</div>' +
                    (meta ? '<div class="pe-req-item-meta">' + meta + '</div>' : '') +
                '</div>' +
            '</div>' +
            '<div class="pe-req-chips">' + chipsHtml + '</div>';
        return div;
    }

    document.querySelectorAll('.pe-open-profile').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var data = JSON.parse(btn.getAttribute('data-payload'));
            document.getElementById('pe_p_title').textContent = data.product_name;
            document.getElementById('pe_p_subtitle').textContent = data.profile_name ? 'Profile: ' + data.profile_name : '';

            var materialsEl = document.getElementById('pe_p_materials');
            materialsEl.innerHTML = '';
            if (data.components.length === 0) {
                materialsEl.innerHTML = '<div class="pe-req-empty">No materials configured for this profile</div>';
            } else {
                data.components.forEach(function (c) {
                    var meta = parseFloat(c.qty_per_unit).toFixed(4) + ' per unit' + (c.variant ? ' · ' + c.variant : '');
                    var chips = c.is_optional ? '<span class="pe-req-chip is-optional">Optional</span>' : '';
                    materialsEl.appendChild(peReqItem('bi-box-seam', c.name, meta, chips));
                });
            }

            var stepsEl = document.getElementById('pe_p_steps');
            stepsEl.innerHTML = '';
            if (data.steps.length === 0) {
                stepsEl.innerHTML = '<div class="pe-req-empty">No steps configured for this profile</div>';
            } else {
                data.steps.forEach(function (s) {
                    var chips = '';
                    if (s.allow_inhouse) chips += '<span class="pe-req-chip is-inhouse">In-house</span>';
                    s.vendor_options.forEach(function (v) {
                        chips += '<span class="pe-req-chip is-vendor">' + peEscapeHtml(v.vendor_name) + '</span>';
                    });
                    if (!chips) chips = '<span class="pe-req-chip is-missing">No route configured</span>';
                    stepsEl.appendChild(peReqItem('bi-gear-wide-connected', s.name, '', chips));
                });
            }

            new bootstrap.Modal(document.getElementById('peProfileModal')).show();
        });
    });

    // --- Reroute: vendor ran out of time, goods go straight to another vendor ---
    document.querySelectorAll('.pe-open-reroute').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var data = JSON.parse(btn.getAttribute('data-payload'));
            document.getElementById('pe_rr_send_note_id').value = data.send_note_id;
            document.getElementById('pe_rr_hint').textContent =
                data.outstanding_qty.toFixed(2) + ' pcs are with ' + data.vendor_name + ' on ' + data.reference_no + '.';
            var qty = document.getElementById('pe_rr_qty');
            qty.value = data.outstanding_qty;
            qty.max = data.outstanding_qty;

            var vendorSelect = document.getElementById('pe_rr_vendor');
            vendorSelect.innerHTML = '<option value="">Select…</option>';
            data.vendor_options.forEach(function (v) {
                var opt = document.createElement('option');
                opt.value = v.vendor_id;
                opt.textContent = v.vendor_name;
                vendorSelect.appendChild(opt);
            });
            fillLocationsFor(vendorSelect.value, 'pe_rr_to');
            vendorSelect.onchange = function () { fillLocationsFor(vendorSelect.value, 'pe_rr_to'); };

            new bootstrap.Modal(document.getElementById('peRerouteModal')).show();
        });
    });

    wireAddLocation('pe_rr_add_loc_toggle', 'pe_rr_add_loc_box', 'pe_rr_new_loc_name', 'pe_rr_add_loc_save', 'pe_rr_add_loc_error', 'pe_rr_vendor', 'pe_rr_to');

    // --- Movement trail ---
    document.querySelectorAll('.pe-open-trail').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var body = document.getElementById('pe_t_body');
            document.getElementById('pe_t_title').textContent = btn.dataset.productName || '';
            body.innerHTML = '<div class="text-muted small">Loading…</div>';
            new bootstrap.Modal(document.getElementById('peTrailModal')).show();
            fetch(btn.dataset.url)
                .then(function (r) { return r.text(); })
                .then(function (html) { body.innerHTML = html; })
                .catch(function () { body.innerHTML = '<p class="text-danger mb-0">Failed to load the trail.</p>'; });
        });
    });

})();
</script>
