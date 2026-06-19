<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>
<?= esc($delivery_order['do_number'] ?? 'DO-Draft') ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$do       = $delivery_order;
$status   = $do['status'] ?? 'draft';
$doId     = (int)($do['id'] ?? 0);
$isDraft  = ($status === 'draft');
$isConfirmed = in_array($status, ['confirmed', 'shipped', 'delivered'], true);
$hasTracking = !empty($do['tracking_number']);
$deliveryStatus = $do['delivery_status'] ?? null;
$estDays = (int)($do['estimated_delivery_days'] ?? 0);
$shippedAt = $do['shipped_at'] ?? null;
$isDelivered = ($status === 'delivered' || $deliveryStatus === 'delivered');

// Delivery status config (used in multiple places)
$statusConfig = [
    'delivered'           => ['label' => 'Delivered',            'icon' => 'check-circle-fill', 'color' => '#34d399', 'bg' => 'rgba(52,211,153,.12)'],
    'lost'                => ['label' => 'Lost in Transit',      'icon' => 'exclamation-triangle-fill', 'color' => '#f87171', 'bg' => 'rgba(248,113,113,.12)'],
    'customer_refused'    => ['label' => 'Customer Refused',     'icon' => 'person-x-fill',     'color' => '#fb923c', 'bg' => 'rgba(251,146,60,.12)'],
    'damaged_in_transit'  => ['label' => 'Damaged in Transit',   'icon' => 'box-seam',          'color' => '#f87171', 'bg' => 'rgba(248,113,113,.12)'],
    'returned_to_sender'  => ['label' => 'Returned to Sender',   'icon' => 'arrow-return-left', 'color' => '#a78bfa', 'bg' => 'rgba(167,139,250,.12)'],
    'delayed'             => ['label' => 'Delayed',              'icon' => 'clock-history',     'color' => '#fbbf24', 'bg' => 'rgba(251,191,36,.12)'],
    'partial_delivery'    => ['label' => 'Partial Delivery',     'icon' => 'box-arrow-in-down', 'color' => '#38bdf8', 'bg' => 'rgba(56,189,248,.12)'],
];
$soNumber = $sales_order['order_number'] ?? '';
$soId     = (int)($sales_order['id'] ?? 0);
$customerCountry = $sales_order['country'] ?? ($sales_order['ship_country'] ?? ($do['destination_country'] ?? ''));
?>
<style>
/* ── DO View ─────────────────────────────────────────── */
.do-page-header{margin-bottom:1.5rem}
.do-page-header h2{font-size:1.5rem;font-weight:700;margin:0;color:var(--cl-text-primary,#f1f5f9)}
.do-meta-line{font-size:.8rem;color:var(--cl-text-muted,#64748b);margin-top:.35rem}
.do-meta-line a{color:#60a5fa;text-decoration:none}
.do-meta-line a:hover{text-decoration:underline}

/* Status pills */
.do-pill{display:inline-flex;align-items:center;gap:4px;padding:4px 12px;border-radius:20px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
.do-pill-draft{background:rgba(250,204,21,.15);color:#fbbf24;border:1px solid rgba(250,204,21,.25)}
.do-pill-confirmed{background:rgba(52,211,153,.12);color:#34d399;border:1px solid rgba(52,211,153,.2)}
.do-pill-shipped{background:rgba(56,189,248,.12);color:#38bdf8;border:1px solid rgba(56,189,248,.2)}
.do-pill-delivered{background:rgba(96,165,250,.12);color:#60a5fa;border:1px solid rgba(96,165,250,.2)}
.do-pill-overdue{background:rgba(248,113,113,.12);color:#f87171;border:1px solid rgba(248,113,113,.2)}
.do-pill-track{background:rgba(251,191,36,.1);color:#fbbf24;border:1px solid rgba(251,191,36,.2)}
.do-pill-track-ok{background:rgba(52,211,153,.1);color:#34d399;border:1px solid rgba(52,211,153,.2)}

/* Section card */
.do-section{background:var(--cl-surface,#1e293b);border:1px solid var(--cl-border,#334155);border-radius:10px;margin-bottom:1rem;overflow:hidden}
.do-section-head{display:flex;align-items:center;gap:.5rem;padding:.7rem 1rem;border-bottom:1px solid var(--cl-border,#334155);font-size:.8rem;font-weight:600;color:var(--cl-text-secondary,#cbd5e1)}
.do-section-body{padding:1rem}

/* Detail rows */
.do-detail{display:grid;grid-template-columns:140px 1fr;gap:4px 12px;font-size:.83rem;margin-bottom:.35rem}
.do-detail dt{color:var(--cl-text-muted,#64748b);font-weight:600}
.do-detail dd{color:var(--cl-text-primary,#f1f5f9);margin:0}
.do-detail dd a{color:#60a5fa;text-decoration:none}

/* Items table */
.do-items-table{width:100%;border-collapse:collapse}
.do-items-table thead th{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--cl-text-muted,#64748b);padding:.55rem .75rem;border-bottom:1px solid var(--cl-border,#334155);background:var(--cl-surface-alt,#162033)}
.do-items-table tbody td{padding:.6rem .75rem;font-size:.84rem;color:var(--cl-text-primary,#f1f5f9);border-bottom:1px solid var(--cl-divider,#1e293b);vertical-align:middle}
.do-items-table tbody tr:last-child td{border-bottom:none}
.do-items-table tbody tr:hover{background:rgba(99,102,241,.04)}
.do-pcode{font-weight:600;font-size:.84rem}
.do-pname{font-size:.78rem;color:var(--cl-text-muted,#64748b)}

/* Qty input */
.do-qty-input{width:85px;padding:5px 8px;font-size:.85rem;font-weight:600;text-align:right;background:var(--cl-surface-alt,#162033);border:1px solid var(--cl-border,#334155);border-radius:6px;color:var(--cl-text-primary,#f1f5f9);transition:border-color .2s}
.do-qty-input:focus{outline:none;border-color:#6366f1;box-shadow:0 0 0 2px rgba(99,102,241,.2)}
.do-qty-msg{font-size:.68rem;min-height:.9rem;margin-top:2px;text-align:right}

/* Summary bar */
.do-summary-bar{display:flex;align-items:center;gap:1rem;padding:.65rem 1rem;background:var(--cl-surface-alt,#162033);border-top:1px solid var(--cl-border,#334155);font-size:.8rem;color:var(--cl-text-secondary,#cbd5e1)}

/* Shipment form */
.do-field label{font-size:.78rem;font-weight:600;color:var(--cl-text-secondary,#cbd5e1);margin-bottom:3px;display:block}
.do-field .hint{font-size:.7rem;color:var(--cl-text-muted,#64748b);margin-top:2px}
.do-inline-btn{font-size:.72rem;color:#818cf8;cursor:pointer;background:none;border:none;padding:0;text-decoration:none}
.do-inline-btn:hover{color:#a5b4fc;text-decoration:underline}

/* Tracking inline form */
.do-tracking-form{background:rgba(251,191,36,.06);border:1px solid rgba(251,191,36,.15);border-radius:8px;padding:.85rem 1rem;margin-top:.75rem;display:none}
.do-tracking-form.open{display:block}
.do-modal-label{font-size:.76rem;font-weight:600;color:var(--cl-text-secondary,#cbd5e1);margin-bottom:3px}
/* Select2 overrides for the country select inside DO section */
.do-section-body .select2-container .select2-selection--single{height:31px;line-height:31px;background:var(--cl-surface-alt,#162033);border:1px solid var(--cl-border,#334155);border-radius:4px}
.do-section-body .select2-container .select2-selection__rendered{line-height:31px;font-size:.82rem;color:var(--cl-text-primary,#f1f5f9);padding-left:8px}
.do-section-body .select2-container .select2-selection__arrow{height:29px}

/* Timeline */
.tl-wrap{background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%);border-radius:0 0 10px 10px;padding:1.75rem 1.5rem 1.5rem}
.tl-heading{font-size:.72rem;font-weight:700;color:var(--cl-text-muted,#64748b);text-transform:uppercase;letter-spacing:.08em;margin-bottom:2rem;display:flex;align-items:center;gap:.4rem}
.tl-bar{position:relative;display:flex;justify-content:space-between;align-items:flex-start}
.tl-bar::before{content:'';position:absolute;top:12px;left:0;right:0;height:2px;background:#334155;border-radius:1px}
.tl-progress-fill{position:absolute;top:12px;left:0;height:2px;background:linear-gradient(90deg,#10b981,#34d399);border-radius:1px;transition:width .6s ease}
.tl-step{position:relative;flex:1;display:flex;flex-direction:column;align-items:center;z-index:2}
.tl-dot{width:24px;height:24px;border-radius:50%;border:2.5px solid #475569;background:#0f172a;margin-bottom:.7rem;flex-shrink:0;transition:all .3s}
.tl-dot.done{background:#10b981;border-color:#10b981;box-shadow:0 0 8px rgba(16,185,129,.35)}
.tl-dot.current{border-color:#fbbf24;background:#0f172a;box-shadow:0 0 8px rgba(251,191,36,.3)}
.tl-step-info{text-align:center;max-width:120px}
.tl-step-label{font-size:.73rem;font-weight:600;color:#e2e8f0;line-height:1.3;margin-bottom:4px}
.tl-step-date{font-size:.68rem;color:#94a3b8}
.tl-step-time{font-size:.64rem;color:#64748b}
.tl-chip{display:inline-block;font-size:.6rem;font-weight:700;padding:2px 8px;border-radius:3px;text-transform:uppercase;margin-top:5px;letter-spacing:.03em}
.tl-chip.done{background:rgba(16,185,129,.15);color:#6ee7b7}
.tl-chip.pend{background:rgba(251,191,36,.12);color:#fcd34d}
.tl-duration{position:absolute;top:-18px;left:50%;transform:translateX(-50%);font-size:.58rem;color:#64748b;white-space:nowrap;background:var(--cl-surface,#1e293b);padding:1px 6px;border-radius:3px;border:1px solid #334155}
</style>

<?php $flashOk = session()->getFlashdata('success'); $flashErr = session()->getFlashdata('error'); ?>
<?php if ($flashOk): ?><div class="alert alert-success alert-dismissible fade show mb-3"><i class="bi bi-check-circle-fill me-2"></i><?= esc($flashOk) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($flashErr): ?><div class="alert alert-danger alert-dismissible fade show mb-3"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= esc($flashErr) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<!-- ════ HEADER ════ -->
<div class="do-page-header d-flex justify-content-between align-items-start flex-wrap gap-3">
    <div>
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <h2><?= esc($do['do_number'] ?? 'DO-Draft') ?></h2>
            <span class="do-pill do-pill-<?= esc($status) ?>">
                <i class="bi bi-<?= $isDraft ? 'pencil-square' : ($status === 'delivered' ? 'check-all' : 'check-circle') ?>"></i>
                <?= esc(ucfirst($status)) ?>
            </span>
            <?php if ($isConfirmed && $deliveryStatus && $deliveryStatus !== 'delivered'): ?>
                <?php $dsCfg = $statusConfig[$deliveryStatus] ?? null; ?>
                <?php if ($dsCfg): ?>
                <span class="do-pill" style="background:<?= $dsCfg['bg'] ?>;color:<?= $dsCfg['color'] ?>;border:1px solid <?= $dsCfg['color'] ?>33">
                    <i class="bi bi-<?= $dsCfg['icon'] ?>"></i>
                    <?= $dsCfg['label'] ?>
                </span>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($isConfirmed): ?>
                <span class="do-pill <?= $hasTracking ? 'do-pill-track-ok' : 'do-pill-track' ?>">
                    <i class="bi bi-<?= $hasTracking ? 'truck' : 'hourglass-split' ?>"></i>
                    <?= $hasTracking ? 'Tracking Added' : 'Tracking Pending' ?>
                </span>
            <?php endif; ?>
        </div>
        <div class="do-meta-line">
            <?php if ($soId): ?>Sales Order <a href="<?= site_url('sales-orders/view/'.$soId) ?>"><?= esc($soNumber) ?></a> &middot; <?php endif; ?>
            Created <?= date('d M Y, H:i', strtotime($do['created_at'] ?? 'now')) ?>
            <?php if ($isConfirmed && !empty($do['shipped_at'])): ?> &middot; Shipped <?= date('d M Y, H:i', strtotime($do['shipped_at'])) ?><?php endif; ?>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($isDraft): ?>
            <button type="button" class="btn btn-sm btn-outline-danger" id="cancelDoBtn"><i class="bi bi-x-circle me-1"></i>Cancel</button>
            <button type="button" class="btn btn-sm btn-success" id="openShipBtn"><i class="bi bi-send-fill me-1"></i>Confirm &amp; Ship</button>
        <?php elseif ($isConfirmed): ?>
            <button type="button" class="btn btn-sm btn-outline-warning js-add-tracking-btn"><i class="bi bi-upc-scan me-1"></i><?= $hasTracking ? 'Update Tracking' : 'Add Tracking' ?></button>
            <?php if (!$isDelivered): ?>
                <button type="button" class="btn btn-sm btn-primary" id="updateDeliveryBtn"><i class="bi bi-clipboard-check me-1"></i>Update Delivery Status</button>
            <?php endif; ?>
        <?php endif; ?>
        <a href="<?= site_url('delivery-orders/print/' . (!empty($do['public_id']) ? $do['public_id'] : $doId)) ?>" class="btn btn-sm btn-outline-dark" target="_blank" rel="noopener"><i class="bi bi-printer me-1"></i>Print</a>
        <a href="<?= site_url('delivery-orders') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>All DOs</a>
    </div>
</div>

<!-- ════ SHIPMENT DETAILS (confirmed only) ════ -->
<?php if ($isConfirmed): ?>
<div class="do-section">
    <div class="do-section-head" style="border-bottom-color:rgba(52,211,153,.2)"><i class="bi bi-truck" style="color:#34d399"></i> <span style="color:#34d399">Shipment Details</span></div>
    <div class="do-section-body">
        <div class="row g-4">
            <div class="col-md-4">
                <?php if (!empty($shippingVendor)): ?><dl class="do-detail"><dt>Carrier / Vendor</dt><dd class="fw-semibold"><?= esc($shippingVendor['name']) ?></dd></dl><?php endif; ?>
                <?php if (!empty($shippingService)): ?><dl class="do-detail"><dt>Service</dt><dd><?= esc($shippingService['carrier'] . ' - ' . $shippingService['service_name']) ?></dd></dl><?php endif; ?>
                <?php if (!empty($do['destination_country'])): ?><dl class="do-detail"><dt>Destination</dt><dd><?= esc($do['destination_country']) ?></dd></dl><?php endif; ?>
            </div>
            <div class="col-md-4">
                <?php if (!empty($do['final_weight_kg'])): ?><dl class="do-detail"><dt>Final Weight</dt><dd><?= number_format((float)$do['final_weight_kg'],3) ?> kg</dd></dl><?php endif; ?>
                <?php if (!empty($do['shipping_cost_pkr'])): ?><dl class="do-detail"><dt>Cost Paid</dt><dd class="fw-bold" style="color:#34d399">PKR <?= number_format((float)$do['shipping_cost_pkr'],2) ?></dd></dl><?php endif; ?>
                <?php if (!empty($do['shipping_po_id'])): ?>
                    <dl class="do-detail"><dt>Shipping PO</dt><dd><a href="<?= site_url('purchase/orders/' . $do['shipping_po_id']) ?>" style="color:#818cf8"><i class="bi bi-file-earmark-text me-1"></i><?php
                        $spoPo = \Config\Database::connect()->table('purchase_orders')->select('po_number,status')->where('id', $do['shipping_po_id'])->get()->getRowArray();
                        echo esc($spoPo['po_number'] ?? 'PO-' . $do['shipping_po_id']);
                        $spoStatus = strtolower($spoPo['status'] ?? '');
                    ?></a> <span class="badge <?= $spoStatus === 'confirmed' ? 'bg-success' : ($spoStatus === 'draft' ? 'bg-secondary' : 'bg-info') ?>" style="font-size:.6rem"><?= ucfirst($spoStatus) ?></span></dd></dl>
                <?php endif; ?>
                <?php if (!empty($do['shipping_bill_id'])): ?>
                    <dl class="do-detail"><dt>Shipping Bill</dt><dd><a href="<?= site_url('vendor-bills/' . $do['shipping_bill_id']) ?>" style="color:#fbbf24"><i class="bi bi-receipt me-1"></i>Bill #<?= (int)$do['shipping_bill_id'] ?></a> <span class="badge bg-warning text-dark" style="font-size:.6rem"><?php
                        $sbill = \Config\Database::connect()->table('vendor_bills')->select('status')->where('id', $do['shipping_bill_id'])->get()->getRowArray();
                        echo ucfirst($sbill['status'] ?? 'unknown');
                    ?></span></dd></dl>
                <?php endif; ?>
                <?php if (!empty($do['shipping_vendor_id']) && empty($do['shipping_po_id'])): ?>
                    <dl class="do-detail"><dt>Shipping PO</dt><dd>
                        <button id="createShippingPoBtn" class="btn btn-sm btn-outline-info" style="font-size:.72rem;padding:.18rem .6rem">
                            <i class="bi bi-plus-circle me-1"></i>Create Shipping PO &amp; Bill
                        </button>
                        <div id="createShippingPoResult" style="font-size:.73rem;margin-top:3px"></div>
                    </dd></dl>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <?php if ($hasTracking): ?>
                    <dl class="do-detail"><dt>Tracking #</dt><dd>
                    <?php if (!empty($do['tracking_url'])): ?><a href="<?= esc($do['tracking_url']) ?>" target="_blank" rel="noopener" class="fw-semibold"><?= esc($do['tracking_number']) ?> <i class="bi bi-box-arrow-up-right" style="font-size:.65rem"></i></a>
                    <?php else: ?><span class="fw-semibold"><?= esc($do['tracking_number']) ?></span><?php endif; ?>
                    </dd></dl>
                <?php else: ?>
                    <div style="font-size:.8rem;color:var(--cl-text-muted,#64748b)"><i class="bi bi-hourglass-split me-1"></i>Tracking not yet added</div>
                <?php endif; ?>
                <?php if (!empty($do['shipping_notes'])): ?><dl class="do-detail"><dt>Notes</dt><dd style="color:var(--cl-text-muted,#64748b)"><?= esc($do['shipping_notes']) ?></dd></dl><?php endif; ?>
            </div>
        </div>

        <!-- Parcel Photos Section -->
        <div class="mt-3 pt-3" style="border-top:1px solid var(--cl-border,#334155)">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div style="font-size:.78rem;font-weight:600;color:var(--cl-text-secondary,#cbd5e1)">
                    <i class="bi bi-camera me-1"></i>Parcel Photos
                    <?php if (!empty($parcelImages)): ?>
                        <span style="font-size:.7rem;font-weight:400;color:var(--cl-text-muted,#64748b);margin-left:4px">(<?= count($parcelImages) ?>)</span>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-sm btn-outline-light" id="parcelUploadToggleBtn" style="font-size:.72rem;padding:2px 10px;border-color:var(--cl-border,#334155)">
                    <i class="bi bi-upload me-1"></i>Add Photos
                </button>
            </div>
            <?php if (!empty($parcelImages)): ?>
            <div id="parcelImageGrid" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:0">
                <?php foreach ($parcelImages as $idx => $img): ?>
                <div class="parcel-thumb-wrap" data-img-id="<?= (int)$img['id'] ?>" style="position:relative;max-width:160px;border-radius:8px;overflow:hidden;border:1px solid var(--cl-border,#334155);flex-shrink:0;background:#0f172a">
                    <img src="<?= base_url(esc($img['image_path'])) ?>" alt="Parcel photo <?= $idx+1 ?>"
                         data-lightbox-src="<?= base_url(esc($img['image_path'])) ?>"
                         data-lightbox-index="<?= $idx ?>"
                         class="parcel-thumb"
                         style="width:100%;height:auto;max-height:160px;object-fit:contain;display:block;cursor:zoom-in;transition:opacity .15s"
                         onmouseover="this.style.opacity='.8'" onmouseout="this.style.opacity='1'">
                    <button type="button" class="parcel-thumb-del" data-img-id="<?= (int)$img['id'] ?>"
                            title="Delete this photo"
                            style="position:absolute;top:3px;right:3px;background:rgba(0,0,0,.65);border:none;border-radius:4px;color:#f87171;width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:.65rem;padding:0;cursor:pointer;opacity:0;transition:opacity .15s">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div id="parcelImageGrid" style="font-size:.78rem;color:var(--cl-text-muted,#64748b)"><i class="bi bi-image me-1"></i>No parcel photos uploaded yet</div>
            <?php endif; ?>
            <!-- Inline upload form (hidden by default) -->
            <div id="parcelUploadFormWrap" class="do-tracking-form">
                <div class="fw-semibold mb-2" style="font-size:.82rem;color:var(--cl-text-primary,#f1f5f9)"><i class="bi bi-camera me-1"></i>Upload Parcel Photos</div>
                <div class="row g-2 align-items-start">
                    <div class="col-md-6">
                        <label class="form-label" style="font-size:.75rem;font-weight:600">Select Images <span class="text-muted fw-normal">(JPG, PNG, WebP — max 5 MB each — multiple allowed)</span></label>
                        <input type="file" id="parcelImageFileInput" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp" multiple>
                    </div>
                    <div class="col-12">
                        <div id="parcelInlinePreviews" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:4px"></div>
                    </div>
                    <div class="col-auto d-flex gap-1 align-items-center">
                        <button type="button" class="btn btn-sm btn-primary" id="saveParcelImageBtn"><i class="bi bi-upload me-1"></i>Upload</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="cancelParcelImageBtn">Cancel</button>
                    </div>
                </div>
                <div id="parcelUploadMsg" class="mt-2" style="font-size:.78rem"></div>
            </div>
        </div>

        <!-- Tracking Documents Section -->
        <div class="mt-3 pt-3" style="border-top:1px solid var(--cl-border,#334155)">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div style="font-size:.78rem;font-weight:600;color:var(--cl-text-secondary,#cbd5e1)">
                    <i class="bi bi-file-earmark-text me-1"></i>Tracking Documents
                    <?php if (!empty($trackingDocs)): ?>
                        <span style="font-size:.7rem;font-weight:400;color:var(--cl-text-muted,#64748b);margin-left:4px">(<?= count($trackingDocs) ?>)</span>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-sm btn-outline-light js-add-tracking-btn" style="font-size:.72rem;padding:2px 10px;border-color:var(--cl-border,#334155)">
                    <i class="bi bi-upc-scan me-1"></i><?= $hasTracking ? 'Update Tracking' : 'Add Tracking' ?>
                </button>
            </div>
            <?php if (!empty($trackingDocs)): ?>
            <div id="trackingDocGrid" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:0">
                <?php foreach ($trackingDocs as $doc):
                    $ext = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
                    $isImg = in_array($ext, ['jpg','jpeg','png','webp']);
                    $docUrl = base_url(esc($doc['file_path']));
                    $docName = esc($doc['original_name'] ?: basename($doc['file_path']));
                ?>
                <div class="trk-doc-wrap" data-doc-id="<?= (int)$doc['id'] ?>" style="position:relative;border-radius:8px;overflow:hidden;border:1px solid var(--cl-border,#334155);flex-shrink:0;background:#0f172a;max-width:160px">
                    <?php if ($isImg): ?>
                    <img src="<?= $docUrl ?>" alt="<?= $docName ?>"
                         data-lightbox-trk-src="<?= $docUrl ?>"
                         class="trk-doc-img"
                         style="width:100%;height:auto;max-height:160px;object-fit:contain;display:block;cursor:zoom-in;transition:opacity .15s"
                         onmouseover="this.style.opacity='.8'" onmouseout="this.style.opacity='1'">
                    <?php else: ?>
                    <a href="<?= $docUrl ?>" target="_blank" rel="noopener" style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:14px 12px;text-decoration:none;min-width:110px">
                        <i class="bi bi-file-earmark-pdf" style="font-size:2rem;color:#f87171"></i>
                        <span style="font-size:.65rem;color:#94a3b8;margin-top:4px;word-break:break-all;text-align:center;max-width:130px"><?= $docName ?></span>
                    </a>
                    <?php endif; ?>
                    <button type="button" class="trk-doc-del" data-doc-id="<?= (int)$doc['id'] ?>"
                            title="Delete document"
                            style="position:absolute;top:3px;right:3px;background:rgba(0,0,0,.65);border:none;border-radius:4px;color:#f87171;width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:.65rem;padding:0;cursor:pointer;opacity:0;transition:opacity .15s">
                        <i class="bi bi-x-lg"></i>
                    </button>
                    <?php if ($isImg): ?>
                    <div style="padding:2px 6px;font-size:.62rem;color:#64748b;background:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px" title="<?= $docName ?>"><?= $docName ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div id="trackingDocGrid" style="font-size:.78rem;color:var(--cl-text-muted,#64748b)"><i class="bi bi-file-earmark me-1"></i>No tracking documents uploaded yet</div>
            <?php endif; ?>
        </div>

        <!-- Inline tracking form -->
        <div class="do-tracking-form" id="trackingFormWrap">
            <div class="fw-semibold mb-2" style="font-size:.82rem;color:var(--cl-text-primary,#f1f5f9)"><i class="bi bi-upc-scan me-1"></i><?= $hasTracking ? 'Update Tracking' : 'Add Tracking Number' ?></div>
            <div class="row g-2 align-items-end mb-2">
                <div class="col-md-4"><label class="form-label" style="font-size:.75rem;font-weight:600">Tracking Number</label><input type="text" id="trackingNumberInput" class="form-control form-control-sm" value="<?= esc($do['tracking_number'] ?? '') ?>" placeholder="e.g. 1Z999AA10123456784"></div>
                <div class="col-md-5"><label class="form-label" style="font-size:.75rem;font-weight:600">Tracking URL <span class="text-muted fw-normal">(optional)</span></label><input type="url" id="trackingUrlInput" class="form-control form-control-sm" value="<?= esc($do['tracking_url'] ?? '') ?>" placeholder="https://track.dhl.com/..."></div>
                <div class="col-auto d-flex gap-1"><button type="button" class="btn btn-sm btn-primary" id="saveTrackingBtn">Save</button><button type="button" class="btn btn-sm btn-outline-secondary" id="cancelTrackingBtn">Cancel</button></div>
            </div>
            <div class="row g-2 align-items-start">
                <div class="col-md-9">
                    <label class="form-label" style="font-size:.75rem;font-weight:600">Attach Documents <span class="text-muted fw-normal">(airway bills, cargo docs — images or PDF — multiple allowed)</span></label>
                    <input type="file" id="trackingDocInput" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp,application/pdf" multiple>
                </div>
            </div>
            <div id="trackingDocPreviews" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px"></div>
            <div id="trackingDocUploadMsg" style="font-size:.78rem;margin-top:4px"></div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ════ DELIVERY TRACKING (confirmed only) ════ -->
<?php if ($isConfirmed): ?>
<?php
    // Calculate days since shipped
    $daysSinceShipped = 0;
    $estDeliveryDate = null;
    $isOverdue = false;
    if ($shippedAt) {
        $shippedTime = strtotime($shippedAt);
        $daysSinceShipped = (int)floor((time() - $shippedTime) / 86400);
        if ($estDays > 0) {
            $estDeliveryDate = date('d M Y', strtotime("+{$estDays} days", $shippedTime));
            $isOverdue = $daysSinceShipped > $estDays && !$isDelivered;
        }
    }
    $daysProgress = ($estDays > 0) ? min(100, round(($daysSinceShipped / $estDays) * 100)) : 0;

    // Delivery status config
    $currentStatusCfg = $statusConfig[$deliveryStatus] ?? null;
?>
<div class="do-section">
    <div class="do-section-head" style="border-bottom-color:<?= $isOverdue ? 'rgba(248,113,113,.3)' : ($isDelivered ? 'rgba(52,211,153,.2)' : 'rgba(99,102,241,.2)') ?>">
        <i class="bi bi-<?= $isDelivered ? 'check-circle-fill' : ($isOverdue ? 'exclamation-triangle-fill' : 'truck') ?>" style="color:<?= $isDelivered ? '#34d399' : ($isOverdue ? '#f87171' : '#818cf8') ?>"></i>
        <span style="color:<?= $isDelivered ? '#34d399' : ($isOverdue ? '#f87171' : '#818cf8') ?>">Delivery Tracking</span>
        <?php if ($isOverdue): ?>
            <span class="ms-2 badge" style="background:#f8717133;color:#f87171;font-size:.65rem;">OVERDUE by <?= $daysSinceShipped - $estDays ?> day<?= ($daysSinceShipped - $estDays) > 1 ? 's' : '' ?></span>
        <?php endif; ?>
    </div>
    <div class="do-section-body">
        <div class="row g-4">
            <!-- Left: Shipping Progress -->
            <div class="col-md-5">
                <div style="font-size:.78rem;font-weight:600;color:var(--cl-text-secondary,#cbd5e1);margin-bottom:.8rem;">
                    <i class="bi bi-clock-history me-1"></i>Shipping Progress
                </div>

                <div class="d-flex justify-content-between" style="font-size:.75rem;color:var(--cl-text-muted,#64748b);margin-bottom:4px;">
                    <span>Shipped <?= $shippedAt ? date('d M Y', strtotime($shippedAt)) : '—' ?></span>
                    <span><?= $estDays > 0 ? 'Est. ' . $estDeliveryDate : 'No estimate set' ?></span>
                </div>

                <?php if ($estDays > 0): ?>
                <div style="background:rgba(255,255,255,.06);height:8px;border-radius:4px;overflow:hidden;margin-bottom:.6rem;">
                    <div style="width:<?= $daysProgress ?>%;height:100%;background:<?= $isOverdue ? '#f87171' : ($isDelivered ? '#34d399' : '#818cf8') ?>;border-radius:4px;transition:width .5s;"></div>
                </div>
                <?php endif; ?>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem;">
                    <div style="background:var(--cl-surface-alt,#162033);border-radius:6px;padding:.5rem .7rem;text-align:center;">
                        <div style="font-size:1.3rem;font-weight:700;color:var(--cl-text-primary,#f1f5f9);"><?= $daysSinceShipped ?></div>
                        <div style="font-size:.68rem;color:var(--cl-text-muted,#64748b);">Days Since Shipped</div>
                    </div>
                    <div style="background:var(--cl-surface-alt,#162033);border-radius:6px;padding:.5rem .7rem;text-align:center;">
                        <div style="font-size:1.3rem;font-weight:700;color:<?= $isOverdue ? '#f87171' : '#818cf8' ?>;"><?= $estDays > 0 ? $estDays : '—' ?></div>
                        <div style="font-size:.68rem;color:var(--cl-text-muted,#64748b);">Estimated Days</div>
                    </div>
                </div>

                <?php if ($estDays === 0 && !$isDelivered): ?>
                <div style="margin-top:.7rem;">
                    <div style="font-size:.75rem;color:var(--cl-text-muted,#64748b);margin-bottom:4px;"><i class="bi bi-calendar-plus me-1"></i>Set estimated delivery days</div>
                    <div class="d-flex gap-2">
                        <input type="number" class="form-control form-control-sm" id="estDaysInput" min="1" max="365" placeholder="e.g. 7" style="width:100px;">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="saveEstDaysBtn">Save</button>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right: Delivery Status -->
            <div class="col-md-7">
                <div style="font-size:.78rem;font-weight:600;color:var(--cl-text-secondary,#cbd5e1);margin-bottom:.8rem;">
                    <i class="bi bi-clipboard-check me-1"></i>Delivery Status
                </div>

                <?php if ($currentStatusCfg): ?>
                    <!-- Current status display -->
                    <div style="background:<?= $currentStatusCfg['bg'] ?>;border:1px solid <?= $currentStatusCfg['color'] ?>33;border-radius:8px;padding:.8rem 1rem;margin-bottom:.7rem;">
                        <div style="font-size:.9rem;font-weight:700;color:<?= $currentStatusCfg['color'] ?>;">
                            <i class="bi bi-<?= $currentStatusCfg['icon'] ?> me-1"></i><?= $currentStatusCfg['label'] ?>
                        </div>
                        <?php if (!empty($do['delivery_confirmed_at'])): ?>
                            <div style="font-size:.72rem;color:var(--cl-text-muted,#64748b);margin-top:3px;">
                                Updated: <?= date('d M Y, H:i', strtotime($do['delivery_confirmed_at'])) ?>
                                <?php if ($shippedAt): ?>
                                    &middot; <?= (int)floor((strtotime($do['delivery_confirmed_at']) - strtotime($shippedAt)) / 86400) ?> days after shipping
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($do['delivery_notes'])): ?>
                            <div style="font-size:.78rem;color:var(--cl-text-secondary,#cbd5e1);margin-top:.5rem;padding-top:.5rem;border-top:1px solid <?= $currentStatusCfg['color'] ?>22;">
                                <i class="bi bi-chat-left-text me-1"></i><?= esc($do['delivery_notes']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- No status yet -->
                    <div style="background:rgba(255,255,255,.03);border:1px dashed rgba(255,255,255,.1);border-radius:8px;padding:1rem;text-align:center;margin-bottom:.7rem;">
                        <div style="font-size:.85rem;color:var(--cl-text-muted,#64748b);">
                            <i class="bi bi-hourglass-split me-1"></i>Awaiting delivery confirmation
                        </div>
                        <div style="font-size:.72rem;color:var(--cl-text-muted,#64748b);margin-top:4px;">
                            Click "Update Delivery Status" when the shipment reaches its destination
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Delivery status update form (hidden, toggled by button) -->
                <div id="deliveryStatusForm" style="display:none;background:rgba(99,102,241,.06);border:1px solid rgba(99,102,241,.15);border-radius:8px;padding:.85rem 1rem;">
                    <div class="fw-semibold mb-2" style="font-size:.82rem;color:var(--cl-text-primary,#f1f5f9);">
                        <i class="bi bi-clipboard-check me-1"></i>Update Delivery Status
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-5">
                            <label class="form-label" style="font-size:.75rem;font-weight:600;">Status</label>
                            <select class="form-select form-select-sm" id="deliveryStatusSelect">
                                <option value="">-- Select Status --</option>
                                <option value="delivered">&#10004; Delivered Successfully</option>
                                <option value="partial_delivery">&#9744; Partial Delivery</option>
                                <option value="delayed">&#9201; Delayed</option>
                                <option value="lost">&#9888; Lost in Transit</option>
                                <option value="damaged_in_transit">&#128465; Damaged in Transit</option>
                                <option value="customer_refused">&#128581; Customer Refused</option>
                                <option value="returned_to_sender">&#8617; Returned to Sender</option>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label" style="font-size:.75rem;font-weight:600;">Notes <span class="text-muted fw-normal">(optional)</span></label>
                            <input type="text" class="form-control form-control-sm" id="deliveryNotesInput" placeholder="e.g. Received by customer, signed by Ali">
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-primary" id="saveDeliveryStatusBtn"><i class="bi bi-check-circle me-1"></i>Save Status</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="cancelDeliveryStatusBtn">Cancel</button>
                    </div>
                    <div id="deliveryStatusMsg" class="mt-2" style="font-size:.78rem;min-height:1rem;"></div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ════ ORDER ITEMS ════ -->
<div class="do-section">
    <div class="do-section-head"><i class="bi bi-boxes"></i> Order Items</div>
    <div style="overflow-x:auto">
        <table class="do-items-table">
            <thead><tr>
                <th style="width:50px"></th>
                <th>Product</th>
                <th class="text-end">Ordered</th>
                <th class="text-end">Ready</th>
                <th class="text-end" style="width:120px">Qty to Ship</th>
            </tr></thead>
            <tbody>
            <?php if (!empty($do['lines'])): foreach ($do['lines'] as $line):
                $lineId = $line['id'] ?? 0;
                $orderedQty = (float)($line['quantity_ordered'] ?? 0);
                $readyQty = (float)($line['ready_qty'] ?? 0);
                $qtyShip = (float)($line['qty_to_ship'] ?? $readyQty);
                $img = $line['product_image_url'] ?? base_url('assets/images/no-image.png');
            ?>
            <tr>
                <td><img src="<?= esc($img) ?>" alt="" class="js-product-hover-thumb" data-preview-src="<?= esc($img) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:4px;opacity:.85" onerror="this.onerror=null;this.src='<?= base_url('assets/images/no-image.png') ?>';this.setAttribute('data-preview-src','<?= base_url('assets/images/no-image.png') ?>');"></td>
                <td>
                    <div class="do-pcode"><?= esc($line['product_code'] ?? '') ?></div>
                    <div class="do-pname"><?= esc($line['product_name'] ?? '') ?></div>
                    <?php if (!empty($line['description'])): ?><div class="do-pname" style="font-size:.72rem"><?= esc($line['description']) ?></div><?php endif; ?>
                </td>
                <td class="text-end fw-semibold"><?= number_format($orderedQty, 2) ?></td>
                <td class="text-end" style="color:var(--cl-text-muted,#64748b)"><?= number_format($readyQty, 2) ?></td>
                <td class="text-end">
                    <?php if ($isDraft): ?>
                        <input type="number" class="do-qty-input qty-to-ship" data-line-id="<?= (int)$lineId ?>" data-ready-qty="<?= $readyQty ?>" value="<?= number_format($qtyShip, 2, '.', '') ?>" step="0.01" min="0" max="<?= $readyQty ?>">
                        <div class="do-qty-msg" data-line-id="<?= (int)$lineId ?>"></div>
                    <?php else: ?>
                        <span class="fw-semibold"><?= number_format($qtyShip, 2) ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="5" class="text-center py-4" style="color:var(--cl-text-muted,#64748b)">No items found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="do-summary-bar">
        <i class="bi bi-box-seam"></i>
        <?php $totalUnits = array_sum(array_column($do['lines'] ?? [], 'qty_to_ship')); ?>
        <span>Total units: <strong><?= number_format((float)$totalUnits, 2) ?></strong></span>
        <?php if (!empty($suggestedWeight)): ?>
            <span style="color:var(--cl-text-muted,#64748b)"><i class="bi bi-info-circle me-1"></i>Quotation weight: <?= number_format((float)$suggestedWeight, 3) ?> kg</span>
        <?php endif; ?>
    </div>
</div>

<!-- ════ CONFIRM & SHIP PANEL (draft only) ════ -->
<?php if ($isDraft): ?>
<div id="shipmentPanelWrap" style="display:none">
<div class="do-section">
    <div class="do-section-head" style="border-bottom-color:rgba(52,211,153,.2)">
        <i class="bi bi-send-fill" style="color:#34d399"></i>
        <span style="color:#34d399">Confirm Shipment</span>
        <span class="ms-auto" style="font-size:.72rem;color:var(--cl-text-muted,#64748b)">Fill carrier details and confirm</span>
    </div>
    <div class="do-section-body">
    <form method="post" action="<?= site_url('delivery-orders/confirm/'.$doId) ?>" id="shipmentForm" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <div class="row g-3 mb-3">
            <!-- Weight -->
            <div class="col-md-2">
                <div class="do-field">
                    <label for="final_weight_kg"><i class="bi bi-speedometer2 me-1"></i>Weight (kg) <span class="text-danger">*</span></label>
                    <input type="number" class="form-control form-control-sm" id="final_weight_kg" name="final_weight_kg" step="0.001" min="0.001" required value="<?= !empty($suggestedWeight) ? number_format((float)$suggestedWeight, 3, '.', '') : '' ?>" placeholder="e.g. 2.500">
                    <?php if (!empty($suggestedWeight)): ?><div class="hint"><i class="bi bi-info-circle me-1"></i>From quotation</div><?php else: ?><div class="hint">Actual packed weight</div><?php endif; ?>
                </div>
            </div>
            <!-- Vendor -->
            <div class="col-md-3">
                <div class="do-field">
                    <label for="shipping_vendor_id"><i class="bi bi-building me-1"></i>Shipping Vendor</label>
                    <select class="form-select form-select-sm" id="shipping_vendor_id" name="shipping_vendor_id">
                        <option value="">-- Select Vendor --</option>
                        <?php foreach ($vendors as $v): ?><option value="<?= (int)$v['id'] ?>"><?= esc($v['name']) ?></option><?php endforeach; ?>
                    </select>
                    <div class="hint"><button type="button" class="do-inline-btn" data-bs-toggle="modal" data-bs-target="#vendorModal"><i class="bi bi-plus-circle me-1"></i>Add new vendor</button></div>
                </div>
            </div>
            <!-- Service -->
            <div class="col-md-3">
                <div class="do-field">
                    <label for="shipping_service_id"><i class="bi bi-box-seam me-1"></i>Shipping Service</label>
                    <select class="form-select form-select-sm" id="shipping_service_id" name="shipping_service_id">
                        <option value="">-- Select Service --</option>
                        <?php foreach ($shippingServices as $svc): ?>
                        <option value="<?= (int)$svc['id'] ?>" data-cost="<?= (float)($svc['cost_pkr'] ?? 0) ?>" data-vendor="<?= (int)($svc['vendor_id'] ?? 0) ?>"><?= esc($svc['carrier'] . ' - ' . $svc['service_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="hint"><button type="button" class="do-inline-btn" id="addServiceInlineBtn"><i class="bi bi-plus-circle me-1"></i>Add new service</button></div>
                </div>
            </div>
            <!-- PKR Cost -->
            <div class="col-md-2">
                <div class="do-field">
                    <label for="shipping_cost_pkr"><i class="bi bi-cash-stack me-1"></i>Amount Paid (PKR)</label>
                    <div class="input-group input-group-sm"><span class="input-group-text fw-bold">PKR</span><input type="number" class="form-control" id="shipping_cost_pkr" name="shipping_cost_pkr" step="0.01" min="0" placeholder="e.g. 4500"></div>
                    <div class="hint">Auto-fills from service</div>
                </div>
            </div>
            <!-- Destination Country -->
            <div class="col-md-2">
                <div class="do-field">
                    <label for="destination_country"><i class="bi bi-globe-americas me-1"></i>Destination Country</label>
                    <select class="form-select form-select-sm do-country-select" id="destination_country" name="destination_country">
                        <option value="">-- Select Country --</option>
                        <?php foreach ($countries as $c): ?>
                        <option value="<?= esc($c['name']) ?>"
                            <?= (strtolower($c['name']) === strtolower(trim($customerCountry ?? '')) || strtolower($c['iso_code']) === strtolower(trim($customerCountry ?? ''))) ? 'selected' : '' ?>>
                            <?= esc($c['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <!-- Tracking # -->
            <div class="col-md-3">
                <div class="do-field">
                    <label for="tracking_number"><i class="bi bi-upc-scan me-1"></i>Tracking Number <span class="fw-normal fst-italic" style="color:var(--cl-text-muted,#64748b)">(optional)</span></label>
                    <input type="text" class="form-control form-control-sm" id="tracking_number" name="tracking_number" placeholder="Can add later">
                </div>
            </div>
            <!-- Tracking URL -->
            <div class="col-md-4">
                <div class="do-field">
                    <label for="tracking_url"><i class="bi bi-link-45deg me-1"></i>Tracking URL <span class="fw-normal fst-italic" style="color:var(--cl-text-muted,#64748b)">(optional)</span></label>
                    <input type="url" class="form-control form-control-sm" id="tracking_url" name="tracking_url" placeholder="https://track.dhl.com/...">
                </div>
            </div>
            <!-- Estimated Delivery Days -->
            <div class="col-md-2">
                <div class="do-field">
                    <label for="estimated_delivery_days"><i class="bi bi-calendar-event me-1"></i>Delivery Days</label>
                    <input type="number" class="form-control form-control-sm" id="estimated_delivery_days" name="estimated_delivery_days" min="1" max="365" step="1" placeholder="e.g. 7">
                    <div class="hint">Estimated shipping time</div>
                </div>
            </div>
            <!-- Notes -->
            <div class="col-md-3">
                <div class="do-field">
                    <label for="shipping_notes"><i class="bi bi-chat-left-text me-1"></i>Notes <span class="fw-normal fst-italic" style="color:var(--cl-text-muted,#64748b)">(optional)</span></label>
                    <textarea class="form-control form-control-sm" id="shipping_notes" name="shipping_notes" rows="1" placeholder="e.g. fragile, special handling..."></textarea>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 pt-3" style="border-top:1px solid var(--cl-border,#334155)">
            <!-- Parcel Image Upload -->
            <div class="flex-grow-1">
                <div class="do-field">
                    <label for="parcel_images_confirm" style="font-size:.78rem;font-weight:600;color:var(--cl-text-secondary,#cbd5e1)"><i class="bi bi-camera me-1"></i>Parcel Photos <span class="fw-normal fst-italic" style="color:var(--cl-text-muted,#64748b)">(optional — multiple allowed)</span></label>
                    <input type="file" class="form-control form-control-sm" id="parcel_images_confirm" name="parcel_images[]" accept="image/jpeg,image/png,image/webp" multiple style="font-size:.78rem">
                    <div class="hint">JPG, PNG, WebP — max 5 MB each</div>
                    <div id="confirmParcelPreviews" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px"></div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 pt-3" style="border-top:1px solid var(--cl-border,#334155)">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="cancelShipBtn"><i class="bi bi-x me-1"></i>Cancel</button>
            <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check-circle-fill me-1"></i>Confirm Shipment</button>
            <div class="ms-auto d-flex align-items-center gap-3">
                <div class="form-check form-check-inline" style="font-size:.78rem">
                    <input class="form-check-input" type="checkbox" id="auto_confirm_po" name="auto_confirm_po" value="1" checked>
                    <label class="form-check-label" for="auto_confirm_po" style="color:var(--cl-text-secondary,#cbd5e1)">Auto-confirm Shipping PO</label>
                </div>
                <div class="form-check form-check-inline" style="font-size:.78rem">
                    <input class="form-check-input" type="checkbox" id="auto_create_bill" name="auto_create_bill" value="1" checked>
                    <label class="form-check-label" for="auto_create_bill" style="color:var(--cl-text-secondary,#cbd5e1)">Auto-create Vendor Bill</label>
                </div>
            </div>
        </div>
    </form>
    </div>
</div>
</div>

<!-- ════ MODAL: Add New Vendor ════ -->
<div class="modal fade" id="vendorModal" tabindex="-1" aria-labelledby="vendorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:var(--cl-surface,#1e293b);border:1px solid #6366f1;border-radius:10px">
            <div class="modal-header" style="border-bottom:1px solid rgba(99,102,241,.25);padding:.65rem 1rem">
                <h6 class="modal-title" id="vendorModalLabel" style="color:#a5b4fc;font-size:.88rem;font-weight:700"><i class="bi bi-person-plus me-2"></i>Add New Shipping Vendor</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="font-size:.7rem"></button>
            </div>
            <div class="modal-body" style="padding:1rem 1.1rem">
                <div class="mb-3">
                    <label class="form-label do-modal-label">Vendor Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="qv_name" placeholder="e.g. TNT Express, FedEx, DHL">
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label do-modal-label">Contact Person <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="text" class="form-control form-control-sm" id="qv_contact" placeholder="e.g. John Smith">
                    </div>
                    <div class="col-6">
                        <label class="form-label do-modal-label">Phone <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="text" class="form-control form-control-sm" id="qv_phone" placeholder="e.g. +92 300 0000000">
                    </div>
                </div>
                <div id="qv_msg" class="mt-2" style="font-size:.78rem;min-height:1.2rem"></div>
            </div>
            <div class="modal-footer" style="border-top:1px solid rgba(99,102,241,.2);padding:.6rem 1rem">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm" id="saveQuickVendorBtn" style="background:#6366f1;color:#fff;border:none"><i class="bi bi-check-circle me-1"></i>Create Vendor</button>
            </div>
        </div>
    </div>
</div>

<!-- ════ MODAL: Add New Service ════ -->
<div class="modal fade" id="serviceModal" tabindex="-1" aria-labelledby="serviceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:var(--cl-surface,#1e293b);border:1px solid #0ea5e9;border-radius:10px">
            <div class="modal-header" style="border-bottom:1px solid rgba(14,165,233,.25);padding:.65rem 1rem">
                <h6 class="modal-title" id="serviceModalLabel" style="color:#38bdf8;font-size:.88rem;font-weight:700"><i class="bi bi-plus-square me-2"></i>Add New Shipping Service</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="font-size:.7rem"></button>
            </div>
            <div class="modal-body" style="padding:1rem 1.1rem">
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label do-modal-label">Carrier <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="qs_carrier" placeholder="e.g. DHL, FedEx, TCS">
                    </div>
                    <div class="col-6">
                        <label class="form-label do-modal-label">Service Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="qs_service" placeholder="e.g. Express 24h, Economy">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label do-modal-label">Pricing <span class="text-muted fw-normal">(optional)</span></label>
                    <div class="d-flex gap-3 mb-2" style="font-size:.8rem">
                        <label class="d-flex align-items-center gap-1" style="cursor:pointer">
                            <input type="radio" name="qs_price_mode" value="flat" id="qs_mode_flat" checked>
                            <span>Flat Rate</span>
                        </label>
                        <label class="d-flex align-items-center gap-1" style="cursor:pointer">
                            <input type="radio" name="qs_price_mode" value="weight" id="qs_mode_weight">
                            <span>Weight-based (auto-calc)</span>
                        </label>
                    </div>
                    <div id="qs_flat_section">
                        <div class="input-group input-group-sm"><span class="input-group-text">PKR</span><input type="number" class="form-control" id="qs_cost" placeholder="e.g. 4500" step="0.01" min="0"></div>
                    </div>
                    <div id="qs_weight_section" style="display:none">
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="input-group input-group-sm"><span class="input-group-text">Base</span><input type="number" class="form-control" id="qs_base_rate" placeholder="PKR base" step="0.01" min="0"></div>
                                <div style="font-size:.68rem;color:var(--cl-text-muted,#64748b);margin-top:2px">Base/handling fee</div>
                            </div>
                            <div class="col-6">
                                <div class="input-group input-group-sm"><input type="number" class="form-control" id="qs_rate_per_kg" placeholder="Rate per kg" step="0.01" min="0"><span class="input-group-text">/kg</span></div>
                                <div style="font-size:.68rem;color:var(--cl-text-muted,#64748b);margin-top:2px">PKR per kg charged</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label do-modal-label">Vendor <span class="text-muted fw-normal">(optional)</span></label>
                    <select class="form-select form-select-sm" id="qs_vendor_id">
                        <option value="">-- No vendor --</option>
                        <?php foreach ($vendors as $v): ?>
                        <option value="<?= (int)$v['id'] ?>"><?= esc($v['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div style="font-size:.7rem;margin-top:3px"><a href="#" class="do-inline-btn" onclick="event.preventDefault();var sm=bootstrap.Modal.getInstance(document.getElementById('serviceModal'));if(sm)sm.hide();new bootstrap.Modal(document.getElementById('vendorModal')).show()"><i class="bi bi-plus-circle me-1"></i>Create new vendor first</a></div>
                </div>
                <div id="qs_msg" class="mt-2" style="font-size:.78rem;min-height:1.2rem"></div>
            </div>
            <div class="modal-footer" style="border-top:1px solid rgba(14,165,233,.2);padding:.6rem 1rem">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm" id="saveQuickServiceBtn" style="background:#0ea5e9;color:#fff;border:none"><i class="bi bi-check-circle me-1"></i>Create Service</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ════ TIMELINE ════ -->
<?php if (!empty($timeline)):
    // Extract meta
    $tlMeta = $timeline['_meta'] ?? ['source' => 'unknown'];
    $tlSource = $tlMeta['source'] ?? 'unknown';

    // Build event lookup (skip _meta key)
    $eventMap = [];
    foreach ($timeline as $k => $ev) {
        if ($k === '_meta') continue;
        $eventMap[$ev['label']] = $ev;
    }

    // Build milestones dynamically based on source type
    if ($tlSource === 'procurement') {
        // Procurement path: SO → RFQ → PO → Warehouse Receipt → Shipped → Delivered
        $milestones = [
            ['label' => 'Sales Order',       'event' => $eventMap['Sales Order Created'] ?? null],
            ['label' => 'RFQ Created',       'event' => $eventMap['RFQ Created'] ?? null],
            ['label' => 'Purchase Order',    'event' => $eventMap['Purchase Order Created'] ?? null],
            ['label' => 'Warehouse Receipt', 'event' => $eventMap['Received at Warehouse'] ?? null],
            ['label' => 'Shipped',           'event' => $isConfirmed ? ['time' => $do['shipped_at'] ?? $do['updated_at'], 'label' => 'Shipped'] : null],
            ['label' => 'Delivered',         'event' => $isDelivered ? ['time' => $do['delivery_confirmed_at'] ?? $do['updated_at'], 'label' => 'Delivered'] : null],
        ];
    } else {
        // In-stock path: SO → Stock Available → DO Created → Shipped → Delivered
        $milestones = [
            ['label' => 'Sales Order',       'event' => $eventMap['Sales Order Created'] ?? null],
            ['label' => 'Stock Available',   'event' => $eventMap['Stock Available'] ?? null],
            ['label' => 'DO Created',        'event' => $eventMap['Delivery Order Created'] ?? null],
            ['label' => 'Shipped',           'event' => $isConfirmed ? ['time' => $do['shipped_at'] ?? $do['updated_at'], 'label' => 'Shipped'] : null],
            ['label' => 'Delivered',         'event' => $isDelivered ? ['time' => $do['delivery_confirmed_at'] ?? $do['updated_at'], 'label' => 'Delivered'] : null],
        ];
    }

    // Count completed milestones and compute progress
    $doneCount = 0;
    $lastDoneIdx = -1;
    foreach ($milestones as $idx => $m) {
        if (!empty($m['event']['time'])) { $doneCount++; $lastDoneIdx = $idx; }
    }
    $totalSteps = count($milestones);
    $progressPct = $lastDoneIdx >= 0 ? ($lastDoneIdx / max($totalSteps - 1, 1)) * 100 : 0;

    // Build duration map from original sorted timeline
    $durationMap = [];
    foreach ($timeline as $k => $ev) {
        if ($k === '_meta') continue;
        if (!empty($ev['duration_from_prev'])) {
            $durationMap[$ev['label']] = $ev['duration_from_prev'];
        }
    }

    // Calculate total elapsed time
    $firstTime = null;
    $lastTime = null;
    foreach ($timeline as $k => $ev) {
        if ($k === '_meta') continue;
        if (!empty($ev['time'])) {
            if ($firstTime === null) $firstTime = $ev['time'];
            $lastTime = $ev['time'];
        }
    }
    $totalElapsedSec = 0;
    if ($firstTime && $lastTime) {
        $totalElapsedSec = max(0, strtotime($lastTime) - strtotime($firstTime));
    }

    // Per-step durations for performance breakdown
    $stepDurations = [];
    $prevStepTime = null;
    foreach ($milestones as $idx => $m) {
        if (!empty($m['event']['time'])) {
            if ($prevStepTime !== null) {
                $stepDurations[] = [
                    'from' => $milestones[$idx - 1]['label'] ?? '',
                    'to' => $m['label'],
                    'seconds' => max(0, strtotime($m['event']['time']) - strtotime($prevStepTime)),
                ];
            }
            $prevStepTime = $m['event']['time'];
        }
    }

    // Find the slowest step
    $slowestStep = null;
    $slowestSec = 0;
    foreach ($stepDurations as $sd) {
        if ($sd['seconds'] > $slowestSec) {
            $slowestSec = $sd['seconds'];
            $slowestStep = $sd;
        }
    }

    // Helper: format seconds to readable string
    $fmtSecs = function($secs) {
        if ($secs <= 0) return '—';
        $d = intdiv($secs, 86400);
        $h = intdiv($secs % 86400, 3600);
        $m = intdiv($secs % 3600, 60);
        $parts = [];
        if ($d > 0) $parts[] = $d . 'd';
        if ($h > 0) $parts[] = $h . 'h';
        if ($m > 0 && $d === 0) $parts[] = $m . 'm';
        return implode(' ', $parts) ?: '< 1m';
    };
?>
<div class="do-section">
    <div class="do-section-head"><i class="bi bi-diagram-3"></i> Fulfillment Timeline</div>

    <!-- Source type indicator -->
    <div style="padding:0 1.2rem .5rem;font-size:.75rem;">
        <?php if ($tlSource === 'procurement'): ?>
            <span style="background:rgba(99,102,241,.15);color:#818cf8;padding:3px 10px;border-radius:10px;font-weight:600;">
                <i class="bi bi-cart3 me-1"></i>Procurement Path
            </span>
        <?php else: ?>
            <span style="background:rgba(16,185,129,.15);color:#34d399;padding:3px 10px;border-radius:10px;font-weight:600;">
                <i class="bi bi-box-seam me-1"></i>Already in Stock
            </span>
            <?php
                $stockEv = $eventMap['Stock Available'] ?? null;
                $stockDetail = $stockEv['detail'] ?? '';
            ?>
            <?php if ($stockDetail): ?>
                <span style="color:#94a3b8;margin-left:6px;font-size:.72rem;"><?= esc($stockDetail) ?></span>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="tl-wrap">
        <div class="tl-heading"><i class="bi bi-clock-history"></i> Progress</div>
        <div class="tl-bar">
            <div class="tl-progress-fill" style="width:<?= $progressPct ?>%"></div>
            <?php foreach ($milestones as $idx => $m):
                $has = !empty($m['event']['time']);
                $isNext = !$has && ($idx === 0 || !empty($milestones[$idx-1]['event']['time']));
                $evLabel = $m['event']['label'] ?? $m['label'];
                $dur = $durationMap[$evLabel] ?? null;
            ?>
            <div class="tl-step">
                <?php if ($dur && $idx > 0): ?><div class="tl-duration"><?= esc($dur) ?></div><?php endif; ?>
                <div class="tl-dot <?= $has ? 'done' : ($isNext ? 'current' : '') ?>"></div>
                <div class="tl-step-info">
                    <div class="tl-step-label"><?= esc($m['label']) ?></div>
                    <?php if ($has): ?>
                        <div class="tl-step-date"><?= date('d M Y', strtotime($m['event']['time'])) ?></div>
                        <div class="tl-step-time"><?= date('H:i', strtotime($m['event']['time'])) ?></div>
                    <?php endif; ?>
                    <span class="tl-chip <?= $has ? 'done' : 'pend' ?>"><?= $has ? 'Done' : 'Pending' ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Performance Metrics -->
    <div style="padding:1rem 1.2rem .6rem;border-top:1px solid rgba(255,255,255,.06);">
        <div style="font-size:.78rem;font-weight:700;color:var(--cl-text-primary,#f1f5f9);margin-bottom:.7rem;">
            <i class="bi bi-speedometer2 me-1"></i> Order Performance
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:.8rem;">
            <!-- Total Elapsed -->
            <div style="flex:1;min-width:140px;background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.2);border-radius:8px;padding:.6rem .8rem;">
                <div style="font-size:.68rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em;margin-bottom:2px;">Total Time</div>
                <div style="font-size:1.15rem;font-weight:700;color:#818cf8;"><?= $fmtSecs($totalElapsedSec) ?></div>
                <?php if ($firstTime && $lastTime): ?>
                    <div style="font-size:.65rem;color:#64748b;margin-top:2px;">
                        <?= date('d M H:i', strtotime($firstTime)) ?> → <?= date('d M H:i', strtotime($lastTime)) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Steps Completed -->
            <div style="flex:1;min-width:140px;background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.2);border-radius:8px;padding:.6rem .8rem;">
                <div style="font-size:.68rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em;margin-bottom:2px;">Progress</div>
                <div style="font-size:1.15rem;font-weight:700;color:#34d399;"><?= $doneCount ?> / <?= $totalSteps ?></div>
                <div style="font-size:.65rem;color:#64748b;margin-top:2px;">
                    <?php if ($doneCount === $totalSteps): ?>
                        All steps completed
                    <?php else: ?>
                        <?= $totalSteps - $doneCount ?> step<?= ($totalSteps - $doneCount) > 1 ? 's' : '' ?> remaining
                    <?php endif; ?>
                </div>
            </div>

            <!-- Bottleneck -->
            <?php if ($slowestStep): ?>
            <div style="flex:1;min-width:140px;background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.2);border-radius:8px;padding:.6rem .8rem;">
                <div style="font-size:.68rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em;margin-bottom:2px;">Slowest Step</div>
                <div style="font-size:1.15rem;font-weight:700;color:#fbbf24;"><?= $fmtSecs($slowestSec) ?></div>
                <div style="font-size:.65rem;color:#64748b;margin-top:2px;">
                    <?= esc($slowestStep['from']) ?> → <?= esc($slowestStep['to']) ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Step-by-step breakdown -->
        <?php if (!empty($stepDurations)): ?>
        <div style="margin-top:.7rem;">
            <div style="font-size:.7rem;color:#94a3b8;margin-bottom:.4rem;">Step Breakdown</div>
            <?php foreach ($stepDurations as $sd):
                $pct = $totalElapsedSec > 0 ? round(($sd['seconds'] / $totalElapsedSec) * 100) : 0;
                $isSlowest = ($slowestStep && $sd['from'] === $slowestStep['from'] && $sd['to'] === $slowestStep['to']);
                $barColor = $isSlowest ? '#fbbf24' : '#818cf8';
            ?>
            <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:4px;font-size:.72rem;">
                <div style="width:140px;color:#cbd5e1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= esc($sd['from']) ?> → <?= esc($sd['to']) ?>">
                    <?= esc($sd['from']) ?> → <?= esc($sd['to']) ?>
                </div>
                <div style="flex:1;height:6px;background:rgba(255,255,255,.06);border-radius:3px;overflow:hidden;">
                    <div style="width:<?= $pct ?>%;height:100%;background:<?= $barColor ?>;border-radius:3px;transition:width .3s;"></div>
                </div>
                <div style="width:50px;text-align:right;color:<?= $isSlowest ? '#fbbf24' : '#94a3b8' ?>;font-weight:<?= $isSlowest ? '700' : '400' ?>;">
                    <?= $fmtSecs($sd['seconds']) ?>
                </div>
                <div style="width:35px;text-align:right;color:#64748b;"><?= $pct ?>%</div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- ════ JAVASCRIPT ════ -->
<script>
(function(){
'use strict';
const CSRF='<?= csrf_token() ?>',HASH='<?= csrf_hash() ?>',DO_ID=<?= $doId ?>;
const ALL_SVC=<?= json_encode(array_map(function($s){return['id'=>(int)$s['id'],'carrier'=>$s['carrier'],'service_name'=>$s['service_name'],'cost_pkr'=>(float)($s['cost_pkr']??0),'base_rate_pkr'=>(float)($s['base_rate_pkr']??0),'rate_per_kg_pkr'=>(float)($s['rate_per_kg_pkr']??0),'vendor_id'=>(int)($s['vendor_id']??0)];},$shippingServices)) ?>;

function post(url,body){body[CSRF]=HASH;return fetch(url,{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(body)}).then(r=>r.json());}

<?php if ($isDraft): ?>
/* ── Qty save ── */
document.querySelectorAll('.qty-to-ship').forEach(inp=>{
    inp.addEventListener('change',async function(){
        const lid=this.dataset.lineId,q=parseFloat(this.value)||0,mx=parseFloat(this.dataset.readyQty)||0;
        const msg=document.querySelector('.do-qty-msg[data-line-id="'+lid+'"]');
        if(q<0){this.value='0.00';return;}
        if(q>mx){this.value=mx.toFixed(2);if(msg)msg.innerHTML='<span class="text-warning">Max '+mx.toFixed(2)+'</span>';return;}
        try{const d=await post('<?= site_url("delivery-orders/update-qty") ?>/'+lid,{qty_to_ship:q});
            if(msg){msg.innerHTML=d.success?'<span class="text-success">Saved</span>':'<span class="text-danger">'+(d.message||'Error')+'</span>';setTimeout(()=>{msg.innerHTML='';},2000);}
        }catch(e){if(msg)msg.innerHTML='<span class="text-danger">Failed</span>';}
    });
});

/* ── Ship panel toggle ── */
const shipWrap=document.getElementById('shipmentPanelWrap');
document.getElementById('openShipBtn')?.addEventListener('click',()=>{
    shipWrap.style.display='block';
    shipWrap.scrollIntoView({behavior:'smooth',block:'nearest'});
    // Init Select2 on country once panel is visible
    if(typeof $!=='undefined'&&!$('#destination_country').data('select2')){
        $('#destination_country').select2({
            theme:'default',
            placeholder:'Search country…',
            allowClear:true,
            width:'100%',
            dropdownAutoWidth:true
        });
    }
});
document.getElementById('cancelShipBtn')?.addEventListener('click',()=>{shipWrap.style.display='none';});

/* ── Auto-confirm PO / Auto-create Bill checkbox dependency ── */
(function(){
    const poCb = document.getElementById('auto_confirm_po');
    const billCb = document.getElementById('auto_create_bill');
    if (poCb && billCb) {
        poCb.addEventListener('change', function(){
            if (!this.checked) {
                billCb.checked = false;
                billCb.disabled = true;
            } else {
                billCb.disabled = false;
            }
        });
    }
})();

/* ── Confirm form: multi-image preview ── */
document.getElementById('parcel_images_confirm')?.addEventListener('change',function(){
    const wrap=document.getElementById('confirmParcelPreviews');
    if(!wrap)return;
    wrap.innerHTML='';
    Array.from(this.files).forEach(f=>{
        if(!f.type.startsWith('image/'))return;
        const r=new FileReader();
        r.onload=e=>{
            const d=document.createElement('div');
            d.style.cssText='width:56px;height:56px;border-radius:6px;overflow:hidden;border:1px solid var(--cl-border,#334155);flex-shrink:0';
            d.innerHTML='<img src="'+e.target.result+'" style="width:100%;height:100%;object-fit:cover">';
            wrap.appendChild(d);
        };
        r.readAsDataURL(f);
    });
});
document.getElementById('cancelDoBtn')?.addEventListener('click',()=>{if(confirm('Cancel this delivery order?'))window.location.href='<?= site_url("delivery-orders") ?>';});

/* ── Vendor / Service filtering ── */
const vSel=document.getElementById('shipping_vendor_id'),sSel=document.getElementById('shipping_service_id'),cInp=document.getElementById('shipping_cost_pkr');
function rebuildSvc(vid){
    const list=vid?ALL_SVC.filter(s=>s.vendor_id===vid||s.vendor_id===0):ALL_SVC;
    sSel.innerHTML='<option value="">-- Select Service --</option>';
    list.forEach(s=>{const o=document.createElement('option');o.value=s.id;o.dataset.cost=s.cost_pkr;o.dataset.baseRate=s.base_rate_pkr||0;o.dataset.ratePerKg=s.rate_per_kg_pkr||0;o.textContent=s.carrier+' - '+s.service_name;sSel.appendChild(o);});
}
function calcAndSetCost(){
    const o=sSel?.options[sSel.selectedIndex];
    if(!o?.value) return;
    const perKg=parseFloat(o.dataset.ratePerKg||0);
    if(perKg>0){
        const base=parseFloat(o.dataset.baseRate||0);
        const wt=parseFloat(document.getElementById('final_weight_kg')?.value)||0;
        cInp.value=(base+(wt*perKg)).toFixed(2);
    } else if(o.dataset.cost){
        cInp.value=parseFloat(o.dataset.cost).toFixed(2);
    }
}
vSel?.addEventListener('change',function(){rebuildSvc(parseInt(this.value)||0);});
sSel?.addEventListener('change',calcAndSetCost);
document.getElementById('final_weight_kg')?.addEventListener('input',function(){
    const o=sSel?.options[sSel.selectedIndex];
    if(!o?.value) return;
    const perKg=parseFloat(o.dataset.ratePerKg||0);
    if(perKg>0){
        const base=parseFloat(o.dataset.baseRate||0);
        const wt=parseFloat(this.value)||0;
        cInp.value=(base+(wt*perKg)).toFixed(2);
    }
});

/* ── Add Service: pre-fill carrier from vendor name ── */
document.getElementById('addServiceInlineBtn')?.addEventListener('click',()=>{
    const vn=vSel?.options[vSel.selectedIndex]?.text||'';
    const formVid=vSel?.value||'';
    if(vn&&!vn.startsWith('--'))document.getElementById('qs_carrier').value=vn;
    // Pre-select the form's current vendor in the modal dropdown
    const qsVend=document.getElementById('qs_vendor_id');
    if(qsVend&&formVid)qsVend.value=formVid;
    new bootstrap.Modal(document.getElementById('serviceModal')).show();
});

/* ── Quick Vendor modal ── */
document.getElementById('saveQuickVendorBtn')?.addEventListener('click',async function(){
    const n=document.getElementById('qv_name').value.trim(),c=document.getElementById('qv_contact').value.trim(),p=document.getElementById('qv_phone').value.trim(),msg=document.getElementById('qv_msg');
    if(!n){msg.innerHTML='<span class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>Vendor name is required</span>';return;}
    this.disabled=true;this.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Saving…';
    try{const d=await post('<?= site_url("delivery-orders/quick-vendor") ?>',{name:n,contact_person:c,phone:p});
        if(d.success){
            const o=document.createElement('option');o.value=d.id;o.textContent=d.name;o.selected=true;vSel.appendChild(o);
            rebuildSvc(d.id);
            bootstrap.Modal.getInstance(document.getElementById('vendorModal')).hide();
            document.getElementById('qv_name').value='';document.getElementById('qv_contact').value='';document.getElementById('qv_phone').value='';
            msg.innerHTML='';
        } else msg.innerHTML='<span class="text-danger">'+(d.message||'Failed to create vendor')+'</span>';
    }catch(e){msg.innerHTML='<span class="text-danger">Connection error</span>';}
    finally{this.disabled=false;this.innerHTML='<i class="bi bi-check-circle me-1"></i>Create Vendor';}
});
// Clear messages when modal reopened
document.getElementById('vendorModal')?.addEventListener('shown.bs.modal',()=>{document.getElementById('qv_msg').innerHTML='';document.getElementById('qv_name').focus();});

/* ── Quick Service modal ── */
document.getElementById('saveQuickServiceBtn')?.addEventListener('click',async function(){
    const cr=document.getElementById('qs_carrier').value.trim(),sn=document.getElementById('qs_service').value.trim(),
          mode=document.querySelector('input[name="qs_price_mode"]:checked')?.value||'flat',
          cp=mode==='flat'?(parseFloat(document.getElementById('qs_cost').value)||0):0,
          br=mode==='weight'?(parseFloat(document.getElementById('qs_base_rate').value)||0):0,
          rpk=mode==='weight'?(parseFloat(document.getElementById('qs_rate_per_kg').value)||0):0,
          vid=parseInt(document.getElementById('qs_vendor_id')?.value||'0')||0,
          msg=document.getElementById('qs_msg');
    if(!cr||!sn){msg.innerHTML='<span class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>Carrier and service name are required</span>';return;}
    this.disabled=true;this.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Saving…';
    try{const d=await post('<?= site_url("delivery-orders/quick-service") ?>',{carrier:cr,service_name:sn,cost_pkr:cp,base_rate_pkr:br,rate_per_kg_pkr:rpk,vendor_id:vid});
        if(d.success){
            const o=document.createElement('option');o.value=d.id;
            o.dataset.cost=d.cost_pkr||0;o.dataset.baseRate=d.base_rate_pkr||0;o.dataset.ratePerKg=d.rate_per_kg_pkr||0;
            o.textContent=d.carrier+' - '+d.service_name;o.selected=true;sSel.appendChild(o);
            ALL_SVC.push({id:d.id,carrier:d.carrier,service_name:d.service_name,cost_pkr:d.cost_pkr||0,base_rate_pkr:d.base_rate_pkr||0,rate_per_kg_pkr:d.rate_per_kg_pkr||0,vendor_id:d.vendor_id||0});
            calcAndSetCost();
            bootstrap.Modal.getInstance(document.getElementById('serviceModal')).hide();
            document.getElementById('qs_carrier').value='';document.getElementById('qs_service').value='';
            document.getElementById('qs_cost').value='';document.getElementById('qs_base_rate').value='';document.getElementById('qs_rate_per_kg').value='';
            msg.innerHTML='';
        } else msg.innerHTML='<span class="text-danger">'+(d.message||'Failed to create service')+'</span>';
    }catch(e){msg.innerHTML='<span class="text-danger">Connection error</span>';}
    finally{this.disabled=false;this.innerHTML='<i class="bi bi-check-circle me-1"></i>Create Service';}
});
document.getElementById('serviceModal')?.addEventListener('shown.bs.modal',()=>{
    document.getElementById('qs_msg').innerHTML='';
    // Reset to flat rate mode
    const flatRadio = document.getElementById('qs_mode_flat');
    if(flatRadio){ flatRadio.checked=true; document.getElementById('qs_flat_section').style.display=''; document.getElementById('qs_weight_section').style.display='none'; }
    // Wire radio toggles (safe to re-attach since we use a fresh modal instance)
    document.querySelectorAll('input[name="qs_price_mode"]').forEach(r=>r.addEventListener('change',function(){
        document.getElementById('qs_flat_section').style.display=this.value==='flat'?'':'none';
        document.getElementById('qs_weight_section').style.display=this.value==='weight'?'':'none';
    }));
    if(typeof $!=='undefined'&&$.fn.select2&&!$('#qs_vendor_id').data('select2')){
        $('#qs_vendor_id').select2({placeholder:'Select vendor (optional)…',allowClear:true,dropdownParent:$('#serviceModal'),width:'100%'});
    }
    document.getElementById('qs_carrier').focus();
});
<?php endif; ?>

/* ── Manual Create Shipping PO/Bill ── */
document.getElementById('createShippingPoBtn')?.addEventListener('click',async function(){
    if(!confirm('Create a Shipping PO and Vendor Bill for this delivery order?')) return;
    this.disabled=true;this.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Creating…';
    const res=document.getElementById('createShippingPoResult');
    try{
        const d=await post('<?= site_url("delivery-orders/create-shipping-po/") ?>'+DO_ID,{});
        if(d.success){location.reload();}
        else{if(res)res.innerHTML='<span class="text-danger">'+(d.message||'Failed')+'</span>';this.disabled=false;this.innerHTML='<i class="bi bi-plus-circle me-1"></i>Create Shipping PO & Bill';}
    }catch(e){if(res)res.innerHTML='<span class="text-danger">Error: '+e.message+'</span>';this.disabled=false;this.innerHTML='<i class="bi bi-plus-circle me-1"></i>Create Shipping PO & Bill';}
});

/* ── Tracking (confirmed state) ── */
const trkBtns=document.querySelectorAll('.js-add-tracking-btn'),trkWrap=document.getElementById('trackingFormWrap');
trkBtns.forEach(btn=>btn.addEventListener('click',()=>{trkWrap?.classList.toggle('open');if(trkWrap?.classList.contains('open'))trkWrap.scrollIntoView({behavior:'smooth',block:'nearest'});}));
document.getElementById('cancelTrackingBtn')?.addEventListener('click',()=>{trkWrap?.classList.remove('open');});

/* Tracking doc file preview */
document.getElementById('trackingDocInput')?.addEventListener('change',function(){
    const wrap=document.getElementById('trackingDocPreviews');
    if(!wrap)return;
    wrap.innerHTML='';
    Array.from(this.files).forEach(f=>{
        const d=document.createElement('div');
        d.style.cssText='display:inline-flex;align-items:center;gap:4px;background:rgba(255,255,255,.06);border:1px solid var(--cl-border,#334155);border-radius:6px;padding:3px 8px;font-size:.72rem;color:#cbd5e1;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap';
        const ico=f.type==='application/pdf'?'bi-file-earmark-pdf':'bi-image';
        d.innerHTML='<i class="bi '+ico+'" style="font-size:.85rem;flex-shrink:0"></i><span style="overflow:hidden;text-overflow:ellipsis">'+f.name+'</span>';
        wrap.appendChild(d);
    });
});

document.getElementById('saveTrackingBtn')?.addEventListener('click',async function(){
    const t=document.getElementById('trackingNumberInput')?.value.trim()||'',
          u=document.getElementById('trackingUrlInput')?.value.trim()||'',
          msg=document.getElementById('trackingDocUploadMsg');
    this.disabled=true;this.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Saving…';
    try{
        // Step 1: save tracking number + URL
        const d=await post('<?= site_url("delivery-orders/add-tracking/") ?>'+DO_ID,{tracking_number:t,tracking_url:u});
        if(!d.success){if(msg)msg.innerHTML='<span class="text-danger">'+(d.message||'Failed to save tracking')+'</span>';this.disabled=false;this.innerHTML='Save';return;}

        // Step 2: upload docs if any
        const fileInput=document.getElementById('trackingDocInput');
        if(fileInput?.files?.length){
            const fd=new FormData();
            Array.from(fileInput.files).forEach(f=>fd.append('tracking_docs[]',f));
            fd.append('<?= csrf_token() ?>','<?= csrf_hash() ?>');
            const resp=await fetch('<?= site_url("delivery-orders/upload-tracking-doc/") ?>'+DO_ID,{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}});
            const r=await resp.json();
            if(!r.success && msg){msg.innerHTML='<span class="text-warning">Tracking saved but file upload failed: '+(r.message||'unknown error')+'</span>';setTimeout(()=>location.reload(),2500);return;}
        }
        location.reload();
    }catch(e){if(msg)msg.innerHTML='<span class="text-danger">Error: '+e.message+'</span>';this.disabled=false;this.innerHTML='Save';}
});

/* Delete tracking doc hover + delete */
document.querySelectorAll('.trk-doc-wrap').forEach(wrap=>{
    wrap.addEventListener('mouseenter',()=>{const b=wrap.querySelector('.trk-doc-del');if(b)b.style.opacity='1';});
    wrap.addEventListener('mouseleave',()=>{const b=wrap.querySelector('.trk-doc-del');if(b)b.style.opacity='0';});
});
document.querySelectorAll('.trk-doc-del').forEach(btn=>{
    btn.addEventListener('click',async function(e){
        e.stopPropagation();
        if(!confirm('Delete this tracking document?'))return;
        const docId=this.dataset.docId;
        const fd=new FormData();fd.append('<?= csrf_token() ?>','<?= csrf_hash() ?>');
        try{
            const resp=await fetch('<?= site_url("delivery-orders/delete-tracking-doc/") ?>'+docId,{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}});
            const d=await resp.json();
            if(d.success){
                this.closest('.trk-doc-wrap')?.remove();
                const grid=document.getElementById('trackingDocGrid');
                if(grid&&!grid.querySelector('.trk-doc-wrap'))grid.innerHTML='<span style="font-size:.78rem;color:var(--cl-text-muted,#64748b)"><i class="bi bi-file-earmark me-1"></i>No tracking documents uploaded yet</span>';
            }else alert(d.message||'Delete failed');
        }catch(e){alert('Connection error');}
    });
});

/* Tracking doc image lightbox */
(function(){
    const imgs=document.querySelectorAll('img[data-lightbox-trk-src]');
    imgs.forEach(img=>{
        img.addEventListener('click',function(){
            const src=this.dataset.lightboxTrkSrc;
            const ol=document.createElement('div');
            ol.style.cssText='position:fixed;top:0;left:0;right:0;bottom:0;z-index:2147483647;background:rgba(0,0,0,.92);display:flex;align-items:center;justify-content:center;cursor:zoom-out';
            const im=document.createElement('img');
            im.src=src;im.style.cssText='max-width:90vw;max-height:90vh;border-radius:6px;object-fit:contain';
            ol.appendChild(im);
            ol.addEventListener('click',()=>ol.remove());
            document.body.appendChild(ol);
        });
    });
})();

/* ── Delivery Status Update ── */
const dsForm=document.getElementById('deliveryStatusForm');
document.getElementById('updateDeliveryBtn')?.addEventListener('click',()=>{
    if(dsForm){dsForm.style.display=dsForm.style.display==='none'?'block':'none';if(dsForm.style.display==='block')dsForm.scrollIntoView({behavior:'smooth',block:'nearest'});}
});
document.getElementById('cancelDeliveryStatusBtn')?.addEventListener('click',()=>{if(dsForm)dsForm.style.display='none';});
document.getElementById('saveDeliveryStatusBtn')?.addEventListener('click',async function(){
    const st=document.getElementById('deliveryStatusSelect')?.value||'',
          nt=document.getElementById('deliveryNotesInput')?.value.trim()||'',
          msg=document.getElementById('deliveryStatusMsg');
    if(!st){if(msg)msg.innerHTML='<span class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>Please select a status</span>';return;}
    this.disabled=true;this.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Saving…';
    try{const d=await post('<?= site_url("delivery-orders/update-delivery-status/") ?>'+DO_ID,{delivery_status:st,delivery_notes:nt});
        if(d.success)location.reload();else{if(msg)msg.innerHTML='<span class="text-danger">'+(d.message||'Failed')+'</span>';this.disabled=false;this.innerHTML='<i class="bi bi-check-circle me-1"></i>Save Status';}
    }catch(e){if(msg)msg.innerHTML='<span class="text-danger">Connection error</span>';this.disabled=false;this.innerHTML='<i class="bi bi-check-circle me-1"></i>Save Status';}
});

/* ── Parcel Image Upload (confirmed state) ── */
const parcelToggle=document.getElementById('parcelUploadToggleBtn'),parcelWrap=document.getElementById('parcelUploadFormWrap');
parcelToggle?.addEventListener('click',()=>{parcelWrap?.classList.toggle('open');if(parcelWrap?.classList.contains('open'))parcelWrap.scrollIntoView({behavior:'smooth',block:'nearest'});});
document.getElementById('cancelParcelImageBtn')?.addEventListener('click',()=>{parcelWrap?.classList.remove('open');});

/* Multi-file preview */
document.getElementById('parcelImageFileInput')?.addEventListener('change',function(){
    const wrap=document.getElementById('parcelInlinePreviews');
    if(!wrap)return;
    wrap.innerHTML='';
    Array.from(this.files).forEach(f=>{
        if(!f.type.startsWith('image/'))return;
        const r=new FileReader();
        r.onload=e=>{
            const d=document.createElement('div');
            d.style.cssText='width:56px;height:56px;border-radius:6px;overflow:hidden;border:1px solid var(--cl-border,#334155);flex-shrink:0';
            d.innerHTML='<img src="'+e.target.result+'" style="width:100%;height:100%;object-fit:cover">';
            wrap.appendChild(d);
        };
        r.readAsDataURL(f);
    });
});

document.getElementById('saveParcelImageBtn')?.addEventListener('click',async function(){
    const fileInput=document.getElementById('parcelImageFileInput'),msg=document.getElementById('parcelUploadMsg');
    if(!fileInput?.files?.length){if(msg)msg.innerHTML='<span class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>Please select at least one image</span>';return;}
    const fd=new FormData();
    Array.from(fileInput.files).forEach(f=>fd.append('parcel_images[]',f));
    fd.append('<?= csrf_token() ?>','<?= csrf_hash() ?>');
    this.disabled=true;this.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Uploading…';
    try{
        const resp=await fetch('<?= site_url("delivery-orders/upload-parcel-image/") ?>'+DO_ID,{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}});
        const d=await resp.json();
        if(d.success){location.reload();}
        else{if(msg)msg.innerHTML='<span class="text-danger">'+(d.message||'Upload failed')+'</span>';this.disabled=false;this.innerHTML='<i class="bi bi-upload me-1"></i>Upload';}
    }catch(e){if(msg)msg.innerHTML='<span class="text-danger">Connection error</span>';this.disabled=false;this.innerHTML='<i class="bi bi-upload me-1"></i>Upload';}
});

/* Delete thumbnail button visibility */
document.querySelectorAll('.parcel-thumb-wrap').forEach(wrap=>{
    wrap.addEventListener('mouseenter',()=>{const b=wrap.querySelector('.parcel-thumb-del');if(b)b.style.opacity='1';});
    wrap.addEventListener('mouseleave',()=>{const b=wrap.querySelector('.parcel-thumb-del');if(b)b.style.opacity='0';});
});

/* Delete parcel image */
document.querySelectorAll('.parcel-thumb-del').forEach(btn=>{
    btn.addEventListener('click',async function(e){
        e.stopPropagation();
        if(!confirm('Delete this parcel photo?'))return;
        const imgId=this.dataset.imgId;
        const fd=new FormData();fd.append('<?= csrf_token() ?>','<?= csrf_hash() ?>');
        try{
            const resp=await fetch('<?= site_url("delivery-orders/delete-parcel-image/") ?>'+imgId,{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}});
            const d=await resp.json();
            if(d.success){this.closest('.parcel-thumb-wrap')?.remove();
                const grid=document.getElementById('parcelImageGrid');
                if(grid&&!grid.querySelector('.parcel-thumb-wrap'))grid.innerHTML='<span style="font-size:.78rem;color:var(--cl-text-muted,#64748b)"><i class="bi bi-image me-1"></i>No parcel photos uploaded yet</span>';
                if(window._lbRebuild)window._lbRebuild();
            }else alert(d.message||'Delete failed');
        }catch(e){alert('Connection error');}
    });
});

/* ── Estimated Days Update ── */
document.getElementById('saveEstDaysBtn')?.addEventListener('click',async function(){
    const days=parseInt(document.getElementById('estDaysInput')?.value)||0;
    if(days<1||days>365){alert('Please enter days between 1 and 365');return;}
    this.disabled=true;this.textContent='Saving…';
    try{const d=await post('<?= site_url("delivery-orders/update-estimated-days/") ?>'+DO_ID,{estimated_delivery_days:days});
        if(d.success)location.reload();else{alert(d.message||'Failed');this.disabled=false;this.textContent='Save';}
    }catch(e){alert('Error: '+e.message);this.disabled=false;this.textContent='Save';}
});
})();
</script>

<script>
/* ── Parcel Lightbox — injected into <body> to avoid stacking context issues ── */
(function(){
    // Build overlay HTML and append directly to body
    var lb=document.createElement('div');
    lb.id='parcelLightbox';
    lb.style.cssText='display:none;position:fixed;top:0;left:0;right:0;bottom:0;z-index:2147483647;background:rgba(0,0,0,.9);align-items:center;justify-content:center;flex-direction:column';
    lb.innerHTML=
        '<button id="lbClose" style="position:absolute;top:18px;right:22px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);border-radius:50%;color:#fff;font-size:1.2rem;width:36px;height:36px;cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:10;">&#x2715;</button>'+
        '<button id="lbPrev" style="position:absolute;left:18px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);border-radius:50%;color:#fff;font-size:1.6rem;width:48px;height:48px;cursor:pointer;display:flex;align-items:center;justify-content:center;">&#8249;</button>'+
        '<button id="lbNext" style="position:absolute;right:18px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);border-radius:50%;color:#fff;font-size:1.6rem;width:48px;height:48px;cursor:pointer;display:flex;align-items:center;justify-content:center;">&#8250;</button>'+
        '<img id="lbImg" src="" alt="" style="max-width:88vw;max-height:84vh;object-fit:contain;border-radius:6px;box-shadow:0 8px 48px rgba(0,0,0,.8);">'+
        '<div id="lbCounter" style="color:rgba(255,255,255,.6);font-size:.82rem;margin-top:12px;"></div>';
    document.body.appendChild(lb);

    var lbImg=document.getElementById('lbImg');
    var lbCounter=document.getElementById('lbCounter');
    var lbPrev=document.getElementById('lbPrev');
    var lbNext=document.getElementById('lbNext');
    var lbClose=document.getElementById('lbClose');
    var srcs=[];
    var idx=0;

    function buildSrcs(){
        srcs=[];
        document.querySelectorAll('.parcel-thumb').forEach(function(el){
            srcs.push(el.getAttribute('data-lightbox-src'));
        });
    }

    function show(i){
        buildSrcs();
        if(!srcs.length)return;
        idx=((i%srcs.length)+srcs.length)%srcs.length;
        lbImg.src=srcs[idx];
        lbCounter.textContent=(idx+1)+' / '+srcs.length;
        lbPrev.style.display=srcs.length>1?'flex':'none';
        lbNext.style.display=srcs.length>1?'flex':'none';
        lb.style.display='flex';
        document.body.style.overflow='hidden';
    }

    function hide(){
        lb.style.display='none';
        document.body.style.overflow='';
    }

    lbClose.addEventListener('click',hide);
    lbPrev.addEventListener('click',function(){show(idx-1);});
    lbNext.addEventListener('click',function(){show(idx+1);});
    lb.addEventListener('click',function(e){if(e.target===lb)hide();});

    document.addEventListener('keydown',function(e){
        if(lb.style.display==='none')return;
        if(e.key==='Escape')hide();
        else if(e.key==='ArrowLeft')show(idx-1);
        else if(e.key==='ArrowRight')show(idx+1);
    });

    document.querySelectorAll('.parcel-thumb').forEach(function(img){
        img.style.cursor='zoom-in';
        img.addEventListener('click',function(e){
            e.stopPropagation();
            show(parseInt(this.getAttribute('data-lightbox-index'))||0);
        });
    });

    window._lbRebuild=buildSrcs;
})();
</script>
<?= $this->endSection() ?>
