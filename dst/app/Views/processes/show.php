<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?= base_url('/processes') ?>">Processes</a></li>
                        <li class="breadcrumb-item active"><?= esc($process['name']) ?></li>
                    </ol>
                </nav>
                <h2 class="mb-0">
                    <i class="bi bi-diagram-3 me-2"></i>
                    <?= esc($process['name']) ?>
                </h2>
            </div>
            <div class="btn-group">
                <?php if ($can_edit): ?>
                    <a href="<?= base_url('/processes/' . $process['id'] . '/edit') ?>" class="btn btn-primary">
                        <i class="bi bi-pencil me-2"></i>Edit Process
                    </a>
                <?php endif; ?>
                <a href="<?= base_url('/processes') ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to List
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Process Details -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-info-circle me-2"></i>Process Information
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Process Name</label>
                        <div class="fw-semibold"><?= esc($process['name']) ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Process Type</label>
                        <div>
                            <?php 
                                // Determine type: prefer process_type, fallback to is_vendor_process
                                $ptype = 'in_house';
                                if (isset($process['process_type']) && $process['process_type'] !== '') {
                                    $ptype = $process['process_type'];
                                } elseif (!empty($process['is_vendor_process'])) {
                                    $ptype = 'outsource';
                                }
                            ?>
                            <?php if ($ptype === 'outsource'): ?>
                                <span class="badge bg-warning text-dark">Outsource</span>
                            <?php else: ?>
                                <span class="badge bg-info">In-House</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($process['description'])): ?>
                        <div class="col-12 mb-3">
                            <label class="form-label text-muted">Description</label>
                            <div><?= nl2br(esc($process['description'])) ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($ptype === 'outsource'): ?>
                        <div class="col-md-12 mb-3">
                            <label class="form-label text-muted">Approved Vendors</label>
                            <?php if (!empty($approvedVendors)): ?>
                                <ul class="list-unstyled mb-2">
                                    <?php foreach ($approvedVendors as $v): ?>
                                        <li class="mb-1">
                                            <span class="fw-semibold"><?= esc($v['name']) ?></span>
                                            <?php if (!empty($v['contact_person'])): ?>
                                                <small class="text-muted">&nbsp;— Contact: <?= esc($v['contact_person']) ?></small>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="text-muted">No vendors configured.</div>
                            <?php endif; ?>
                            <?php if ($can_edit): ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= base_url('/processes/' . $process['id'] . '/edit') ?>#vendor_section">
                                    <i class="bi bi-plus-circle me-1"></i> Add/Manage Vendors
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($ptype === 'in_house'): ?>
                        <div class="col-md-12 mb-3">
                            <label class="form-label text-muted">Responsibility</label>
                            <div>
                                <?php if (!empty($process['responsibility_department'])): ?>
                                    <span class="badge bg-info text-dark">Dept: <?= esc($process['responsibility_department']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($process['assigned_employee_names'])): ?>
                                    <div class="mt-2">
                                        <?php foreach ($process['assigned_employee_names'] as $n): ?>
                                            <span class="badge bg-success me-1">Emp: <?= esc($n) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php elseif (empty($process['responsibility_department'])): ?>
                                    <div class="text-muted">No responsible employees configured.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Status</label>
                        <div>
                            <?php if ($process['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Inactive</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sidebar -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-clock-history me-2"></i>Timeline
                </h5>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-marker bg-success"></div>
                        <div class="timeline-content">
                            <h6 class="mb-1">Process Created</h6>
                            <small class="text-muted"><?= date('M j, Y \a\t g:i A', strtotime($process['created_at'])) ?></small>
                        </div>
                    </div>
                    <?php if ($process['updated_at'] !== $process['created_at']): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-info"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">Last Updated</h6>
                                <small class="text-muted"><?= date('M j, Y \a\t g:i A', strtotime($process['updated_at'])) ?></small>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if ($can_edit || $can_delete): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-gear me-2"></i>Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <?php if ($can_edit): ?>
                            <a href="<?= base_url('/processes/' . $process['id'] . '/edit') ?>" class="btn btn-outline-primary">
                                <i class="bi bi-pencil me-2"></i>Edit Process
                            </a>
                        <?php endif; ?>
                        <?php if ($can_delete): ?>
                            <button type="button" class="btn btn-outline-danger" onclick="confirmDelete()">
                                <i class="bi bi-trash me-2"></i>Delete Process
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($can_delete): ?>
<script>
function confirmDelete() {
    if (confirm('Are you sure you want to delete this process? This action cannot be undone.')) {
        fetch('<?= base_url('/processes/' . $process['id']) ?>', {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = '<?= base_url('/processes') ?>';
            } else {
                alert('Error: ' + (data.message || 'Failed to delete process'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting the process');
        });
    }
}
</script>
<?php endif; ?>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -22px;
    top: 3px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 0 0 2px #e9ecef;
}

.timeline-content h6 {
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
}
</style>

<?= $this->endSection() ?>
