<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="bi bi-building me-2"></i>
                Vendors Management
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Dashboard</a></li>
                    <li class="breadcrumb-item active">Vendors</li>
                </ol>
            </nav>
        </div>
        <?php if ($can_create): ?>
            <a href="<?= base_url('vendors/create') ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>New Vendor
            </a>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="card shadow mb-4">
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
    <div class="card shadow">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="bi bi-building me-2"></i>Vendors & Suppliers
            </h6>
        </div>
        <div class="card-body">
            <?php if (!empty($vendors['data'])): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Vendor Name</th>
                                <th>Contact Person</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Processes</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vendors['data'] as $vendor): ?>
                                <tr>
                                    <td>
                                        <strong><?= esc($vendor['name']) ?></strong>
                                        <?php if (!empty($vendor['address'])): ?>
                                            <br><small class="text-muted"><?= esc($vendor['address']) ?></small>
                                        <?php endif ?>
                                    </td>
                                    <td><?= esc($vendor['contact_person'] ?? '-') ?></td>
                                    <td><?= esc($vendor['phone'] ?? '-') ?></td>
                                    <td><?= esc($vendor['email'] ?? '-') ?></td>
                                    <td>
                                        <span class="badge bg-info"><?= $vendor['process_count'] ?? 0 ?></span>
                                    </td>
                                    <td>
                                        <?php if ($vendor['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif ?>
                                    </td>
                                    <td>
                                        <small><?= date('M d, Y', strtotime($vendor['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="<?= base_url('vendors/' . $vendor['id']) ?>" 
                                               class="btn btn-sm btn-outline-primary" 
                                               title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if ($can_edit): ?>
                                                <a href="<?= base_url('vendors/' . $vendor['id'] . '/edit') ?>" 
                                                   class="btn btn-sm btn-outline-secondary" 
                                                   title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($can_delete): ?>
                                                <button class="btn btn-sm btn-outline-danger" 
                                                        onclick="deleteVendor(<?= $vendor['id'] ?>)" 
                                                        title="Delete">
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
