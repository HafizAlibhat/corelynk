<?php /** @var array $transfer */ ?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Transfer <?= esc($transfer['transfer_number']) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
.trshow-page { max-width:860px; margin:0 auto; }
.badge-trf { background:rgba(99,102,241,.18); color:#a5b4fc; border:1px solid rgba(99,102,241,.3); font-size:.85rem; letter-spacing:.04em; font-weight:700; padding:.35em .7em; }
.loc-card { border-radius:10px; padding:1rem 1.2rem; flex:1; }
.loc-from { background:rgba(248,113,113,.08); border:1px solid rgba(248,113,113,.2); }
.loc-to   { background:rgba(52,211,153,.08);  border:1px solid rgba(52,211,153,.2); }
.loc-title { font-size:.68rem; text-transform:uppercase; letter-spacing:.08em; font-weight:700; margin-bottom:.4rem; }
.loc-from .loc-title { color:#f87171; }
.loc-to   .loc-title { color:#34d399; }
.loc-name { font-size:1.05rem; font-weight:700; color:#e2e8f0; }
.loc-wh   { font-size:.78rem; color:#94a3b8; margin-top:.15rem; }
.qty-hero { font-size:2rem; font-weight:800; color:#34d399; }
.reason-box { background:rgba(99,102,241,.07); border-left:3px solid #6366f1; border-radius:0 8px 8px 0; padding:.75rem 1rem; font-size:.9rem; color:#cbd5e1; }
.detail-row { display:flex; gap:.4rem; padding:.4rem 0; border-bottom:1px solid rgba(255,255,255,.05); font-size:.85rem; }
.detail-row:last-child { border-bottom:none; }
.detail-label { color:#64748b; min-width:130px; }
.detail-value { color:#e2e8f0; }
.notes-box { background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.07); border-radius:8px; padding:.75rem 1rem; font-size:.85rem; color:#94a3b8; white-space:pre-wrap; }
.mov-badge-out { background:rgba(248,113,113,.12); color:#f87171; border:1px solid rgba(248,113,113,.2); font-size:.72rem; padding:.2em .55em; border-radius:5px; }
.mov-badge-in  { background:rgba(52,211,153,.12);  color:#34d399; border:1px solid rgba(52,211,153,.2);  font-size:.72rem; padding:.2em .55em; border-radius:5px; }
</style>

<div class="trshow-page">

    <!-- Breadcrumb / Back -->
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="<?= base_url('/inventory/transfers') ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h5 class="fw-bold mb-0">
                <i class="bi bi-arrow-left-right me-2" style="color:#818cf8"></i>
                Internal Transfer
                <span class="badge badge-trf ms-2"><?= esc($transfer['transfer_number']) ?></span>
            </h5>
            <div class="text-muted small"><?= date('d-m-Y H:i', strtotime($transfer['created_at'])) ?></div>
        </div>
    </div>

    <!-- Product Banner -->
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body py-3 px-4">
            <div class="row align-items-center">
                <div class="col">
                    <div class="text-muted small mb-1">Product</div>
                    <div class="fw-bold fs-5">
                        <?= esc($transfer['product_name']) ?>
                        <?php if ($transfer['product_code']): ?>
                            <span class="badge bg-secondary ms-1 fw-normal" style="font-size:.7rem;"><?= esc($transfer['product_code']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($transfer['variant_name']): ?>
                        <small class="text-muted"><?= esc($transfer['variant_name']) ?> &middot; <?= esc($transfer['art_number']) ?></small>
                    <?php endif; ?>
                </div>
                <div class="col-auto text-end">
                    <div class="text-muted small mb-1">Quantity Moved</div>
                    <div class="qty-hero"><?= number_format((float)$transfer['quantity'], 2) ?></div>
                    <div class="text-muted small">units</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Route: From → To -->
    <div class="d-flex align-items-center gap-3 mb-3">
        <div class="loc-card loc-from">
            <div class="loc-title"><i class="bi bi-box-arrow-right me-1"></i>Moved From</div>
            <div class="loc-name"><?= esc($transfer['from_location']) ?></div>
            <div class="loc-wh"><?= esc($transfer['from_warehouse']) ?></div>
        </div>
        <div class="text-center px-2" style="font-size:1.6rem; color:#6366f1; flex-shrink:0;">
            <i class="bi bi-arrow-right-circle-fill"></i>
        </div>
        <div class="loc-card loc-to">
            <div class="loc-title"><i class="bi bi-box-arrow-in-right me-1"></i>Moved To</div>
            <div class="loc-name"><?= esc($transfer['to_location']) ?></div>
            <div class="loc-wh"><?= esc($transfer['to_warehouse']) ?></div>
        </div>
    </div>

    <!-- Reason -->
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body px-4 py-3">
            <div class="text-muted small mb-2"><i class="bi bi-chat-square-quote me-1"></i>Reason for Transfer</div>
            <div class="reason-box"><?= nl2br(esc($transfer['reason'])) ?></div>

            <?php if (!empty($transfer['notes']) && trim($transfer['notes']) !== ''): ?>
                <div class="text-muted small mt-3 mb-1"><i class="bi bi-sticky me-1"></i>Additional Notes</div>
                <div class="notes-box"><?= nl2br(esc($transfer['notes'])) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Details Table -->
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-transparent border-bottom py-2 px-4">
            <small class="text-uppercase fw-bold text-muted" style="letter-spacing:.05em;">Transfer Details</small>
        </div>
        <div class="card-body px-4 py-2">
            <div class="detail-row">
                <span class="detail-label">Transfer #</span>
                <span class="detail-value fw-bold"><?= esc($transfer['transfer_number']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Date &amp; Time</span>
                <span class="detail-value"><?= date('d-m-Y H:i:s', strtotime($transfer['created_at'])) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Created By</span>
                <span class="detail-value"><?= esc($transfer['created_by_name'] ?: '—') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Item Key</span>
                <span class="detail-value"><code style="font-size:.8rem;"><?= esc($transfer['item_key']) ?></code></span>
            </div>
            <?php if ($transfer['out_movement_id']): ?>
            <div class="detail-row">
                <span class="detail-label">Stock Movements</span>
                <span class="detail-value">
                    <span class="mov-badge-out me-2">OUT #<?= (int)$transfer['out_movement_id'] ?></span>
                    <span class="mov-badge-in">IN #<?= (int)$transfer['in_movement_id'] ?></span>
                </span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Back link -->
    <div class="mb-5">
        <a href="<?= base_url('/inventory/transfers') ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to All Transfers
        </a>
        <a href="<?= base_url('/inventory/transfers/create') ?>" class="btn btn-sm btn-primary ms-2">
            <i class="bi bi-plus me-1"></i>New Transfer
        </a>
    </div>

</div>
<?= $this->endSection() ?>
