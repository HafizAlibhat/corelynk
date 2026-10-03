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
$peReservations = $preparationExecution['reservations'] ?? [];
$peAllVendors = $preparationExecution['all_vendors'] ?? [];
$lineReservations = [];
foreach ($peReservations as $res) {
    $lineReservations[(int)($res['sales_order_line_id'] ?? 0)][] = $res;
}
$statusMeta = [
    'not_started' => ['label' => 'Not started', 'class' => 'secondary'],
    'in_progress' => ['label' => 'In progress', 'class' => 'warning'],
    'ready_for_qc' => ['label' => 'Ready for QC', 'class' => 'info'],
    'completed' => ['label' => 'Completed', 'class' => 'success'],
];
$prepBlocks = $preparationExecution['blocks'] ?? [];
$materialSummary = [];
foreach ($prepBlocks as $prepBlock) {
    $blockMaterials = $prepBlock['required_materials'] ?? [];
    if (empty($blockMaterials) && !empty($prepBlock['material_product_id'])) {
        $blockMaterials[] = [
            'product_id' => $prepBlock['material_product_id'],
            'variant_id' => null,
            'name' => $prepBlock['material_name'] ?? 'Material',
            'code' => $prepBlock['material_code'] ?? '',
            'needed' => (float)($prepBlock['material_needed'] ?? 0),
            'available' => max(0.0, (float)($prepBlock['material_needed'] ?? 0) - (float)($prepBlock['shortage'] ?? 0)),
        ];
    }
    foreach ($blockMaterials as $material) {
        $materialKey = (int)($material['product_id'] ?? 0) . ':' . (int)($material['variant_id'] ?? 0);
        if (!isset($materialSummary[$materialKey])) {
            $materialSummary[$materialKey] = [
                'product_id' => (int) ($material['product_id'] ?? 0),
                'variant_id' => (int) ($material['variant_id'] ?? 0) ?: null,
                'name' => $material['name'] ?? 'Material',
                'code' => $material['code'] ?? '',
                'needed' => 0.0,
                'available' => 0.0,
                'on_hand' => 0.0,
                'reserved' => 0.0,
                'reserved_for_order' => 0.0,
                'locations' => [],
                'shortage' => 0.0,
                'rows' => 0,
            ];
        }
        $materialSummary[$materialKey]['needed'] += (float)($material['needed'] ?? 0);
        $materialSummary[$materialKey]['available'] = max(
            $materialSummary[$materialKey]['available'],
            (float)($material['available'] ?? 0)
        );
        $materialSummary[$materialKey]['on_hand'] = max($materialSummary[$materialKey]['on_hand'], (float) ($material['on_hand'] ?? 0));
        $materialSummary[$materialKey]['reserved'] = max($materialSummary[$materialKey]['reserved'], (float) ($material['reserved'] ?? 0));
        $materialSummary[$materialKey]['reserved_for_order'] += (float) ($material['reserved_for_order'] ?? 0);
        foreach (($material['stock_locations'] ?? []) as $location) {
            $materialSummary[$materialKey]['locations'][(int) ($location['location_id'] ?? 0)] = $location;
        }
        $materialSummary[$materialKey]['rows']++;
    }
}
foreach ($materialSummary as &$materialRow) {
    $materialRow['shortage'] = max(0.0, $materialRow['needed'] - $materialRow['available']);
}
unset($materialRow);
$materialSummary = array_values($materialSummary);
$materialSummaryVisibleCount = count($materialSummary);

$totalOrderReserved = 0.0;
if (! empty($peReservations)) {
    foreach ($peReservations as $res) {
        $rem = max(0.0, (float)($res['reserved_quantity'] ?? 0) - (float)($res['consumed_quantity'] ?? 0) - (float)($res['released_quantity'] ?? 0));
        if ($rem > 0.0001) {
            $totalOrderReserved += $rem;
        }
    }
}
?>
<div class="so-prep-shell">
<div class="so-panel-heading so-prep-heading so-prep-heading-compact mb-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="so-prep-title-wrap">
        <span class="so-prep-title-icon"><i class="bi bi-gear-wide-connected"></i></span>
        <div class="d-flex align-items-center flex-wrap gap-2">
            <span class="so-prep-title-text">Production / Preparation</span>
            <span class="so-prep-title-meta d-none d-lg-inline">&middot; Actions unlock once material is in stock</span>
            <?php if ($totalOrderReserved > 0.0001): ?>
                <div class="pe-header-alloc-pill" title="Total Active Allocations: <?= number_format($totalOrderReserved, 0) ?> pcs reserved for this sales order">
                    <span class="pe-active-res-icon"><i class="bi bi-shield-lock-fill"></i></span>
                    <span class="pe-active-res-title text-nowrap">Active Allocations</span>
                    <span class="pe-alloc-total-pill text-nowrap">
                        <i class="bi bi-lock-fill me-1"></i><?= number_format($totalOrderReserved, 0) ?> pcs
                    </span>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="d-flex align-items-center gap-1.5 flex-wrap">
        <?php
            $hasAnyVendorShipments = false;
            foreach ($prepBlocks as $b) {
                if (! empty($b['open_shipments']) || ! empty($b['cancelled_shipments'])) {
                    $hasAnyVendorShipments = true;
                    break;
                }
            }
        ?>
        <?php if ($hasAnyVendorShipments): ?>
            <a href="<?= site_url('vendor-receive/order/' . (int) $orderId . '/combined-send-slip') ?>" target="_blank"
               class="btn btn-sm btn-outline-primary py-1 px-2" title="Print combined send receipt for all lots in this order" style="font-size:0.75rem;">
                <i class="bi bi-printer me-1"></i>Combined Send Slip
            </a>
            <a href="<?= site_url('vendor-receive/order/' . (int) $orderId . '/lot-stickers') ?>" target="_blank"
               class="btn btn-sm btn-outline-secondary py-1 px-2" title="Print pack/lot stickers for every bundle in this order" style="font-size:0.75rem;">
                <i class="bi bi-tags me-1"></i>Print Lot Stickers
            </a>
        <?php endif; ?>
        <?php if (!empty($canQuickStock) && !empty($hasShortage)): ?>
            <button type="button" class="btn btn-sm btn-success js-so-quick-stock py-1 px-2" data-line-id="all" title="Quick add missing stock for items with shortage" style="font-size:0.75rem;">
                <i class="bi bi-box-arrow-in-down me-1"></i>Add Missing Stock
            </button>
        <?php endif; ?>
        <?php if (! empty($hasUninvoicedVendorLots)): ?>
        <form method="post" action="<?= site_url('sales-orders/preparation/create-job-pos') ?>" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="sales_order_id" value="<?= (int) $orderId ?>">
            <button type="submit" class="btn btn-sm btn-outline-primary py-1 px-2" title="Bundle every un-invoiced vendor lot into one payable PO per vendor" style="font-size:0.75rem;">
                <i class="bi bi-receipt me-1"></i>Create Job PO(s)
            </button>
        </form>
        <?php else: ?>
        <button type="button" class="btn btn-sm btn-outline-secondary disabled py-1 px-2" disabled
                title="All vendor lots for this order already have a PO — nothing left to bundle" style="font-size:0.75rem;">
            <i class="bi bi-check2 me-1"></i>Job PO(s) up to date
        </button>
        <?php endif; ?>
        <button class="btn btn-sm btn-outline-secondary so-prep-collapse-toggle py-1 px-2" type="button"
                data-bs-toggle="collapse" data-bs-target="#soPrepExecutionBody"
                aria-expanded="true" aria-controls="soPrepExecutionBody" style="font-size:0.75rem;">
            <i class="bi bi-chevron-up me-1"></i><span>Close</span>
        </button>
    </div>
</div>

<div class="collapse show" id="soPrepExecutionBody">

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
/* ── Ultra-Compact Apple-Grade Material Summary & Header ── */
.so-prep-heading-compact {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.4rem 0.65rem;
    margin-bottom: 0.45rem;
    background: rgba(138, 114, 240, 0.06);
    border: 1px solid rgba(138, 114, 240, 0.15);
    border-radius: 8px;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.so-prep-title-wrap {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
}
.so-prep-title-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 6px;
    background: rgba(138, 114, 240, 0.18);
    color: #cfc4ff;
    font-size: 0.8rem;
    flex-shrink: 0;
}
.so-prep-title-text {
    font-size: 0.84rem;
    font-weight: 700;
    color: var(--bs-body-color, #f8f7ff);
    margin: 0;
    line-height: 1.2;
}
.so-prep-title-meta {
    font-size: 0.7rem;
    color: var(--bs-secondary-color, #817c90);
    margin-left: 0.25rem;
}

.cl-sales-order-detail .so-prep-material-summary.is-compact {
    display: flex !important;
    flex-direction: column !important;
    gap: 0.35rem !important;
    padding: 0.5rem 0.65rem !important;
    border-radius: 8px !important;
    margin-bottom: 0.6rem !important;
    border: 1px solid rgba(138, 114, 240, 0.18) !important;
    background: rgba(18, 15, 36, 0.95) !important;
}
.pe-mat-strip-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    font-size: 0.72rem;
}
.pe-mat-strip-title {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #a5b4fc;
    font-size: 0.68rem;
}

.so-prep-material-list.is-compact {
    display: grid !important;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)) !important;
    gap: 0.45rem !important;
}

.so-prep-material-item.is-compact {
    display: flex !important;
    flex-direction: column !important;
    justify-content: flex-start !important;
    padding: 0.45rem 0.6rem !important;
    border-radius: 6px !important;
    background: rgba(255, 255, 255, 0.02) !important;
    border: 1px solid rgba(138, 114, 240, 0.15) !important;
    gap: 0.45rem !important;
    transition: all 0.12s ease;
}
.so-prep-material-item.is-compact:hover {
    background: rgba(255, 255, 255, 0.04) !important;
    border-color: rgba(138, 114, 240, 0.3) !important;
}
.so-prep-material-item.is-short {
    border-left: 3px solid #ef4444 !important;
}
.so-prep-material-item.is-ok {
    border-left: 3px solid #10b981 !important;
}

.pe-mat-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.4rem;
    min-width: 0;
    width: 100%;
    height: 22px;
}
.pe-mat-card-name-wrap {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    min-width: 0;
    overflow: hidden;
}
.pe-mat-card-name {
    font-size: 0.76rem !important;
    font-weight: 600 !important;
    color: var(--bs-body-color, #f8f7ff) !important;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.pe-mat-card-code {
    font-size: 0.65rem !important;
    padding: 0.05rem 0.35rem !important;
    border-radius: 999px;
    background: rgba(138, 114, 240, 0.12);
    color: #cfc4ff;
    font-weight: 700;
    flex-shrink: 0;
}
.pe-mat-card-badge {
    flex-shrink: 0;
    margin-left: auto;
    font-size: 0.65rem !important;
    padding: 0.12rem 0.38rem !important;
}

.pe-mat-kpi-bar {
    display: flex;
    align-items: center;
    gap: 0.28rem;
    flex-wrap: wrap;
}
.pe-mat-kpi {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.1rem 0.35rem;
    border-radius: 4px;
    background: rgba(0, 0, 0, 0.25);
    border: 1px solid rgba(255, 255, 255, 0.06);
    font-size: 0.68rem;
    line-height: 1.2;
    font-variant-numeric: tabular-nums;
}
.pe-mat-kpi-lbl {
    font-size: 0.58rem;
    text-transform: uppercase;
    color: var(--bs-secondary-color, #817c90);
    font-weight: 600;
}
.pe-mat-kpi-val {
    font-weight: 700;
    color: var(--bs-body-color, #f8f7ff);
}
.pe-mat-kpi.is-res {
    background: rgba(14, 165, 233, 0.1);
    border-color: rgba(14, 165, 233, 0.25);
}
.pe-mat-kpi.is-short {
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.25);
}

/* ── Active Stock Allocations Header Pill ── */
.pe-header-alloc-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 2px 8px 2px 4px;
    border-radius: 999px;
    background: rgba(14, 165, 233, 0.12);
    border: 1px solid rgba(14, 165, 233, 0.28);
    font-size: 0.72rem;
    line-height: 1;
    white-space: nowrap;
}
.pe-header-alloc-pill .pe-active-res-icon {
    width: 20px;
    height: 20px;
    font-size: 0.65rem;
    border-radius: 50%;
    background: rgba(14, 165, 233, 0.22);
    color: #38bdf8;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.pe-header-alloc-pill .pe-active-res-title {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--bs-body-color, #f1f5f9);
    letter-spacing: 0.01em;
}
.pe-header-alloc-pill .pe-alloc-total-pill {
    display: inline-flex;
    align-items: center;
    font-size: 0.68rem;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 999px;
    background: rgba(14, 165, 233, 0.2);
    color: #38bdf8;
    border: 1px solid rgba(14, 165, 233, 0.35);
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.02em;
}
html[data-bs-theme="light"] .pe-header-alloc-pill,
body.theme-light .pe-header-alloc-pill {
    background: rgba(2, 132, 199, 0.08);
    border-color: rgba(2, 132, 199, 0.25);
}
html[data-bs-theme="light"] .pe-header-alloc-pill .pe-active-res-icon,
body.theme-light .pe-header-alloc-pill .pe-active-res-icon {
    background: rgba(2, 132, 199, 0.15);
    color: #0284c7;
}
html[data-bs-theme="light"] .pe-header-alloc-pill .pe-active-res-title,
body.theme-light .pe-header-alloc-pill .pe-active-res-title {
    color: #0f172a;
}
html[data-bs-theme="light"] .pe-header-alloc-pill .pe-alloc-total-pill,
body.theme-light .pe-header-alloc-pill .pe-alloc-total-pill {
    background: rgba(2, 132, 199, 0.14);
    color: #0369a1;
    border-color: rgba(2, 132, 199, 0.3);
}

.so-prep-exec-table {
    width: 100% !important;
    min-width: 1100px !important;
    table-layout: auto !important;
    border-collapse: separate !important;
    border-spacing: 0 !important;
}
.so-prep-exec-table tr.pe-exec-row {
    height: auto !important;
    min-height: 40px !important;
}
.so-prep-exec-table tr td {
    padding: 6px 8px !important;
    vertical-align: middle !important;
    white-space: normal !important;
    line-height: 1.25 !important;
}
.so-prep-exec-table tr th {
    padding: 8px 8px !important;
    font-size: 0.68rem !important;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--bs-secondary-color, #94a3b8);
    white-space: nowrap !important;
}
.pe-col-no, .pe-row-no {
    width: 32px !important;
    min-width: 32px !important;
    max-width: 36px !important;
    padding-left: 2px !important;
    padding-right: 2px !important;
    text-align: center;
    font-size: .72rem !important;
    color: var(--bs-secondary-color, #94a3b8);
}
.pe-col-code, .pe-cell-code {
    width: 140px !important;
    min-width: 135px !important;
    white-space: nowrap !important;
}
.pe-product-code {
    display: inline-flex;
    align-items: center;
    font-size: 0.68rem;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 4px;
    background: rgba(138, 114, 240, 0.12);
    color: #cfc4ff;
    border: 1px solid rgba(138, 114, 240, 0.22);
    flex-shrink: 0;
}
.pe-req-qty-pill {
    display: inline-flex;
    align-items: center;
    font-size: 0.64rem;
    font-weight: 700;
    padding: 1px 5px;
    border-radius: 4px;
    background: rgba(99, 102, 241, 0.15);
    color: #a5b4fc;
    border: 1px solid rgba(99, 102, 241, 0.28);
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
    flex-shrink: 0;
}
.cl-sales-order-detail .so-prep-exec-table .pe-col-product,
.cl-sales-order-detail .so-prep-exec-table .pe-cell-product {
    width: auto !important;
    min-width: 170px !important;
    max-width: none !important;
    overflow: visible !important;
    white-space: normal !important;
}
.pe-cell-product-inline {
    display: flex !important;
    align-items: flex-start !important;
    gap: 4px !important;
    width: 100% !important;
    min-width: 0 !important;
}
.pe-prod-name-block {
    display: inline-block !important;
    min-width: 0 !important;
    flex: 1 1 auto !important;
    line-height: 1.3 !important;
    word-break: break-word !important;
    white-space: normal !important;
}
.pe-prod-title-text {
    font-size: 0.68rem !important;
    font-weight: 600 !important;
    color: var(--bs-body-color, #f1f5f9) !important;
    white-space: normal !important;
    word-break: break-word !important;
}
.pe-prod-desc-inline {
    font-size: 0.62rem !important;
    color: var(--bs-secondary-color, #94a3b8) !important;
    white-space: normal !important;
    word-break: break-word !important;
}
.pe-product-icons {
    display: inline-flex !important;
    align-items: center !important;
    gap: 2px !important;
    flex-shrink: 0 !important;
    margin-left: 4px !important;
    margin-top: 1px !important;
}
.pe-product-icons .cl-action-icon {
    width: 18px !important;
    height: 18px !important;
    min-width: 18px !important;
    min-height: 18px !important;
    padding: 0 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    border-radius: 3px !important;
    font-size: 0.66rem !important;
    line-height: 1 !important;
    color: var(--bs-secondary-color, #94a3b8) !important;
    background: transparent !important;
    border: 1px solid transparent !important;
    transition: all 0.12s ease !important;
}
.pe-product-icons .cl-action-icon:hover {
    color: var(--bs-body-color, #f8f7ff) !important;
    background: rgba(255, 255, 255, 0.08) !important;
    border-color: rgba(255, 255, 255, 0.15) !important;
}
.cl-sales-order-detail .so-prep-exec-table .pe-col-step,
.cl-sales-order-detail .so-prep-exec-table .pe-cell-step {
    min-width: 220px !important;
    max-width: none !important;
    white-space: normal !important;
}
.pe-cell-step-single {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    min-width: 0;
}
.pe-step-name-text {
    font-size: 0.68rem;
    color: var(--bs-body-color, #cbd5e1);
    max-width: 160px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display: inline-block;
    vertical-align: middle;
    flex-shrink: 1;
}
.pe-shipment-micro-badge {
    display: inline-flex;
    align-items: center;
    font-size: 0.65rem;
    font-weight: 600;
    padding: 1px 5px;
    border-radius: 4px;
    background: rgba(14, 165, 233, 0.12);
    color: #38bdf8;
    border: 1px solid rgba(14, 165, 233, 0.22);
    white-space: nowrap;
    flex-shrink: 0;
}
.cl-sales-order-detail .so-prep-exec-table .pe-col-material,
.cl-sales-order-detail .so-prep-exec-table .pe-cell-material {
    min-width: 270px !important;
    max-width: none !important;
    white-space: normal !important;
}
.pe-cell-material-single {
    display: flex;
    align-items: center;
    gap: 5px;
    flex-wrap: wrap;
    min-width: 0;
}
.pe-mat-name-text {
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--bs-body-color, #e2e8f0);
    max-width: 150px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display: inline-block;
    vertical-align: middle;
    flex-shrink: 1;
}
.pe-mat-issued-pill {
    display: inline-flex;
    align-items: center;
    font-size: 0.65rem;
    font-weight: 700;
    padding: 1px 5px;
    border-radius: 4px;
    background: rgba(14, 165, 233, 0.14);
    color: #38bdf8;
    border: 1px solid rgba(14, 165, 233, 0.28);
    white-space: nowrap;
    flex-shrink: 0;
}
.pe-mat-reserved-total {
    font-size: 0.65rem;
    font-weight: 700;
    color: #38bdf8;
    margin-left: 1px;
    white-space: nowrap;
    flex-shrink: 0;
}
.pe-mat-status-tag {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: 0.65rem;
    font-weight: 600;
    padding: 1px 5px;
    border-radius: 4px;
    white-space: nowrap;
    flex-shrink: 0;
}
.pe-mat-status-tag.is-done {
    background: rgba(16, 185, 129, 0.12);
    color: #34d399;
    border: 1px solid rgba(16, 185, 129, 0.25);
}
.pe-mat-status-tag.is-dispatched {
    background: rgba(14, 165, 233, 0.12);
    color: #38bdf8;
    border: 1px solid rgba(14, 165, 233, 0.25);
}
.pe-mat-status-tag.is-instock {
    background: rgba(16, 185, 129, 0.12);
    color: #34d399;
    border: 1px solid rgba(16, 185, 129, 0.25);
}
.pe-mat-status-tag.is-short {
    background: rgba(239, 68, 68, 0.12);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.25);
}
.pe-mat-code-pill {
    display: inline-flex;
    align-items: center;
    font-size: 0.65rem;
    font-weight: 600;
    padding: 1px 5px;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.05);
    color: var(--bs-body-color, #e2e8f0);
    border: 1px solid rgba(255, 255, 255, 0.1);
    white-space: nowrap;
    flex-shrink: 0;
}
.pe-mat-chips-inline {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    flex-shrink: 0;
}
.pe-micro-res-chip {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    font-size: 0.65rem;
    padding: 1px 4px;
    border-radius: 4px;
    background: rgba(14, 165, 233, 0.14);
    color: #38bdf8;
    border: 1px solid rgba(14, 165, 233, 0.25);
    white-space: nowrap;
    flex-shrink: 0;
}
.pe-micro-res-x {
    border: none;
    background: none;
    color: #f87171;
    padding: 0 1px;
    font-size: 0.72rem;
    line-height: 1;
    cursor: pointer;
    opacity: 0.7;
    margin-left: 2px;
}
.pe-micro-res-x:hover { opacity: 1; color: #ef4444; }
.pe-micro-buffer-trigger {
    display: inline-flex;
    align-items: center;
    gap: 1px;
    font-size: 0.65rem;
    font-weight: 600;
    padding: 1px 5px;
    border-radius: 4px;
    background: rgba(138, 114, 240, 0.12);
    color: #cfc4ff;
    border: 1px solid rgba(138, 114, 240, 0.28);
    cursor: pointer;
    line-height: 1.2;
    transition: all 0.15s ease;
    white-space: nowrap;
    flex-shrink: 0;
}
.pe-micro-buffer-trigger:hover {
    background: rgba(138, 114, 240, 0.25);
    color: #ffffff;
    border-color: rgba(138, 114, 240, 0.5);
}
.pe-col-status, .pe-cell-status {
    width: 95px !important;
    min-width: 95px !important;
    white-space: nowrap !important;
    text-align: center !important;
}
.pe-col-action, .pe-cell-action {
    width: auto !important;
    min-width: 120px !important;
    white-space: nowrap !important;
    text-align: end !important;
}
.pe-action-cell-inline {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    flex-wrap: nowrap;
    justify-content: flex-end;
}
.pe-primary-action.pe-open-vendor-compact {
    height: 24px !important;
    min-height: 24px !important;
    padding: 1px 8px !important;
    font-size: 0.70rem !important;
    font-weight: 600 !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 3px !important;
    border-radius: 4px !important;
    line-height: 1 !important;
    background: #4f46e5 !important;
    border-color: #4f46e5 !important;
    color: #ffffff !important;
}
.pe-primary-action.pe-open-vendor-compact:hover {
    background: #4338ca !important;
    border-color: #4338ca !important;
}
.pe-sent-pill {
    display: inline-flex;
    align-items: center;
    font-size: 0.68rem;
    font-weight: 600;
    padding: 2px 7px;
    border-radius: 4px;
    background: rgba(14, 165, 233, 0.12);
    color: #38bdf8;
    border: 1px solid rgba(14, 165, 233, 0.25);
    white-space: nowrap;
}
.pe-sent-pill.is-done {
    background: rgba(16, 185, 129, 0.12);
    color: #34d399;
    border-color: rgba(16, 185, 129, 0.25);
}
.pe-action-btn-micro {
    height: 24px !important;
    min-height: 24px !important;
    width: 24px !important;
    padding: 0 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 0.72rem !important;
    border-radius: 4px !important;
}
.pe-floating-toast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 1090;
    padding: 10px 16px;
    border-radius: 8px;
    background: rgba(15, 23, 42, 0.95);
    color: #f8fafc;
    border: 1px solid rgba(16, 185, 129, 0.4);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.4);
    font-size: 0.8rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    opacity: 0;
    transform: translateY(10px);
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.pe-floating-toast.is-visible {
    opacity: 1;
    transform: translateY(0);
}
.pe-floating-toast i {
    color: #10b981;
    font-size: 0.95rem;
}

/* ── Reservation modal polish ── */
.pe-modal-kpi-strip {
    display: flex; gap: .5rem; padding: .5rem 0;
}
.pe-modal-kpi {
    flex: 1; text-align: center; padding: .5rem .35rem;
    border-radius: .5rem; background: var(--bs-tertiary-bg, #f8f9fa);
    border: 1px solid var(--bs-border-color, #dee2e6);
    min-width: 0;
}
.pe-modal-kpi-val {
    font-size: 1.1rem; font-weight: 700; line-height: 1.2;
    color: var(--bs-body-color);
}
.pe-modal-kpi-val.is-info  { color: var(--bs-info-text-emphasis, #087990); }
.pe-modal-kpi-val.is-primary { color: var(--bs-primary, #0d6efd); }
.pe-modal-kpi-val.is-success { color: var(--bs-success, #198754); }
.pe-modal-kpi-lbl {
    font-size: .62rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .04em; color: var(--bs-secondary-color, #6c757d);
    margin-top: .1rem;
}
.pe-modal-progress-bar {
    height: 6px; border-radius: 99px;
    background: var(--bs-border-color, #dee2e6); overflow: hidden;
    margin: .5rem 0;
}
.pe-modal-progress-fill {
    height: 100%; border-radius: 99px;
    background: linear-gradient(90deg, var(--bs-info, #0dcaf0), var(--bs-primary, #0d6efd));
    transition: width .35s cubic-bezier(.4,0,.2,1);
}
.pe-modal-alloc-tbl { margin-bottom: 0; }
.pe-modal-alloc-tbl th {
    font-size: .68rem !important; font-weight: 700; text-transform: uppercase;
    letter-spacing: .03em; color: var(--bs-secondary-color, #6c757d) !important;
    border-bottom-width: 1px !important; padding: .4rem .5rem !important;
}
.pe-modal-alloc-tbl td {
    padding: .4rem .5rem !important; vertical-align: middle;
}
.pe-modal-alloc-tbl tbody tr {
    transition: background-color .15s;
}
.pe-modal-alloc-tbl tbody tr:hover {
    background-color: var(--bs-primary-bg-subtle, #cfe2ff) !important;
}
.pe-modal-loc-name {
    font-size: .8rem; font-weight: 600; color: var(--bs-body-color);
    line-height: 1.2;
}
.pe-modal-loc-wh {
    font-size: .68rem; color: var(--bs-secondary-color, #868e96);
}
.pe-modal-avail-pill {
    display: inline-flex; align-items: center; gap: .2rem;
    font-size: .72rem; font-weight: 600;
    padding: .15rem .45rem; border-radius: 999px;
    background: var(--bs-secondary-bg-subtle, #e9ecef);
    color: var(--bs-secondary-text-emphasis, #495057);
}
.pe-modal-alloc-input {
    max-width: 110px;
}
.pe-modal-toolbar {
    display: flex; align-items: center; justify-content: space-between;
    gap: .5rem; margin-bottom: .35rem;
}
.pe-modal-toolbar-hint {
    font-size: .72rem; color: var(--bs-secondary-color, #868e96);
}

@media (max-width: 576px) {
    .pe-modal-kpi-strip { flex-wrap: wrap; }
    .pe-modal-kpi { flex: 0 0 calc(50% - .25rem); }
    .pe-modal-alloc-input { max-width: 80px; }
}
</style>
<div class="table-responsive mb-4 pe-exec-surface">
    <table class="table table-sm align-middle so-prep-exec-table" aria-label="Preparation execution lines">
        <thead>
            <tr class="pe-compact-row">
                <th class="text-center pe-col-no">No.</th>
                <th class="pe-col-code">Product Code</th>
                <th class="pe-col-product">Product</th>
                <th class="pe-col-step">Current step</th>
                <th class="pe-col-material">Material</th>
                <th class="pe-col-status">Status</th>
                <th class="text-end pe-col-action">Action</th>
            </tr>
        </thead>
        <tbody>
        <?php $prepRowNo = 0; ?>
        <?php foreach (($preparationExecution['blocks'] ?? []) as $block): ?>
            <?php
                $lineId = (int) ($block['sales_order_line_id'] ?? 0);
                $blockReservations = $lineReservations[$lineId] ?? [];
                $activeLineReserved = 0.0;
                foreach ($blockReservations as $bres) {
                    $activeLineReserved += max(0.0, (float)($bres['reserved_quantity'] ?? 0) - (float)($bres['consumed_quantity'] ?? 0) - (float)($bres['released_quantity'] ?? 0));
                }

                $steps = $block['steps'];
                $activeIndex = null;
                foreach ($steps as $i => $row) {
                    if (empty($row['is_done'])) { $activeIndex = $i; break; }
                }
                $allDone = $activeIndex === null;
                $activeRow = $allDone ? null : $steps[$activeIndex];
                $meta = $statusMeta[$activeRow['status'] ?? 'completed'] ?? $statusMeta['not_started'];
                if (!$allDone && $activeRow && $activeRow['open_qty'] > 0 && !empty($block['open_shipments']) && $activeRow['remaining_qty'] <= 0.0001) {
                    $meta = ['label' => 'With vendor', 'class' => 'warning'];
                }
                $primaryMaterial = $block['required_materials'][0] ?? null;
                $neededQty = (float) ($primaryMaterial['needed'] ?? 0);
                $alreadyReserved = max($activeLineReserved, (float) ($primaryMaterial['reserved_for_order'] ?? 0));
                $bufferQty = max(0.0, $alreadyReserved - $neededQty);
                $reservableQty = $primaryMaterial
                    ? max(0.0, $neededQty - $alreadyReserved)
                    : 0.0;
                $reserveLocations = array_values(array_filter(
                    $primaryMaterial['stock_locations'] ?? [],
                    static fn ($location) => (float) ($location['available'] ?? 0) > 0.0001
                ));
            ?>
            <?php $prepRowNo++; ?>
            <tr class="pe-exec-row">
                <td class="text-center pe-row-no" data-label="Line"><?= $prepRowNo ?></td>
                <td class="pe-cell-code" data-label="Product code">
                    <div class="d-flex align-items-center gap-1">
                        <?php if (!empty($block['product_code'])): ?>
                            <span class="pe-product-code"><?= esc($block['product_code']) ?></span>
                        <?php else: ?>
                            <span class="text-muted small">-</span>
                        <?php endif; ?>
                        <span class="pe-req-qty-pill" title="Target Order Requirement: <?= number_format($block['line_qty'], 0) ?> pcs">
                            Req: <?= number_format($block['line_qty'], 0) ?>
                        </span>
                    </div>
                </td>
                <td class="pe-cell-product" data-label="Product">
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
                        $prodDesc = !empty($block['product_description']) ? $block['product_description'] : (!empty($block['variant_name']) ? $block['variant_name'] : '');
                    ?>
                    <div class="pe-cell-product-inline" title="<?= esc($block['product_name'] . (!empty($prodDesc) ? ' · ' . $prodDesc : '')) ?>">
                        <div class="pe-prod-name-block">
                            <span class="pe-prod-title-text"><?= esc($block['product_name']) ?></span>
                            <?php if (!empty($prodDesc)): ?>
                                <span class="pe-prod-desc-inline">&middot; <?= esc($prodDesc) ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="pe-product-icons">
                            <button type="button" class="cl-action-icon pe-open-profile" title="View required materials &amp; services"
                                    data-payload='<?= esc(json_encode($profilePayload), 'attr') ?>'>
                                <i class="bi bi-info-circle"></i>
                            </button>
                            <button type="button" class="cl-action-icon pe-open-trail" title="Movement trail — where every piece went"
                                    data-url="<?= site_url('sales-orders/preparation/trail/' . (int) $orderId . '/' . (int) $block['product_id']) ?>"
                                    data-product-name="<?= esc($block['product_name'], 'attr') ?>">
                                <i class="bi bi-signpost-split"></i>
                            </button>
                        </span>
                    </div>
                </td>
                <td class="pe-cell-step" data-label="Current step">
                    <div class="pe-cell-step-single" title="<?= esc(($allDone ? 'All steps complete' : $activeRow['step']['name']) . (!empty($block['open_shipments']) ? ' · ' . ($block['open_shipments'][0]['vendor_name'] ?? '') . ' (' . number_format((float)$block['open_shipments'][0]['outstanding_qty'], 0) . ' pcs)' : '')) ?>">
                        <div class="pe-mini-stepper" title="<?= esc($block['material_ready'] ? 'Material in stock' : 'Material short: ' . number_format($block['shortage'], 2)) ?>">
                            <span class="pe-mini-stepper-dot <?= $block['material_ready'] ? 'is-done' : 'is-blocked' ?>">
                                <i class="bi <?= $block['material_ready'] ? 'bi-check' : 'bi-x' ?>"></i>
                            </span>
                            <?php foreach ($steps as $i => $stepRow): ?>
                                <span class="pe-mini-stepper-arrow">&rarr;</span>
                                <?php $dotState = ! empty($stepRow['is_done']) ? 'is-done' : ($i === $activeIndex ? 'is-current' : 'is-pending'); ?>
                                <span class="pe-mini-stepper-dot <?= $dotState ?>" title="<?= esc($stepRow['step']['name']) ?>">
                                    <?= $dotState === 'is-done' ? '<i class="bi bi-check"></i>' : ($i + 1) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <span class="pe-step-name-text">
                            <?= $allDone ? 'Done' : esc($activeRow['step']['name']) ?>
                        </span>
                        <?php if (!empty($block['open_shipments'])): ?>
                            <?php foreach ($block['open_shipments'] as $shipment): ?>
                                <span class="pe-shipment-micro-badge" title="Dispatched: <?= esc($shipment['reference_no']) ?> to <?= esc($shipment['vendor_name']) ?>">
                                    <i class="bi bi-truck me-1"></i><?= esc($shipment['vendor_name'] ?? 'Vendor') ?> <strong class="ms-1"><?= number_format((float) $shipment['outstanding_qty'], 0) ?></strong>
                                </span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php if (!empty($block['cancelled_shipments'])): ?>
                            <?php foreach ($block['cancelled_shipments'] as $cShipment): ?>
                                <span class="pe-shipment-micro-badge is-cancelled" style="background: rgba(220, 53, 69, 0.08); color: #dc3545; border: 1px dashed rgba(220, 53, 69, 0.4); text-decoration: line-through;" title="Cancelled: <?= esc($cShipment['reference_no']) ?> to <?= esc($cShipment['vendor_name']) ?> (<?= number_format((float) $cShipment['qty'], 0) ?> pcs returned to stock)">
                                    <i class="bi bi-x-circle me-1"></i><?= esc($cShipment['vendor_name'] ?? 'Vendor') ?> <span style="font-size:0.58rem; text-decoration: none;" class="badge bg-danger text-white ms-1">Cancelled</span>
                                </span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="pe-cell-material" data-label="Material">
                    <?php
                        $allStockLocations = $primaryMaterial['stock_locations'] ?? [];
                        $locTooltipParts = [];
                        foreach ($allStockLocations as $location) {
                            $locTooltipParts[] = trim(($location['warehouse_name'] ?? '') . ' / ' . ($location['location_name'] ?? '')) . ' (' . number_format((float)($location['available'] ?? 0), 2) . ' avail)';
                        }
                        $locSummaryTooltip = implode(', ', $locTooltipParts);
                        $matDisplayName = !empty($block['material_product_name']) ? $block['material_product_name'] : (!empty($primaryMaterial['name']) ? $primaryMaterial['name'] : ($block['material_name'] ?? ''));

                        $sentQty = 0.0;
                        if (!empty($block['open_shipments'])) {
                            foreach ($block['open_shipments'] as $os) {
                                $sentQty += (float) ($os['outstanding_qty'] ?? $os['qty'] ?? 0);
                            }
                        }
                        if ($sentQty <= 0.0001 && !empty($activeRow['open_qty'])) {
                            $sentQty = (float) $activeRow['open_qty'];
                        }
                    ?>
                    <div class="pe-cell-material-single" title="<?= esc($locSummaryTooltip ?: ($block['material_name'] ?? '')) ?>">
                        <?php if ($allDone): ?>
                            <span class="pe-mat-status-tag is-done"><i class="bi bi-check-circle-fill"></i> Completed</span>
                        <?php elseif ($sentQty > 0.0001): ?>
                            <span class="pe-mat-status-tag is-dispatched" title="Dispatched to vendor"><i class="bi bi-truck"></i> Dispatched</span>
                        <?php elseif ($block['material_ready']): ?>
                            <span class="pe-mat-status-tag is-instock"><i class="bi bi-check-circle-fill"></i> In stock</span>
                        <?php else: ?>
                            <span class="pe-mat-status-tag is-short"><i class="bi bi-exclamation-triangle-fill"></i> Short <?= number_format($block['shortage'], 0) ?></span>
                        <?php endif; ?>

                        <?php if (! empty($matDisplayName)): ?>
                            <span class="pe-mat-name-text" title="<?= esc($block['material_name'] ?? $matDisplayName) ?>"><?= esc($matDisplayName) ?></span>
                        <?php endif; ?>

                        <?php if (! empty($block['material_code'])): ?>
                            <span class="pe-mat-code-pill" title="<?= esc(($block['material_name'] ?? '') . ($locSummaryTooltip ? ' | ' . $locSummaryTooltip : '')) ?>">
                                <?= esc($block['material_code']) ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($sentQty > 0.0001): ?>
                            <span class="pe-mat-issued-pill" title="Sent to vendor: <?= number_format($sentQty, 0) ?> pcs"><i class="bi bi-send-check me-1"></i><?= number_format($sentQty, 0) ?> sent</span>
                        <?php elseif ($primaryMaterial): ?>
                            <?php
                                $isFull = $alreadyReserved >= $neededQty - 0.001;
                            ?>
                            <?php if (! empty($blockReservations)): ?>
                                <div class="pe-mat-chips-inline">
                                    <?php foreach ($blockReservations as $bres): ?>
                                        <?php $bresRemaining = max(0.0, (float) $bres['reserved_quantity'] - (float) $bres['consumed_quantity'] - (float) $bres['released_quantity']); ?>
                                        <?php if ($bresRemaining > 0.0001): ?>
                                            <span class="pe-micro-res-chip" title="<?= esc(trim(($bres['warehouse_name'] ?? '') . ' / ' . ($bres['location_name'] ?? ''))) ?>">
                                                <i class="bi bi-box-seam me-1"></i><?= esc($bres['location_name'] ?? '') ?>: <strong><?= number_format($bresRemaining, 0) ?></strong>
                                                <form method="post" action="<?= site_url('sales-orders/preparation/release-reservation') ?>" class="d-inline pe-ajax-release-form" style="line-height:0; margin:0;">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="sales_order_id" value="<?= (int) $orderId ?>">
                                                    <input type="hidden" name="reservation_id" value="<?= (int) $bres['id'] ?>">
                                                    <button type="submit" class="pe-micro-res-x" title="Release <?= number_format($bresRemaining, 0) ?> pcs" onclick="return confirm('Release <?= number_format($bresRemaining, 0) ?> pcs from <?= esc($bres['location_name'] ?? '', 'js') ?>?');">
                                                        &times;
                                                    </button>
                                                </form>
                                            </span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ((! $activeRow || $activeRow['remaining_qty'] > 0.0001) && !empty($reserveLocations)): ?>
                                <?php $reservePayload = [
                                    'sales_order_line_id' => (int) $block['sales_order_line_id'],
                                    'product_id' => (int) $primaryMaterial['product_id'],
                                    'variant_id' => (int) ($primaryMaterial['variant_id'] ?? 0),
                                    'material_name' => $primaryMaterial['name'] ?? 'Material',
                                    'needed_quantity' => $neededQty,
                                    'reserved_quantity' => $alreadyReserved,
                                    'quantity' => $reservableQty,
                                    'locations' => $reserveLocations,
                                ]; ?>
                                <button type="button" class="pe-micro-buffer-trigger pe-open-reservation"
                                        title="<?= $isFull ? 'Add extra pieces for safety / rejection allowance' : 'Reserve material stock' ?>"
                                        data-payload='<?= esc(json_encode($reservePayload), 'attr') ?>'>
                                    <i class="bi bi-plus"></i><?= $isFull ? 'Extra' : 'Reserve' ?>
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="pe-cell-status" data-label="Status">
                    <?php if (!$allDone): ?>
                        <span class="pe-status-pill is-<?= esc($activeRow['status'] ?? 'not_started', 'attr') ?>"><span></span><?= $meta['label'] ?></span>
                    <?php else: ?>
                        <span class="pe-status-pill is-completed"><span></span>Done</span>
                    <?php endif; ?>
                </td>
                <td class="text-end pe-cell-action" data-label="Action">
                    <?php if ($allDone): ?>
                        <span class="text-muted small">—</span>
                    <?php else: ?>
                        <div class="pe-action-cell-inline justify-content-end">
                            <?php if ($activeRow['open_qty'] > 0 && ($activeRow['record']['status'] ?? '') !== 'completed'): ?>
                                <form method="post" action="<?= site_url('sales-orders/preparation/complete-step') ?>" class="d-inline" style="line-height:0; margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="sales_order_id" value="<?= (int) $orderId ?>">
                                    <input type="hidden" name="record_id" value="<?= (int) ($activeRow['record']['id'] ?? 0) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-success pe-action-btn-micro"
                                            title="Close this step for <?= number_format((float) $activeRow['open_qty'], 2) ?> pcs">
                                        <i class="bi bi-check2-circle"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <?php if ($activeRow['remaining_qty'] <= 0.0001): ?>
                                <?php if ($activeRow['open_qty'] > 0): ?>
                                    <span class="pe-sent-pill" title="All <?= number_format((float) $activeRow['open_qty'], 2) ?> pcs dispatched to vendor">
                                        <i class="bi bi-check2 text-success me-1"></i>Sent (<?= number_format((float) $activeRow['open_qty'], 0) ?>)
                                    </span>
                                <?php else: ?>
                                    <span class="pe-sent-pill is-done">
                                        <i class="bi bi-check-circle me-1"></i>Done
                                    </span>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php
                                    $blocked = ! $block['material_ready'];
                                    $blockedTitle = 'Needs ' . number_format($block['shortage'], 2) . ' more in stock before this step can start — use Auto-Create RFQ above';
                                    $payload = [
                                        'sales_order_id' => (int) $orderId,
                                        'sales_order_line_id' => (int) $block['sales_order_line_id'],
                                        'product_id' => (int) $block['product_id'],
                                        'product_name' => $block['product_name'],
                                        'material_product_id' => (int) $block['material_product_id'],
                                        'material_variant_id' => (int) ($primaryMaterial['variant_id'] ?? 0),
                                        'material_name' => $block['material_name'],
                                        'step_id' => (int) $activeRow['step']['id'],
                                        'step_name' => $activeRow['step']['name'],
                                        'line_qty' => $block['line_qty'],
                                        'remaining_qty' => $activeRow['remaining_qty'],
                                        'stock_locations' => $block['stock_locations'],
                                        'reserved_locations' => array_values(array_map(static function ($location) {
                                            return [
                                                'location_id' => (int) ($location['location_id'] ?? 0),
                                                'quantity' => (float) ($location['reserved_for_order'] ?? 0),
                                            ];
                                        }, array_filter($block['stock_locations'], static fn ($location) => (float) ($location['reserved_for_order'] ?? 0) > 0.0001))),
                                        'vendor_options' => $activeRow['vendor_options'],
                                    ];
                                ?>
                                <?php if ($activeRow['allow_inhouse']): ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary pe-action-btn-micro<?= $blocked ? ' disabled' : ' pe-open-inhouse' ?>"
                                        title="<?= $blocked ? esc($blockedTitle) : 'Start in-house' ?>"
                                        <?= $blocked ? 'disabled aria-disabled="true"' : '' ?>
                                        data-payload='<?= esc(json_encode($payload), 'attr') ?>'>
                                        <i class="bi bi-house-gear"></i>
                                    </button>
                                <?php endif; ?>
                                <?php if (! empty($activeRow['vendor_options'])): ?>
                                    <button type="button" class="btn btn-sm pe-primary-action pe-open-vendor-compact<?= $blocked ? ' disabled' : ' pe-open-vendor' ?>"
                                        title="<?= $blocked ? esc($blockedTitle) : 'Send to vendor' ?>"
                                        <?= $blocked ? 'disabled aria-disabled="true"' : '' ?>
                                        data-payload='<?= esc(json_encode($payload), 'attr') ?>'>
                                        <i class="bi bi-truck"></i><span>Send</span>
                                    </button>
                                <?php endif; ?>
                                <?php if (! $activeRow['allow_inhouse'] && empty($activeRow['vendor_options'])): ?>
                                    <span class="text-muted small">No route</span>
                                <?php elseif ($blocked): ?>
                                    <span class="badge bg-danger-subtle text-danger border" style="font-size: .65rem; padding: 2px 5px;" title="<?= esc($blockedTitle) ?>">
                                        Short <?= number_format($block['shortage'], 0) ?>
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php foreach (($block['open_shipments'] ?? []) as $shipment): ?>
                                <a class="btn btn-sm btn-outline-secondary pe-action-btn-micro" target="_blank"
                                   href="<?= site_url('vendor-receive/' . (int) $shipment['id'] . '/send-slip') ?>"
                                   title="Print send note (<?= esc($shipment['reference_no']) ?>)"><i class="bi bi-printer"></i></a>
                                <a class="btn btn-sm btn-outline-success pe-action-btn-micro" href="<?= site_url('vendor-receive/' . (int) $shipment['id']) ?>"
                                   title="Receive back &amp; run QC (<?= esc($shipment['reference_no']) ?>)"><i class="bi bi-box-arrow-in-down"></i></a>
                                <?php if (! empty($shipment['vendor_options'])): ?>
                                    <button type="button" class="btn btn-sm btn-outline-warning pe-action-btn-micro pe-open-reroute"
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
                                <form method="post" action="<?= site_url('sales-orders/preparation/cancel-send-note') ?>" class="d-inline" style="line-height:0; margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="send_note_id" value="<?= (int) $shipment['id'] ?>">
                                    <input type="hidden" name="sales_order_id" value="<?= (int) $orderId ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger pe-action-btn-micro"
                                            title="Cancel shipment — return <?= number_format((float) $shipment['outstanding_qty'], 0) ?> pcs to stock (<?= esc($shipment['reference_no']) ?>)"
                                            onclick="return confirm('Cancel this shipment? <?= number_format((float) $shipment['outstanding_qty'], 0) ?> pcs will be returned to your stock from <?= esc($shipment['vendor_name'] ?? 'vendor', 'js') ?>.');">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                </form>
                            <?php endforeach; ?>
                            <?php foreach (($block['cancelled_shipments'] ?? []) as $cShipment): ?>
                                <a class="btn btn-sm btn-outline-danger pe-action-btn-micro" target="_blank"
                                   href="<?= site_url('vendor-receive/' . (int) $cShipment['id'] . '/send-slip') ?>"
                                   title="Print cancelled send note: <?= esc($cShipment['reference_no']) ?> to <?= esc($cShipment['vendor_name']) ?> (<?= number_format((float) $cShipment['qty'], 0) ?> pcs returned to stock)"><i class="bi bi-printer"></i></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
</tbody>
    </table>
</div>
</div>
</div>

<!-- Multi-box / Multi-location material reservation -->
<div class="modal fade" id="peReservationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="post" action="<?= site_url('sales-orders/preparation/reserve-stock') ?>" class="modal-content" id="peReservationForm">
            <?= csrf_field() ?>
            <input type="hidden" name="sales_order_id" value="<?= (int) $orderId ?>">
            <input type="hidden" name="sales_order_line_id" id="pe_r_line_id">
            <input type="hidden" name="product_id" id="pe_r_product_id">
            <input type="hidden" name="variant_id" id="pe_r_variant_id">
            <input type="hidden" name="allocations" id="pe_r_allocations_json">
            <input type="hidden" name="quantity" id="pe_r_quantity_single">
            <input type="hidden" name="location_id" id="pe_r_location_id_single">

            <div class="modal-header border-0 pb-0">
                <div>
                    <h6 class="modal-title d-flex align-items-center gap-2 mb-0">
                        <i class="bi bi-boxes text-info"></i>
                        Reserve Material: <strong id="pe_r_title"></strong>
                    </h6>
                    <div class="pe-modal-toolbar-hint mt-1"><i class="bi bi-info-circle me-1"></i>Pick quantities from one or more boxes below; safety extras are allowed</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2 pb-3">
                <!-- Compact KPI strip -->
                <div class="pe-modal-kpi-strip">
                    <div class="pe-modal-kpi">
                        <div class="pe-modal-kpi-val" id="pe_r_metric_needed">0</div>
                        <div class="pe-modal-kpi-lbl">Needed</div>
                    </div>
                    <div class="pe-modal-kpi">
                        <div class="pe-modal-kpi-val is-info" id="pe_r_metric_reserved">0</div>
                        <div class="pe-modal-kpi-lbl">Reserved</div>
                    </div>
                    <div class="pe-modal-kpi">
                        <div class="pe-modal-kpi-val is-primary" id="pe_r_metric_remaining">0</div>
                        <div class="pe-modal-kpi-lbl">Remaining</div>
                    </div>
                    <div class="pe-modal-kpi">
                        <div class="pe-modal-kpi-val is-success" id="pe_r_metric_allocating">0</div>
                        <div class="pe-modal-kpi-lbl">Allocating</div>
                    </div>
                </div>

                <!-- Live progress bar -->
                <div class="pe-modal-progress-bar">
                    <div class="pe-modal-progress-fill" id="pe_r_progress_fill" style="width: 0%;"></div>
                </div>

                <!-- Toolbar -->
                <div class="pe-modal-toolbar">
                    <span class="pe-modal-toolbar-hint" id="pe_r_status_hint"></span>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="pe_r_btn_autofill" title="Auto-fill boxes to match remaining">
                            <i class="bi bi-magic me-1"></i>Auto-fill
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="pe_r_btn_reset" title="Clear all">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </button>
                    </div>
                </div>

                <!-- Allocation table -->
                <div class="table-responsive border rounded">
                    <table class="table table-sm align-middle pe-modal-alloc-tbl">
                        <thead>
                            <tr>
                                <th>Location</th>
                                <th style="width:100px;">Available</th>
                                <th style="width:140px;">Reserve</th>
                                <th style="width:50px;" class="text-center"></th>
                            </tr>
                        </thead>
                        <tbody id="pe_r_locations_tbody"></tbody>
                    </table>
                </div>

                <div id="pe_r_alert" class="alert alert-warning py-1 px-3 small d-none mb-0 mt-2"></div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" id="pe_r_submit_btn" class="btn btn-sm btn-info" disabled>
                    <i class="bi bi-lock me-1"></i>Reserve <span id="pe_r_submit_qty">0</span> pcs
                </button>
            </div>
        </form>
    </div>
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
        <form method="post" action="<?= site_url('sales-orders/preparation/send-to-vendor') ?>" class="modal-content" id="peVendorForm">
            <?= csrf_field() ?>
            <input type="hidden" name="sales_order_id" value="<?= (int) $orderId ?>">
            <input type="hidden" name="sales_order_line_id" id="pe_v_line_id">
            <input type="hidden" name="product_id" id="pe_v_product_id">
            <input type="hidden" name="material_product_id" id="pe_v_material_product_id">
            <input type="hidden" name="material_variant_id" id="pe_v_material_variant_id">
            <input type="hidden" name="step_id" id="pe_v_step_id">
            <input type="hidden" name="unit_price" id="pe_v_unit_price">
            <div class="modal-header">
                <h6 class="modal-title">Send to vendor: <span id="pe_v_title"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-2">
                    <div class="col-7">
                        <label class="form-label small mb-0">Vendor</label>
                        <select name="vendor_id" id="pe_v_vendor" class="form-select form-select-sm" required></select>
                    </div>
                    <div class="col-5">
                        <label class="form-label small mb-0">Dispatch Date</label>
                        <input type="date" name="dispatch_date" id="pe_v_dispatch_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-0">Quantity</label>
                    <input type="number" step="0.0001" min="0.0001" name="qty" id="pe_v_qty" class="form-control form-control-sm" required>
                    <div class="form-text">Partial is fine — the rest stays with the current vendor.</div>
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
    var peAllVendors = <?= json_encode($peAllVendors ?? []) ?>;
    var peAddLocationUrl = '<?= site_url('sales-orders/preparation/add-vendor-location') ?>';
    var peCsrfName = '<?= csrf_token() ?>';
    var peCsrfHash = '<?= csrf_hash() ?>';

    function showPrepToast(message, type) {
        document.querySelectorAll('.pe-floating-toast').forEach(function(t) { t.remove(); });
        var toast = document.createElement('div');
        toast.className = 'pe-floating-toast pe-toast-' + (type || 'success');
        toast.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i>' + peEscapeHtml(message);
        document.body.appendChild(toast);
        setTimeout(function() { toast.classList.add('is-visible'); }, 10);
        setTimeout(function() {
            toast.classList.remove('is-visible');
            setTimeout(function() { toast.remove(); }, 300);
        }, 3500);
    }

    function refreshPrepExecutionPanel(msg) {
        var prepTab = document.getElementById('so-tab-prep');
        if (!prepTab) return;

        if (msg) {
            showPrepToast(msg);
        }

        fetch(window.location.href, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.text(); })
        .then(function (html) {
            var parser = new DOMParser();
            var doc = parser.parseFromString(html, 'text/html');
            var newPrep = doc.getElementById('so-tab-prep');
            if (newPrep) {
                document.querySelectorAll('.modal-backdrop').forEach(function(b) { b.remove(); });
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('padding-right');
                document.body.style.removeProperty('overflow');

                prepTab.innerHTML = newPrep.innerHTML;
                if (typeof window.initPreparationExecution === 'function') {
                    window.initPreparationExecution();
                }
            }
        })
        .catch(function (err) {
            console.error('Error refreshing preparation tab:', err);
        });
    }

    function initPreparationExecution() {
        var prepBody = document.getElementById('soPrepExecutionBody');
        var prepToggle = document.querySelector('.so-prep-collapse-toggle');
        if (prepBody && prepToggle) {
            prepBody.addEventListener('shown.bs.collapse', function () {
                prepToggle.setAttribute('aria-expanded', 'true');
                prepToggle.querySelector('span').textContent = 'Close';
                prepToggle.querySelector('i').className = 'bi bi-chevron-up';
            });
            prepBody.addEventListener('hidden.bs.collapse', function () {
                prepToggle.setAttribute('aria-expanded', 'false');
                prepToggle.querySelector('span').textContent = 'Open';
                prepToggle.querySelector('i').className = 'bi bi-chevron-down';
            });
        }

        document.querySelectorAll('[data-material-summary-toggle]').forEach(function (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                var list = document.querySelector('[data-material-summary-list]');
                if (! list) { return; }
                var expanded = list.getAttribute('data-expanded') === 'true';
                var visibleCount = parseInt(<?= (int) $materialSummaryVisibleCount ?>, 10);
                if (expanded) {
                    list.setAttribute('data-expanded', 'false');
                    list.querySelectorAll('[data-summary-item]').forEach(function (item, index) {
                        item.classList.toggle('d-none', index >= visibleCount);
                    });
                    toggleBtn.querySelector('span').textContent = 'Show all <?= count($materialSummary) ?> materials';
                    toggleBtn.querySelector('i').className = 'bi bi-chevron-down';
                    return;
                }

                list.setAttribute('data-expanded', 'true');
                list.querySelectorAll('[data-summary-item]').forEach(function (item) {
                    item.classList.remove('d-none');
                });
                toggleBtn.querySelector('span').textContent = 'Hide extra materials';
                toggleBtn.querySelector('i').className = 'bi bi-chevron-up';
            });
        });

        var currentReservationData = null;

        function updateReservationSummary() {
            if (!currentReservationData) return;
            var maxReservable = parseFloat(currentReservationData.quantity) || 0;
            var needed = parseFloat(currentReservationData.needed_quantity) || 0;
            var already = parseFloat(currentReservationData.reserved_quantity) || 0;
            var totalAllocated = 0;
            var inputs = document.querySelectorAll('.pe-alloc-input');
            var allocations = [];
            var hasInvalidInput = false;
            var invalidMsg = '';

            inputs.forEach(function (input) {
                var val = parseFloat(input.value) || 0;
                var available = parseFloat(input.getAttribute('data-available')) || 0;
                var locId = parseInt(input.getAttribute('data-location-id'), 10);
                if (val > 0) {
                    if (val > available + 0.0001) {
                        hasInvalidInput = true;
                        invalidMsg = 'Qty ' + val.toFixed(2) + ' exceeds ' + available.toFixed(2) + ' available in this box.';
                        input.classList.add('is-invalid');
                    } else {
                        input.classList.remove('is-invalid');
                    }
                    totalAllocated += val;
                    allocations.push({ location_id: locId, quantity: val });
                } else {
                    input.classList.remove('is-invalid');
                }
            });

            totalAllocated = Math.round(totalAllocated * 10000) / 10000;

            var metricAllocating = document.getElementById('pe_r_metric_allocating');
            var submitBtn = document.getElementById('pe_r_submit_btn');
            var submitQty = document.getElementById('pe_r_submit_qty');
            var alertEl = document.getElementById('pe_r_alert');
            var allocationsHidden = document.getElementById('pe_r_allocations_json');
            var singleQty = document.getElementById('pe_r_quantity_single');
            var singleLoc = document.getElementById('pe_r_location_id_single');
            var progressFill = document.getElementById('pe_r_progress_fill');
            var statusHint = document.getElementById('pe_r_status_hint');

            if (metricAllocating) metricAllocating.textContent = totalAllocated.toFixed(2);
            if (submitQty) submitQty.textContent = totalAllocated.toFixed(2);
            if (allocationsHidden) allocationsHidden.value = JSON.stringify(allocations);
            if (singleQty && singleLoc) {
                if (allocations.length === 1) {
                    singleQty.value = allocations[0].quantity;
                    singleLoc.value = allocations[0].location_id;
                } else {
                    singleQty.value = totalAllocated;
                    singleLoc.value = allocations[0] ? allocations[0].location_id : '';
                }
            }

            if (progressFill && needed > 0) {
                var fillPct = Math.min(100, ((already + totalAllocated) / needed) * 100);
                progressFill.style.width = fillPct + '%';
            }

            if (statusHint) {
                if (totalAllocated > 0 && totalAllocated <= maxReservable + 0.0001) {
                    var locCount = allocations.length;
                    statusHint.textContent = totalAllocated.toFixed(2) + ' pcs from ' + locCount + ' box' + (locCount === 1 ? '' : 'es');
                    statusHint.className = 'pe-modal-toolbar-hint text-success';
                } else {
                    statusHint.textContent = '';
                    statusHint.className = 'pe-modal-toolbar-hint';
                }
            }

            if (hasInvalidInput) {
                alertEl.textContent = invalidMsg;
                alertEl.classList.remove('d-none');
                submitBtn.disabled = true;
            } else if (totalAllocated <= 0.0001) {
                alertEl.classList.add('d-none');
                submitBtn.disabled = true;
            } else {
                alertEl.classList.add('d-none');
                submitBtn.disabled = false;
            }
        }

        document.querySelectorAll('.pe-open-reservation').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var data = JSON.parse(btn.getAttribute('data-payload'));
                currentReservationData = data;
                document.getElementById('pe_r_title').textContent = data.material_name;
                document.getElementById('pe_r_line_id').value = data.sales_order_line_id;
                document.getElementById('pe_r_product_id').value = data.product_id;
                document.getElementById('pe_r_variant_id').value = data.variant_id || '';

                var needed = parseFloat(data.needed_quantity) || 0;
                var already = parseFloat(data.reserved_quantity) || 0;
                var remaining = parseFloat(data.quantity) || 0;

                document.getElementById('pe_r_metric_needed').textContent = needed.toFixed(2);
                document.getElementById('pe_r_metric_reserved').textContent = already.toFixed(2);
                document.getElementById('pe_r_metric_remaining').textContent = remaining.toFixed(2);
                document.getElementById('pe_r_metric_allocating').textContent = '0.00';

                var tbody = document.getElementById('pe_r_locations_tbody');
                tbody.innerHTML = '';

                if (!data.locations || data.locations.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3"><i class="bi bi-inbox me-1"></i>No boxes with available stock</td></tr>';
                } else {
                    data.locations.forEach(function (loc) {
                        var avail = parseFloat(loc.available) || 0;
                        var tr = document.createElement('tr');
                        tr.innerHTML = 
                            '<td>' +
                                '<div class="pe-modal-loc-name">' + peEscapeHtml(loc.location_name) + '</div>' +
                                '<div class="pe-modal-loc-wh">' + peEscapeHtml(loc.warehouse_name || '') + '</div>' +
                            '</td>' +
                            '<td>' +
                                '<span class="pe-modal-avail-pill">' + avail.toFixed(2) + '</span>' +
                            '</td>' +
                            '<td>' +
                                '<input type="number" step="0.01" min="0" max="' + avail + '" class="form-control form-control-sm pe-alloc-input pe-modal-alloc-input" data-location-id="' + loc.location_id + '" data-available="' + avail + '" placeholder="0">' +
                            '</td>' +
                            '<td class="text-center">' +
                                '<button type="button" class="btn btn-sm btn-link text-info p-0 pe-btn-max" title="Fill max"><i class="bi bi-arrow-bar-up"></i></button>' +
                            '</td>';
                        tbody.appendChild(tr);
                    });
                }

                tbody.querySelectorAll('.pe-alloc-input').forEach(function (inp) {
                    inp.addEventListener('input', updateReservationSummary);
                });

                tbody.querySelectorAll('.pe-btn-max').forEach(function (maxBtn) {
                    maxBtn.addEventListener('click', function () {
                        var row = maxBtn.closest('tr');
                        var input = row.querySelector('.pe-alloc-input');
                        var avail = parseFloat(input.getAttribute('data-available')) || 0;
                        
                        var otherAllocated = 0;
                        tbody.querySelectorAll('.pe-alloc-input').forEach(function (otherInp) {
                            if (otherInp !== input) {
                                otherAllocated += parseFloat(otherInp.value) || 0;
                            }
                        });
                        var remainingCanTake = Math.max(0, remaining - otherAllocated);
                        var toFill = Math.min(avail, remainingCanTake);
                        input.value = toFill > 0 ? toFill.toFixed(2) : '';
                        updateReservationSummary();
                    });
                });

                var autoBtn = document.getElementById('pe_r_btn_autofill');
                if (autoBtn) {
                    autoBtn.onclick = function () {
                        var toDistribute = remaining;
                        tbody.querySelectorAll('.pe-alloc-input').forEach(function (inp) {
                            var avail = parseFloat(inp.getAttribute('data-available')) || 0;
                            if (toDistribute > 0 && avail > 0) {
                                var take = Math.min(toDistribute, avail);
                                inp.value = take.toFixed(2);
                                toDistribute -= take;
                            } else {
                                inp.value = '';
                            }
                        });
                        updateReservationSummary();
                    };
                }

                var resetBtn = document.getElementById('pe_r_btn_reset');
                if (resetBtn) {
                    resetBtn.onclick = function () {
                        tbody.querySelectorAll('.pe-alloc-input').forEach(function (inp) {
                            inp.value = '';
                        });
                        updateReservationSummary();
                    };
                }

                updateReservationSummary();
                new bootstrap.Modal(document.getElementById('peReservationModal')).show();
            });
        });

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
                document.getElementById('pe_v_material_variant_id').value = data.material_variant_id || '';
                document.getElementById('pe_v_step_id').value = data.step_id;
                document.getElementById('pe_v_material_hint').textContent = data.material_name
                    ? 'Shipping material: ' + data.material_name
                    : '';
                var vRemaining = data.remaining_qty != null ? parseFloat(data.remaining_qty) : parseFloat(data.line_qty);
                fillLocationSelect(document.getElementById('pe_v_from'), data.stock_locations, 'Select…');

                var vendorQty = document.getElementById('pe_v_qty');
                var fromSelect = document.getElementById('pe_v_from');
                var reservedByLocation = {};
                (data.reserved_locations || []).forEach(function (reservation) {
                    var locationId = String(reservation.location_id);
                    reservedByLocation[locationId] = (reservedByLocation[locationId] || 0) + (parseFloat(reservation.quantity) || 0);
                });
                var reservedLocationIds = Object.keys(reservedByLocation).filter(function (locationId) {
                    return reservedByLocation[locationId] > 0.0001;
                });
                if (reservedLocationIds.length > 0) {
                    fromSelect.value = reservedLocationIds[0];
                }
                function syncVendorQuantityToSource() {
                    var selectedLocation = (data.stock_locations || []).find(function (location) {
                        return String(location.location_id) === String(fromSelect.value);
                    });
                    var reservedQty = reservedByLocation[String(fromSelect.value)] || 0;
                    var availableForOrder = selectedLocation ? parseFloat(selectedLocation.qty || selectedLocation.available_for_order || 0) : 0;
                    vendorQty.value = reservedQty > 0.0001 ? reservedQty : vRemaining;
                    if (availableForOrder > 0) {
                        vendorQty.max = availableForOrder;
                    } else {
                        vendorQty.removeAttribute('max');
                    }
                }
                fromSelect.onchange = syncVendorQuantityToSource;
                syncVendorQuantityToSource();

                var vendorSelect = document.getElementById('pe_v_vendor');
                var priceInput = document.getElementById('pe_v_unit_price');
                vendorSelect.innerHTML = '';
                var stepVendorIds = {};
                if (data.vendor_options && data.vendor_options.length > 0) {
                    var grpStep = document.createElement('optgroup');
                    grpStep.label = 'Step Vendors';
                    data.vendor_options.forEach(function (v) {
                        stepVendorIds[String(v.vendor_id)] = true;
                        var opt = document.createElement('option');
                        opt.value = v.vendor_id;
                        opt.textContent = v.vendor_name + (v.service_price ? ' (' + v.currency + ' ' + parseFloat(v.service_price).toFixed(2) + '/unit)' : '');
                        opt.dataset.price = v.service_price || 0;
                        if (v.is_default) opt.selected = true;
                        grpStep.appendChild(opt);
                    });
                    vendorSelect.appendChild(grpStep);
                }

                // Other available vendors so user can choose or change vendor anytime
                var otherVendors = (peAllVendors || []).filter(function (v) {
                    return !stepVendorIds[String(v.vendor_id)];
                });
                if (otherVendors.length > 0) {
                    var grpOther = document.createElement('optgroup');
                    grpOther.label = 'Other Active Vendors';
                    otherVendors.forEach(function (v) {
                        var opt = document.createElement('option');
                        opt.value = v.vendor_id;
                        opt.textContent = v.vendor_name;
                        opt.dataset.price = 0;
                        grpOther.appendChild(opt);
                    });
                    vendorSelect.appendChild(grpOther);
                }

                priceInput.value = vendorSelect.selectedOptions[0] ? (vendorSelect.selectedOptions[0].dataset.price || 0) : 0;
                fillVendorLocationSelect(vendorSelect.value);
                vendorSelect.onchange = function () {
                    priceInput.value = vendorSelect.selectedOptions[0] ? (vendorSelect.selectedOptions[0].dataset.price || 0) : 0;
                    fillVendorLocationSelect(vendorSelect.value);
                };

                var dateInput = document.getElementById('pe_v_dispatch_date');
                if (dateInput && !dateInput.value) {
                    dateInput.value = new Date().toISOString().split('T')[0];
                }

                new bootstrap.Modal(document.getElementById('peVendorModal')).show();
            });
        });

        // AJAX Send to Vendor submission (no page reload)
        var vendorForm = document.getElementById('peVendorForm');
        if (vendorForm) {
            vendorForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var submitBtn = vendorForm.querySelector('button[type="submit"]');
                var originalBtnHtml = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Sending...';

                var formData = new FormData(vendorForm);
                fetch(vendorForm.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnHtml;

                    if (data.csrf_name && data.csrf_hash) {
                        peCsrfName = data.csrf_name;
                        peCsrfHash = data.csrf_hash;
                        document.querySelectorAll('input[name="' + data.csrf_name + '"]').forEach(function (inp) {
                            inp.value = data.csrf_hash;
                        });
                    }

                    if (!data.ok) {
                        alert(data.message || 'Failed to send to vendor.');
                        return;
                    }

                    var modalEl = document.getElementById('peVendorModal');
                    var modalInstance = bootstrap.Modal.getInstance(modalEl);
                    if (modalInstance) {
                        modalInstance.hide();
                    }

                    setTimeout(function() {
                        refreshPrepExecutionPanel(data.message || 'Sent to vendor successfully.');
                    }, 200);
                })
                .catch(function (err) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnHtml;
                    alert('An error occurred while sending to vendor.');
                });
            });
        }

        // AJAX release reservation forms
        document.querySelectorAll('.pe-ajax-release-form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var formData = new FormData(form);
                fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.csrf_name && data.csrf_hash) {
                        peCsrfName = data.csrf_name;
                        peCsrfHash = data.csrf_hash;
                        document.querySelectorAll('input[name="' + data.csrf_name + '"]').forEach(function (inp) {
                            inp.value = data.csrf_hash;
                        });
                    }
                    if (!data.ok) {
                        alert(data.message || 'Failed to release reservation.');
                        return;
                    }
                    refreshPrepExecutionPanel(data.message || 'Reservation released.');
                })
                .catch(function () {
                    alert('An error occurred while releasing reservation.');
                });
            });
        });

        // AJAX stock reservation form
        var resForm = document.getElementById('peReservationForm');
        if (resForm) {
            resForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var submitBtn = document.getElementById('pe_r_submit_btn');
                var originalBtnHtml = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Reserving...';

                var formData = new FormData(resForm);
                fetch(resForm.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnHtml;

                    if (data.csrf_name && data.csrf_hash) {
                        peCsrfName = data.csrf_name;
                        peCsrfHash = data.csrf_hash;
                        document.querySelectorAll('input[name="' + data.csrf_name + '"]').forEach(function (inp) {
                            inp.value = data.csrf_hash;
                        });
                    }

                    if (!data.ok) {
                        alert(data.message || 'Failed to reserve stock.');
                        return;
                    }

                    var modalEl = document.getElementById('peReservationModal');
                    var modalInstance = bootstrap.Modal.getInstance(modalEl);
                    if (modalInstance) {
                        modalInstance.hide();
                    }

                    setTimeout(function() {
                        refreshPrepExecutionPanel(data.message || 'Stock reserved successfully.');
                    }, 200);
                })
                .catch(function () {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnHtml;
                    alert('An error occurred while reserving stock.');
                });
            });
        }

        wireAddLocation('pe_v_add_loc_toggle', 'pe_v_add_loc_box', 'pe_v_new_loc_name', 'pe_v_add_loc_save', 'pe_v_add_loc_error', 'pe_v_vendor', 'pe_v_to');

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
    }

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

    initPreparationExecution();
    window.initPreparationExecution = initPreparationExecution;
    window.refreshPrepExecutionPanel = refreshPrepExecutionPanel;

})();
</script>
