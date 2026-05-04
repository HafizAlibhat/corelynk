<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>
Pending Shipment Follow-up
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
    .pf-hero {
        background: linear-gradient(120deg, #0f172a 0%, #1e3a8a 100%);
        border: 1px solid rgba(148, 163, 184, 0.25);
        border-radius: 12px;
        color: #e2e8f0;
        padding: 1rem 1.1rem;
        margin-bottom: 1rem;
    }
    .pf-hero h2 { margin: 0; font-size: 1.3rem; font-weight: 700; color: #f8fafc; }
    .pf-hero p { margin: .25rem 0 0; color: #cbd5e1; font-size: .84rem; }

    .pf-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .6rem; margin: 0 0 1rem; }
    .pf-kpi {
        border: 1px solid var(--cl-border, #334155);
        border-radius: 10px;
        background: var(--cl-surface, #1e293b);
        padding: .7rem .8rem;
    }
    .pf-kpi-label { color: var(--cl-text-muted, #94a3b8); font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; }
    .pf-kpi-val { color: var(--cl-text-primary, #f1f5f9); font-size: 1.15rem; font-weight: 700; line-height: 1.2; margin-top: .15rem; }

    .pf-table-wrap {
        border: 1px solid var(--cl-border, #334155);
        border-radius: 10px;
        overflow: hidden;
        background: var(--cl-surface, #1e293b);
    }
    .pf-table-wrap .table { margin-bottom: 0; }
    .pf-table-wrap thead th {
        font-size: .68rem;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: var(--cl-text-muted, #94a3b8);
        background: var(--cl-surface-alt, #162033);
        border-bottom-color: var(--cl-border, #334155);
        white-space: nowrap;
    }
    .pf-table-wrap tbody td { font-size: .84rem; vertical-align: middle; }
    .pf-row-overdue td { background: rgba(239, 68, 68, 0.08) !important; }
    .pf-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border-radius: 999px;
        padding: 3px 9px;
        font-size: .68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        border: 1px solid transparent;
    }
    .pf-status-pill.pending { color: #fbbf24; background: rgba(251, 191, 36, .13); border-color: rgba(251, 191, 36, .25); }
    .pf-status-pill.overdue { color: #f87171; background: rgba(248, 113, 113, .13); border-color: rgba(248, 113, 113, .25); }
    .pf-empty {
        border: 1px dashed var(--cl-border, #334155);
        border-radius: 10px;
        padding: 2.2rem 1rem;
        text-align: center;
        color: var(--cl-text-muted, #94a3b8);
    }

    @media (max-width: 992px) {
        .pf-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
</style>

<?php
    $rows = $delivery_orders ?? [];
    $totalPending = count($rows);
    $totalOverdue = 0;
    $missingTracking = 0;
    $avgTransit = 0;
    $transitRows = 0;
    foreach ($rows as $r) {
        $shippedAt = $r['shipped_at'] ?? null;
        $estDays = (int)($r['estimated_delivery_days'] ?? 0);
        $daysInTransit = $shippedAt ? (int)floor((time() - strtotime($shippedAt)) / 86400) : 0;
        if ($shippedAt) {
            $avgTransit += $daysInTransit;
            $transitRows++;
        }
        if ($estDays > 0 && $daysInTransit > $estDays) {
            $totalOverdue++;
        }
        if (empty($r['tracking_number'])) {
            $missingTracking++;
        }
    }
    $avgTransitDays = $transitRows > 0 ? round($avgTransit / $transitRows, 1) : 0;
?>

<div class="pf-hero d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h2><i class="bi bi-truck me-2"></i>Pending Shipment Follow-up</h2>
        <p>Only in-transit deliveries are listed here. Once marked Delivered, they are removed from this monitor and remain in Delivery Orders history.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= site_url('delivery-orders/shipped') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-list me-1"></i>Shipped Orders
        </a>
        <a href="<?= site_url('delivery-orders') ?>" class="btn btn-outline-light">
            <i class="bi bi-truck me-1"></i>All Delivery Orders
        </a>
        <a href="<?= site_url('dashboard') ?>" class="btn btn-primary">
            <i class="bi bi-speedometer2 me-1"></i>Dashboard
        </a>
    </div>
</div>

<div class="pf-kpis" id="pendingKpiWrap">
    <div class="pf-kpi">
        <div class="pf-kpi-label">Pending</div>
        <div class="pf-kpi-val" id="kpiPending"><?= (int)$totalPending ?></div>
    </div>
    <div class="pf-kpi">
        <div class="pf-kpi-label">Overdue</div>
        <div class="pf-kpi-val" id="kpiOverdue"><?= (int)$totalOverdue ?></div>
    </div>
    <div class="pf-kpi">
        <div class="pf-kpi-label">Missing Tracking</div>
        <div class="pf-kpi-val" id="kpiMissingTracking"><?= (int)$missingTracking ?></div>
    </div>
    <div class="pf-kpi">
        <div class="pf-kpi-label">Avg Transit (Days)</div>
        <div class="pf-kpi-val"><?= esc((string)$avgTransitDays) ?></div>
    </div>
</div>

<div class="pf-table-wrap" id="pendingTableWrap">
<?php if (empty($rows)): ?>
    <div class="pf-empty" id="pendingEmptyState">
        <i class="bi bi-check2-circle" style="font-size:2.2rem;color:#34d399"></i>
        <div class="mt-2">No pending shipments. Great job.</div>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle" id="pendingShipmentTable">
            <thead>
                <tr>
                    <th>DO</th>
                    <th>SO / Customer</th>
                    <th>Tracking</th>
                    <th>Shipped</th>
                    <th>Transit</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $do): ?>
                    <?php
                        $shippedAt = $do['shipped_at'] ?? null;
                        $estDays = (int)($do['estimated_delivery_days'] ?? 0);
                        $daysInTransit = $shippedAt ? (int)floor((time() - strtotime($shippedAt)) / 86400) : 0;
                        $isOverdue = $estDays > 0 && $daysInTransit > $estDays;
                        $so = $do['sales_order'] ?? [];
                        $doIdentifier = !empty($do['public_id']) ? $do['public_id'] : (int)$do['do_id'];
                        $deliveryStatus = trim((string)($do['delivery_status'] ?? ''));
                        $deliveryStatusLabel = $deliveryStatus !== '' ? ucfirst(str_replace('_', ' ', $deliveryStatus)) : 'In Transit';
                    ?>
                    <tr class="pending-row <?= $isOverdue ? 'pf-row-overdue' : '' ?>" data-do-id="<?= (int)$do['do_id'] ?>" data-missing-tracking="<?= empty($do['tracking_number']) ? '1' : '0' ?>" data-overdue="<?= $isOverdue ? '1' : '0' ?>">
                        <td>
                            <a href="<?= site_url('delivery-orders/view/' . $doIdentifier) ?>" class="fw-semibold text-decoration-none">
                                <?= esc($do['do_number']) ?>
                            </a>
                            <?php if (!empty($do['destination_country'])): ?>
                                <div><small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= esc($do['destination_country']) ?></small></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div><?= esc($so['order_number'] ?? '-') ?></div>
                            <small class="text-muted"><?= esc($so['customer_code'] ?? ($so['customer_name'] ?? 'N/A')) ?></small>
                        </td>
                        <td>
                            <?php if (!empty($do['tracking_number'])): ?>
                                <span class="d-inline-flex align-items-center gap-1">
                                <?php if (!empty($do['tracking_url'])): ?>
                                    <a href="<?= esc($do['tracking_url']) ?>" target="_blank" rel="noopener" class="text-decoration-none">
                                        <?= esc($do['tracking_number']) ?>
                                        <i class="bi bi-box-arrow-up-right" style="font-size:.7rem"></i>
                                    </a>
                                <?php else: ?>
                                    <?= esc($do['tracking_number']) ?>
                                <?php endif; ?>
                                    <button
                                        type="button"
                                        class="btn btn-link p-0 js-copy-tracking"
                                        data-tracking="<?= esc($do['tracking_number']) ?>"
                                        title="Copy tracking number"
                                        aria-label="Copy tracking number"
                                        style="font-size:.82rem;line-height:1;color:#60a5fa;"
                                    >
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                    <a href="#" class="small js-copy-tracking" data-tracking="<?= esc($do['tracking_number']) ?>" style="text-decoration:none;">Copy</a>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Missing</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $shippedAt ? date('d M Y H:i', strtotime($shippedAt)) : '-' ?>
                        </td>
                        <td>
                            <span class="badge <?= $isOverdue ? 'bg-danger' : 'bg-secondary' ?>">
                                Day <?= $daysInTransit ?><?= $estDays > 0 ? ' / ' . $estDays : '' ?>
                            </span>
                            <?php if ($isOverdue): ?>
                                <div><small class="text-danger">Overdue by <?= $daysInTransit - $estDays ?> day<?= ($daysInTransit - $estDays) > 1 ? 's' : '' ?></small></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="pf-status-pill <?= $isOverdue ? 'overdue' : 'pending' ?>">
                                <i class="bi bi-<?= $isOverdue ? 'exclamation-triangle' : 'truck' ?>"></i>
                                <?= esc($deliveryStatusLabel) ?>
                            </span>
                        </td>
                        <td class="text-nowrap">
                            <button
                                type="button"
                                class="btn btn-sm btn-success js-open-status-modal"
                                data-do-id="<?= (int)$do['do_id'] ?>"
                                data-do-number="<?= esc($do['do_number']) ?>"
                            >
                                <i class="bi bi-check2-circle me-1"></i>Update Status
                            </button>
                            <a href="<?= site_url('delivery-orders/view/' . $doIdentifier) ?>" class="btn btn-sm btn-outline-primary">Open</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</div>

<div class="modal fade" id="pendingStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background:var(--cl-surface,#1e293b);border:1px solid var(--cl-border,#334155);">
            <div class="modal-header" style="border-bottom-color:var(--cl-border,#334155);">
                <h5 class="modal-title">Update Delivery Status <small class="text-muted" id="pendingModalDoLabel"></small></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="pendingModalDoId" value="">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">Status <span class="text-danger">*</span></label>
                        <select class="form-select" id="pendingDeliveryStatusSelect">
                            <option value="">-- Select Status --</option>
                            <option value="delivered">Delivered Successfully</option>
                            <option value="partial_delivery">Partial Delivery</option>
                            <option value="delayed">Delayed</option>
                            <option value="lost">Lost in Transit</option>
                            <option value="damaged_in_transit">Damaged in Transit</option>
                            <option value="customer_refused">Customer Refused</option>
                            <option value="returned_to_sender">Returned to Sender</option>
                        </select>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label">Comments</label>
                        <input type="text" class="form-control" id="pendingDeliveryNotesInput" placeholder="Add remarks, receiver name, issue details, etc.">
                    </div>
                </div>

                <div id="pendingDeliveredExtraFields" class="mt-3" style="display:none;padding-top:.75rem;border-top:1px solid var(--cl-border,#334155);">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">Delivery Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="pendingDeliveredAtInput" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Delivery Screenshot / Proof</label>
                            <input type="file" class="form-control" id="pendingDeliveryScreenshotInput" accept="image/*">
                            <div class="form-text">Optional. JPG/PNG/GIF/WEBP up to 5MB.</div>
                        </div>
                    </div>
                </div>
                <div id="pendingStatusMsg" class="mt-3" style="min-height:1rem;font-size:.85rem;"></div>
            </div>
            <div class="modal-footer" style="border-top-color:var(--cl-border,#334155);">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="pendingSaveStatusBtn">
                    <i class="bi bi-check-circle me-1"></i>Save Status
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const table = document.getElementById('pendingShipmentTable');
    if (!table) return;

    const csrfName = '<?= csrf_token() ?>';
    const csrfHash = '<?= csrf_hash() ?>';
    const kpiPending = document.getElementById('kpiPending');
    const kpiOverdue = document.getElementById('kpiOverdue');
    const kpiMissing = document.getElementById('kpiMissingTracking');
    const modalEl = document.getElementById('pendingStatusModal');
    const modalDoId = document.getElementById('pendingModalDoId');
    const modalDoLabel = document.getElementById('pendingModalDoLabel');
    const statusSelect = document.getElementById('pendingDeliveryStatusSelect');
    const notesInput = document.getElementById('pendingDeliveryNotesInput');
    const deliveredAtInput = document.getElementById('pendingDeliveredAtInput');
    const screenshotInput = document.getElementById('pendingDeliveryScreenshotInput');
    const deliveredExtra = document.getElementById('pendingDeliveredExtraFields');
    const saveBtn = document.getElementById('pendingSaveStatusBtn');
    const statusMsg = document.getElementById('pendingStatusMsg');
    const statusModal = (window.bootstrap && modalEl) ? new bootstrap.Modal(modalEl) : null;

    function openStatusModal() {
        if (!modalEl) return;
        if (statusModal) {
            statusModal.show();
            return;
        }
        modalEl.style.display = 'block';
        modalEl.classList.add('show');
        modalEl.removeAttribute('aria-hidden');
        modalEl.setAttribute('aria-modal', 'true');
        document.body.classList.add('modal-open');

        if (!document.getElementById('pendingStatusModalBackdrop')) {
            const backdrop = document.createElement('div');
            backdrop.id = 'pendingStatusModalBackdrop';
            backdrop.className = 'modal-backdrop fade show';
            backdrop.addEventListener('click', closeStatusModal);
            document.body.appendChild(backdrop);
        }
    }

    function closeStatusModal() {
        if (!modalEl) return;
        if (statusModal) {
            statusModal.hide();
            return;
        }
        modalEl.classList.remove('show');
        modalEl.style.display = 'none';
        modalEl.setAttribute('aria-hidden', 'true');
        modalEl.removeAttribute('aria-modal');
        document.body.classList.remove('modal-open');
        const backdrop = document.getElementById('pendingStatusModalBackdrop');
        if (backdrop) backdrop.remove();
    }

    function updateCountersAfterRemove(row) {
        if (kpiPending) {
            kpiPending.textContent = String(Math.max(0, parseInt(kpiPending.textContent || '0', 10) - 1));
        }
        if (row?.dataset?.overdue === '1' && kpiOverdue) {
            kpiOverdue.textContent = String(Math.max(0, parseInt(kpiOverdue.textContent || '0', 10) - 1));
        }
        if (row?.dataset?.missingTracking === '1' && kpiMissing) {
            kpiMissing.textContent = String(Math.max(0, parseInt(kpiMissing.textContent || '0', 10) - 1));
        }
    }

    function ensureEmptyState() {
        const body = table.querySelector('tbody');
        if (!body) return;
        if (body.querySelectorAll('tr.pending-row').length > 0) return;

        const wrap = document.getElementById('pendingTableWrap');
        if (!wrap) return;
        wrap.innerHTML = '<div class="pf-empty" id="pendingEmptyState"><i class="bi bi-check2-circle" style="font-size:2.2rem;color:#34d399"></i><div class="mt-2">No pending shipments. Great job.</div></div>';
    }

    async function updateDeliveryStatus(doId) {
        if (!doId) return;

        const row = table.querySelector('tr.pending-row[data-do-id="' + String(doId) + '"]');
        const st = (statusSelect?.value || '').trim();
        const notes = (notesInput?.value || '').trim();
        const deliveredAt = (deliveredAtInput?.value || '').trim();

        if (!st) {
            if (statusMsg) statusMsg.innerHTML = '<span class="text-danger">Please select a status.</span>';
            return;
        }
        if (st === 'delivered' && !deliveredAt) {
            if (statusMsg) statusMsg.innerHTML = '<span class="text-danger">Please select delivery date.</span>';
            return;
        }

        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
        }
        if (statusMsg) statusMsg.innerHTML = '';

        try {
            const fd = new FormData();
            fd.append('delivery_status', st);
            fd.append('delivery_notes', notes);
            if (st === 'delivered') {
                fd.append('delivered_at', deliveredAt);
                if (screenshotInput && screenshotInput.files && screenshotInput.files[0]) {
                    fd.append('delivery_screenshot', screenshotInput.files[0]);
                }
            }
            fd.append(csrfName, csrfHash);

            const res = await fetch('<?= site_url('delivery-orders/update-delivery-status/') ?>' + doId, {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            const raw = await res.text();
            let data = null;
            try {
                data = JSON.parse(raw);
            } catch (e) {
                throw new Error('Server response was not valid JSON. HTTP ' + res.status);
            }

            if (!data || !data.success) {
                throw new Error((data && data.message) ? data.message : 'Failed to update status');
            }

            if (st === 'delivered') {
                if (row) {
                    updateCountersAfterRemove(row);
                    row.remove();
                }
                ensureEmptyState();
            } else {
                if (row) {
                    const pill = row.querySelector('.pf-status-pill');
                    if (pill) {
                        pill.innerHTML = '<i class="bi bi-truck"></i> ' + st.replaceAll('_', ' ').replace(/\b\w/g, function(m){ return m.toUpperCase(); });
                    }
                }
            }

            closeStatusModal();
        } catch (e) {
            if (statusMsg) {
                statusMsg.innerHTML = '<span class="text-danger">Could not update status: ' + e.message + '</span>';
            } else {
                alert('Could not update status: ' + e.message);
            }
        } finally {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Save Status';
            }
        }
    }

    statusSelect?.addEventListener('change', function() {
        if (deliveredExtra) {
            deliveredExtra.style.display = this.value === 'delivered' ? 'block' : 'none';
        }
    });

    document.querySelectorAll('.js-open-status-modal').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const doId = btn.getAttribute('data-do-id') || '';
            const doNumber = btn.getAttribute('data-do-number') || '';
            if (modalDoId) modalDoId.value = doId;
            if (modalDoLabel) modalDoLabel.textContent = doNumber ? '(' + doNumber + ')' : '';
            if (statusSelect) statusSelect.value = 'delivered';
            if (notesInput) notesInput.value = '';
            if (deliveredAtInput) deliveredAtInput.value = '<?= date('Y-m-d') ?>';
            if (screenshotInput) screenshotInput.value = '';
            if (statusMsg) statusMsg.innerHTML = '';
            if (deliveredExtra) deliveredExtra.style.display = 'block';
            openStatusModal();
        });
    });

    modalEl?.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function(el) {
        el.addEventListener('click', function(e) {
            if (!statusModal) {
                e.preventDefault();
                closeStatusModal();
            }
        });
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modalEl?.classList.contains('show')) {
            closeStatusModal();
        }
    });

    saveBtn?.addEventListener('click', function() {
        updateDeliveryStatus(modalDoId?.value || '');
    });

    function copyTracking(value, triggerEl) {
        const text = (value || '').trim();
        if (!text) return;

        const setCopiedState = function() {
            if (!triggerEl) return;
            const icon = triggerEl.querySelector('i');
            if (icon) {
                icon.className = 'bi bi-clipboard-check';
                icon.style.color = '#34d399';
                setTimeout(function() {
                    icon.className = 'bi bi-clipboard';
                    icon.style.color = '';
                }, 1200);
            }
            if (!icon) {
                const oldText = triggerEl.textContent;
                triggerEl.textContent = 'Copied';
                setTimeout(function() { triggerEl.textContent = oldText; }, 1200);
            }
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(setCopiedState).catch(function() {
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.focus();
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                setCopiedState();
            });
            return;
        }

        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        setCopiedState();
    }

    document.querySelectorAll('.js-copy-tracking').forEach(function(el) {
        el.addEventListener('click', function(e) {
            e.preventDefault();
            copyTracking(el.getAttribute('data-tracking') || '', el);
        });
    });
})();
</script>

<?= $this->endSection() ?>
