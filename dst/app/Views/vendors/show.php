<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>
Vendor: <?= esc($vendor['name'] ?? 'Details') ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php $vendorIdentifier = entityRouteIdentifier($vendor); ?>
<div class="container-fluid" style="padding: 1.5rem;">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div class="flex-grow-1">
            <div class="mb-2">
                <h4 class="mb-1 fw-bold text-light">
                    <i class="bi bi-building me-2" style="color: #4a9eff;"></i>
                    <?= esc($vendor['name']) ?>
                </h4>
                <small class="text-muted">Vendor ID: <span class="text-light fw-semibold"><?= esc($vendor['vendor_code'] ?? 'N/A') ?></span></small>
            </div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= base_url('vendors') ?>" class="text-decoration-none">Vendors</a></li>
                    <li class="breadcrumb-item active small"><?= esc($vendor['vendor_code'] ?? 'Profile') ?></li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2 flex-shrink-0">
            <a href="<?= base_url('vendors') ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back
            </a>
            <?php if ($can_edit): ?>
                <a href="<?= base_url('vendors/' . $vendorIdentifier . '/edit') ?>" class="btn btn-sm btn-primary">
                    <i class="bi bi-pencil me-1"></i>Edit
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Content -->
    <div class="row g-3">
        <!-- Left Sidebar: Compact Vendor Info -->
        <div class="col-lg-3">
            <div class="card border-0 shadow-sm" style="border-radius: 8px; background: rgba(255,255,255,0.05);">
                <div class="card-body p-3">
                    <!-- Status Badge -->
                    <div class="mb-3">
                        <?php if ($vendor['is_active']): ?>
                            <span class="badge text-success fw-semibold px-2 py-1" style="background-color: rgba(34,197,94,0.2); border-radius: 4px; font-size: 0.8rem;">
                                <i class="bi bi-check-circle me-1"></i>Active
                            </span>
                        <?php else: ?>
                            <span class="badge text-danger fw-semibold px-2 py-1" style="background-color: rgba(220,38,38,0.2); border-radius: 4px; font-size: 0.8rem;">
                                <i class="bi bi-x-circle me-1"></i>Inactive
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Info Items - Compact -->
                    <div class="small text-muted mb-2" style="border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 0.75rem;">
                        <div class="text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px; margin-bottom: 0.25rem;">Vendor Code</div>
                        <div class="text-light fw-semibold"><?= esc($vendor['vendor_code'] ?? 'N/A') ?></div>
                    </div>

                    <div class="small text-muted mb-2" style="border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 0.75rem;">
                        <div class="text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px; margin-bottom: 0.25rem;">Contact</div>
                        <div class="text-light fw-500" style="font-size: 0.9rem;"><?= esc($vendor['contact_person'] ?: '—') ?></div>
                    </div>

                    <div class="small text-muted mb-2" style="border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 0.75rem;">
                        <div class="text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px; margin-bottom: 0.25rem;">Email</div>
                        <a href="mailto:<?= esc($vendor['email']) ?>" class="text-decoration-none text-light fw-500" style="font-size: 0.9rem; word-break: break-word;"><?= esc($vendor['email'] ?: '—') ?></a>
                    </div>

                    <div class="small text-muted mb-2" style="border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 0.75rem;">
                        <div class="text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px; margin-bottom: 0.25rem;">Phone</div>
                        <a href="tel:<?= esc($vendor['phone']) ?>" class="text-decoration-none text-light fw-500" style="font-size: 0.9rem;"><?= esc($vendor['phone'] ?: '—') ?></a>
                    </div>

                    <div class="small text-muted">
                        <div class="text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px; margin-bottom: 0.25rem;">Address</div>
                        <div class="text-light lh-sm" style="font-size: 0.9rem;"><?= nl2br(esc($vendor['address'] ?: '—')) ?></div>
                    </div>
                </div>
            </div>

            <!-- Stats Card -->
            <div class="card border-0 shadow-sm mt-3" style="border-left: 3px solid #4a9eff; border-radius: 8px;">
                <div class="card-body p-3">
                    <div class="text-uppercase fw-bold small text-muted mb-2" style="font-size: 0.7rem;">Assigned Processes</div>
                    <div class="fs-4 fw-bold text-light"><?= $vendor['process_count'] ?? 0 ?></div>
                </div>
            </div>
        </div>

        <!-- Right Content: Business Data -->
        <div class="col-lg-9">
            <?php $sum = $vendor_summary ?? []; ?>
            
            <!-- Business Summary Stats - Compact -->
            <div class="card border-0 shadow-sm mb-3" style="border-radius: 8px;">
                <div class="card-body p-3">
                    <h6 class="mb-3 fw-bold text-light" style="font-size: 0.95rem;">
                        <i class="bi bi-graph-up me-2" style="color: #4a9eff;"></i>Business Summary
                    </h6>
                    
                    <div class="row g-2">
                        <div class="col-6 col-md-3">
                            <div class="rounded p-2 small" style="background: rgba(74, 158, 255, 0.15); border-left: 2px solid #4a9eff;">
                                <div class="text-muted fw-semibold mb-1" style="font-size: 0.75rem;">Total</div>
                                <div class="text-light fw-bold"><?= number_format((float)($sum['total_business'] ?? 0), 2) ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="rounded p-2 small" style="background: rgba(34, 197, 94, 0.15); border-left: 2px solid #22c55e;">
                                <div class="text-muted fw-semibold mb-1" style="font-size: 0.75rem;">Paid</div>
                                <div class="text-success fw-bold"><?= number_format((float)($sum['paid_total'] ?? 0), 2) ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="rounded p-2 small" style="background: rgba(239, 68, 68, 0.15); border-left: 2px solid #ef4444;">
                                <div class="text-muted fw-semibold mb-1" style="font-size: 0.75rem;">Pending</div>
                                <div class="text-danger fw-bold"><?= number_format((float)($sum['pending_total'] ?? 0), 2) ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="rounded p-2 small" style="background: rgba(251, 191, 36, 0.15); border-left: 2px solid #fbbf24;">
                                <div class="text-muted fw-semibold mb-1" style="font-size: 0.75rem;">Docs</div>
                                <div class="text-light fw-bold"><?= (int)($sum['bill_count'] ?? 0) ?>/<?= (int)($sum['po_count'] ?? 0) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bills and POs Section -->
            <div class="row g-3">
                <!-- Bills -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm" style="border-radius: 8px; overflow: hidden;">
                        <div class="card-header p-3" style="background: rgba(74, 158, 255, 0.1); border-bottom: 1px solid rgba(255,255,255,0.1);">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold text-light small">
                                    <i class="bi bi-receipt me-1"></i>Last 5 Bills
                                </h6>
                                <a href="<?= base_url('vendor-bills?vendor_id=' . (int)($vendor['id'] ?? 0)) ?>" class="small text-light text-decoration-none">View All →</a>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php $rb = $recent_bills ?? []; ?>
                            <?php if (empty($rb)): ?>
                                <div class="p-3 text-center text-muted small">No bills found</div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($rb as $b): ?>
                                        <a href="<?= base_url('vendor-bills/' . (int)$b['id']) ?>" class="list-group-item list-group-item-action px-3 py-2 text-decoration-none" style="background: transparent; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                            <div class="d-flex justify-content-between align-items-start small">
                                                <div class="flex-grow-1">
                                                    <div class="text-light fw-semibold mb-1" style="font-size: 0.9rem;">
                                                        <?= esc($b['bill_number'] ?: ('VB-' . (int)$b['id'])) ?>
                                                    </div>
                                                    <?php
                                                        $bStatus = $b['status'] ?? 'draft';
                                                        $bBal    = (float)($b['balance'] ?? 0);
                                                        $bTotal  = (float)($b['total_amount'] ?? 0);
                                                        $bPaid   = (float)($b['paid_total'] ?? 0);
                                                        if ($bStatus === 'cancelled') {
                                                            $bsBadge = '<span style="font-size:.65rem;padding:.2rem .4rem;border-radius:3px;background:rgba(100,115,139,0.2);color:#a0aec0;font-weight:600;">Cancelled</span>';
                                                        } elseif ($bBal <= 0.001 && $bTotal > 0) {
                                                            $bsBadge = '<span style="font-size:.65rem;padding:.2rem .4rem;border-radius:3px;background:rgba(34,197,94,0.2);color:#86efac;font-weight:600;">Paid</span>';
                                                        } elseif ($bPaid > 0.001) {
                                                            $bsBadge = '<span style="font-size:.65rem;padding:.2rem .4rem;border-radius:3px;background:rgba(251,191,36,0.2);color:#fde68a;font-weight:600;">Partial</span>';
                                                        } else {
                                                            $bsBadge = '<span style="font-size:.65rem;padding:.2rem .4rem;border-radius:3px;background:rgba(239,68,68,0.2);color:#fca5a5;font-weight:600;">Unpaid</span>';
                                                        }
                                                    ?>
                                                    <div class="text-muted" style="font-size: 0.8rem;">
                                                        <?php
                                                            $bDateRaw = $b['bill_date'] ?? '';
                                                            $bDateTs  = $bDateRaw ? strtotime($bDateRaw) : 0;
                                                            echo $bDateTs ? date('d M Y', $bDateTs) : esc($bDateRaw ?: '—');
                                                        ?>
                                                        &nbsp;<?= $bsBadge ?>
                                                    </div>
                                                </div>
                                                <div class="text-end ms-2">
                                                    <div class="text-light fw-semibold" style="font-size: 0.9rem;"><?= number_format((float)($b['total_amount'] ?? 0), 2) ?></div>
                                                    <div class="text-danger" style="font-size: 0.8rem;">Bal: <?= number_format((float)($b['balance'] ?? 0), 2) ?></div>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Purchase Orders -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm" style="border-radius: 8px; overflow: hidden;">
                        <div class="card-header p-3" style="background: rgba(34, 197, 94, 0.1); border-bottom: 1px solid rgba(255,255,255,0.1);">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold text-light small">
                                    <i class="bi bi-clipboard-check me-1"></i>Last 5 POs
                                </h6>
                                <a href="<?= base_url('newpurchaseui/rfqpo') ?>" class="small text-light text-decoration-none">View All →</a>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php $rpo = $recent_pos ?? []; ?>
                            <?php if (empty($rpo)): ?>
                                <div class="p-3 text-center text-muted small">No purchase orders found</div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($rpo as $po): ?>
                                        <a href="<?= base_url('purchases/po/' . (int)$po['id']) ?>" class="list-group-item list-group-item-action px-3 py-2 text-decoration-none" style="background: transparent; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                            <div class="d-flex justify-content-between align-items-start small">
                                                <div class="flex-grow-1">
                                                    <div class="text-light fw-semibold mb-1" style="font-size: 0.9rem;">
                                                        <?= esc($po['po_number'] ?: ('PO-' . (int)$po['id'])) ?>
                                                    </div>
                                                    <div class="text-muted d-flex gap-1 align-items-center" style="font-size: 0.8rem;">
                                                        <span><?= esc($po['status'] ?? 'Draft') ?></span>
                                                        <span style="padding:.15rem .4rem;border-radius:3px;background:rgba(34,197,94,0.2);color:#86efac;font-weight:600; font-size: 0.7rem;">On Track</span>
                                                    </div>
                                                </div>
                                                <div class="text-end ms-2">
                                                    <div class="text-light fw-semibold" style="font-size: 0.9rem;"><?= number_format((float)($po['total'] ?? 0), 2) ?></div>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alternate Contacts -->
            <div class="card border-0 shadow-sm mt-3" style="border-radius: 8px; overflow: hidden;">
                <div class="card-header p-3" style="background: rgba(74, 158, 255, 0.1); border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-light small">
                            <i class="bi bi-people me-1"></i>Alternate Contacts
                        </h6>
                        <?php if ($can_edit): ?>
                            <button id="btn-add-contact" type="button" class="btn btn-sm text-light" style="font-size: 0.85rem;">
                                <i class="bi bi-plus-lg me-1"></i>Add
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div id="contacts_list">
                        <?php if (!empty($vendor_contacts)): ?>
                            <div id="contacts_container" class="list-group list-group-flush">
                                <?php foreach ($vendor_contacts as $idx => $c): ?>
                                    <div id="contact_li_<?= esc($c['id']) ?>" class="list-group-item px-3 py-2" style="background: transparent; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                        <div class="d-flex justify-content-between align-items-start small">
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-center mb-1">
                                                    <strong class="text-light me-2" style="font-size: 0.9rem;"><?= esc($c['name']) ?></strong>
                                                    <?php if (!empty($c['is_primary'])): ?>
                                                        <span class="badge bg-primary rounded-pill" style="font-size: 0.65rem;">Primary</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-muted" style="font-size: 0.8rem;">
                                                    <?php if (!empty($c['designation'])): ?>
                                                        <div><?= esc($c['designation']) ?></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($c['phone'])): ?>
                                                        <div><?= esc($c['phone']) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php if ($can_edit): ?>
                                                <div class="btn-group btn-group-sm ms-2">
                                                    <button type="button" class="btn btn-outline-secondary btn-sm" 
                                                            data-contact-id="<?= esc($c['id']) ?>" 
                                                            data-name="<?= esc($c['name']) ?>" 
                                                            data-phone="<?= esc($c['phone']) ?>" 
                                                            data-cnic="<?= esc($c['cnic']) ?>" 
                                                            data-designation="<?= esc($c['designation']) ?>" 
                                                            onclick="editContact(this)">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger btn-sm" 
                                                            data-contact-id="<?= esc($c['id']) ?>" 
                                                            onclick="deleteContact(this)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="p-3 text-center text-muted small">No alternate contacts found</div>
                        <?php endif; ?>
                    </div>

                    <!-- Contact Form (hidden by default) -->
                    <div id="contact_form" class="p-3 border-top d-none" style="background: rgba(255,255,255,0.02);">
                        <form id="vendorContactForm" method="post" action="<?= base_url('vendors/' . $vendorIdentifier) ?>/addContact">
                            <?= csrf_field() ?>
                            <input type="hidden" id="contact_id_input" name="contact_id" value="">
                            <div class="row g-2 small">
                                <div class="col-md-6">
                                    <label class="form-label mb-1 fw-bold" style="font-size: 0.85rem;">Contact Name *</label>
                                    <input id="contact_name" name="name" class="form-control form-control-sm" placeholder="Full name" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label mb-1 fw-bold" style="font-size: 0.85rem;">Designation</label>
                                    <input id="contact_designation" name="designation" class="form-control form-control-sm" placeholder="Role">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label mb-1 fw-bold" style="font-size: 0.85rem;">Phone</label>
                                    <input id="contact_phone" name="phone" class="form-control form-control-sm" placeholder="Phone number">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label mb-1 fw-bold" style="font-size: 0.85rem;">CNIC / ID</label>
                                    <input id="contact_cnic" name="cnic" class="form-control form-control-sm" placeholder="ID Number">
                                </div>
                            </div>
                            <div class="mt-3 d-flex justify-content-end gap-2">
                                <button type="button" id="cancel_contact" class="btn btn-sm btn-secondary">Cancel</button>
                                <button type="submit" class="btn btn-sm btn-success">Save Contact</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Manufacturing Processes Table -->
            <div class="card border-0 shadow-sm mt-3" style="border-radius: 8px; overflow: hidden;">
                <div class="card-header p-3" style="background: rgba(74, 158, 255, 0.1); border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <h6 class="mb-0 fw-bold text-light small">
                        <i class="bi bi-gear me-1"></i>Manufacturing Processes
                    </h6>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($vendor_processes)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0" style="color: #e2e4e9;">
                                <thead style="background: rgba(255,255,255,0.05); border-bottom: 1px solid rgba(255,255,255,0.1);">
                                    <tr>
                                        <th class="fw-bold small">Process Name</th>
                                        <th class="fw-bold small">Description</th>
                                        <th class="text-center fw-bold small">Status</th>
                                        <th class="fw-bold small">Created</th>
                                    </tr>
                                </thead>
                                <tbody style="border-color: rgba(255,255,255,0.05);">
                                    <?php foreach ($vendor_processes as $process): ?>
                                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                        <td class="small">
                                            <a href="<?= base_url('processes/' . $process['id']) ?>" class="text-decoration-none text-light fw-semibold">
                                                <?= esc($process['name']) ?>
                                            </a>
                                        </td>
                                        <td class="small text-muted"><?= esc($process['description'] ?: 'No description') ?></td>
                                        <td class="text-center small">
                                            <span class="badge <?= $process['is_active'] ? 'bg-success' : 'bg-secondary' ?> rounded-pill" style="font-size: 0.7rem;">
                                                <?= $process['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td class="small text-muted"><?= date('M d, Y', strtotime($process['created_at'])) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted small">No manufacturing processes assigned</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .list-group-item:hover {
        background-color: rgba(74, 158, 255, 0.08) !important;
    }
    .btn:hover {
        transform: translateY(-1px);
    }
    .text-light {
        color: #e2e4e9 !important;
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
    const vendorId = <?= json_encode($vendor['id'] ?? null) ?>;
    const vendorIdentifier = <?= json_encode($vendorIdentifier) ?>;
    const vendorBase = <?= json_encode(base_url('vendors')) ?>;
    const csrfTokenName = <?= json_encode(csrf_token()) ?>;
    const csrfTokenValue = <?= json_encode(csrf_hash()) ?>;
</script>
<script src="<?= base_url('assets/js/vendor_contacts.js') ?>"></script>
<?= $this->endSection() ?>
