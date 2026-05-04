<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3 cl-list-page">
    <!-- Page Header -->
    <div class="cl-list-header">
        <div>
            <h2 class="mb-0">Vendors Management</h2>
            <small class="text-muted">Manage your vendor list and contacts</small>
        </div>
        <?php if ($can_create): ?>
            <a href="<?= base_url('vendors/create') ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>New Vendor
            </a>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="card cl-list-filters mb-3">
        <div class="card-body">
                <?= form_open('', ['method' => 'GET', 'class' => 'row g-3']) ?>
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" 
                           class="form-control" 
                           name="search" 
                           value="<?= esc($current_search) ?>" 
                           placeholder="Vendor name, contact...">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="1" <?= $current_status === '1' ? 'selected' : '' ?>>Active</option>
                        <option value="0" <?= $current_status === '0' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Per Page</label>
                    <select class="form-select" name="per_page">
                        <option value="20" <?= $per_page == 20 ? 'selected' : '' ?>>20</option>
                        <option value="50" <?= $per_page == 50 ? 'selected' : '' ?>>50</option>
                        <option value="100" <?= $per_page == 100 ? 'selected' : '' ?>>100</option>
                    </select>
                </div>
                
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-search me-1"></i>Search
                    </button>
                    <a href="<?= base_url('vendors') ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise me-1"></i>Reset
                    </a>
                </div>
            <?= form_close() ?>
        </div>
    </div>

    <!-- Vendors List -->
    <div class="card cl-list-table-card">
        <div class="card-body p-0">
            <?php if (!empty($vendors['data'])): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="40">
                                    <input type="checkbox" class="form-check-input" id="selectAll">
                                </th>
                                <th>Vendor Name</th>
                                <th>Contact Info</th>
                                <th>Processes</th>
                                <th>Status</th>
                                <th class="actions-col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vendors['data'] as $vendor):
                                $vendorIdentifier = entityRouteIdentifier($vendor);
                            ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input vendor-checkbox" value="<?= $vendor['id'] ?>">
                                    </td>
                                    <td>
                                        <div>
                                            <a href="<?= base_url('vendors/' . $vendorIdentifier) ?>" class="text-decoration-none fw-semibold">
                                                <?= esc($vendor['name']) ?>
                                            </a>
                                            <?php if (!empty($vendor['address'])): ?>
                                                <br><small class="text-muted"><?= esc(substr($vendor['address'], 0, 50)) ?><?= strlen($vendor['address']) > 50 ? '...' : '' ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <?php if (!empty($vendor['contact_person'])): ?>
                                                <div class="mb-1">
                                                    <i class="bi bi-person me-1"></i>
                                                    <small><?= esc($vendor['contact_person']) ?></small>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($vendor['phone'])): ?>
                                                <div class="mb-1">
                                                    <i class="bi bi-telephone me-1"></i>
                                                    <small><a href="tel:<?= esc($vendor['phone']) ?>" class="text-decoration-none"><?= esc($vendor['phone']) ?></a></small>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($vendor['email'])): ?>
                                                <div>
                                                    <i class="bi bi-envelope me-1"></i>
                                                    <small><a href="mailto:<?= esc($vendor['email']) ?>" class="text-decoration-none"><?= esc($vendor['email']) ?></a></small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?= $vendor['process_count'] ?? 0 ?></span>
                                    </td>
                                    <td>
                                        <?php if ($vendor['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions-col">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="<?= base_url('vendors/' . $vendorIdentifier) ?>" 
                                               class="btn btn-sm btn-outline-primary btn-icon" title="View" aria-label="View">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if ($can_edit): ?>
                                                <a href="<?= base_url('vendors/' . $vendorIdentifier . '/edit') ?>" 
                                                   class="btn btn-sm btn-outline-secondary btn-icon" title="Edit" aria-label="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($can_delete): ?>
                                                <button class="btn btn-sm btn-outline-danger btn-icon" 
                                                        onclick="deleteVendor(<?= $vendor['id'] ?>)" title="Delete" aria-label="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if (isset($vendors['pager'])): ?>
                    <div class="d-flex justify-content-center mt-3">
                        <?= $vendors['pager']->links() ?>
                    </div>
                <?php endif ?>
                
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-building fs-1 text-muted"></i>
                    <h5 class="mt-3 text-muted">No Vendors Found</h5>
                    <p class="text-muted">No vendors match your current filters.</p>
                    <?php if ($can_create): ?>
                        <a href="<?= base_url('vendors/create') ?>" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-2"></i>Add First Vendor
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif ?>
        </div>
    </div>
</div>

<script>
function deleteVendor(vendorId) {
    if (confirm('Are you sure you want to delete this vendor?')) {
        // Implement delete functionality
        fetch(`<?= base_url('vendors/') ?>${vendorId}`, {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error deleting vendor: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error deleting vendor');
        });
    }
}
</script>

<?= $this->endSection() ?>
