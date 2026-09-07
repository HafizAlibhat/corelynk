<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="container-fluid cl-list-page cl-vendor-list" data-cl-list>

    <!-- Page Header -->
    <div class="cl-list-page-header">
        <div>
            <h2 class="mb-0"><i class="bi bi-truck me-2"></i>Vendors</h2>
            <div class="small text-muted">Manage suppliers, contacts, and purchasing relationships</div>
        </div>
        <?php if ($can_create): ?>
            <div class="cl-list-page-actions">
                <a href="<?= base_url('vendors/create') ?>" class="btn btn-primary cl-vendors-new-btn"><i class="bi bi-plus-circle me-2"></i>New Vendor</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <?= form_open('', ['method' => 'GET', 'class' => 'pl-filter-form cl-list-toolbar cl-vendors-toolbar']) ?>
        <div class="pl-filter-toolbar">
            <div class="pl-filter-toolbar-search cl-list-search-group">
                <div class="pl-filter-search-label">
                    <label for="vendorSearch">Search vendors</label>
                    <span>Press Enter to search</span>
                </div>
                <div class="pl-search-shell">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input type="search"
                           class="form-control pl-filter-control pl-filter-control-search"
                           id="vendorSearch"
                           name="search"
                           value="<?= esc($current_search) ?>"
                           placeholder="Search vendor name, contact, phone, or email">
                </div>
            </div>
            <div class="pl-filter-toolbar-actions">
                <button type="submit" class="btn btn-primary pl-filter-btn pl-filter-btn-primary">
                    <i class="bi bi-search me-1"></i>Search
                </button>
                <a href="<?= base_url('vendors') ?>" class="btn btn-outline-secondary pl-filter-btn pl-filter-btn-soft">Reset</a>
            </div>
        </div>

        <div class="pl-filter-chipbar cl-list-toolbar-controls" aria-label="Vendor filters">
            <div class="pl-chip-field pl-chip-field-md">
                <label class="pl-chip-label" for="vendorStatus">Status</label>
                <select class="form-select pl-filter-control" id="vendorStatus" name="status">
                    <option value="">All Statuses</option>
                    <option value="1" <?= $current_status === '1' ? 'selected' : '' ?>>Active</option>
                    <option value="0" <?= $current_status === '0' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="pl-chip-field pl-chip-field-sm">
                <label class="pl-chip-label" for="vendorPerPage">Per Page</label>
                <select class="form-select pl-filter-control" id="vendorPerPage" name="per_page">
                    <option value="20" <?= $per_page == 20 ? 'selected' : '' ?>>20</option>
                    <option value="50" <?= $per_page == 50 ? 'selected' : '' ?>>50</option>
                    <option value="100" <?= $per_page == 100 ? 'selected' : '' ?>>100</option>
                </select>
            </div>
        </div>
    <?= form_close() ?>

    <!-- Vendors List -->
            <?php if (!empty($vendors['data'])): ?>
                <div class="card border-0 shadow-sm cl-list-table-card">
                <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="cl-table table table-hover pl-table pl-vendors-table data-table-table mb-0">
                        <thead>
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
                                        <span class="cl-count-badge"><?= (int)($vendor['process_count'] ?? 0) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($vendor['is_active']): ?>
                                            <span class="cl-status-badge cl-status-badge--success"><span class="cl-status-badge__dot"></span>Active</span>
                                        <?php else: ?>
                                            <span class="cl-status-badge cl-status-badge--neutral"><span class="cl-status-badge__dot"></span>Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions-col">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="<?= base_url('vendors/' . $vendorIdentifier) ?>" 
                                               class="btn btn-sm btn-outline-primary btn-icon cl-icon-action" title="View" aria-label="View">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if ($can_edit): ?>
                                                <a href="<?= base_url('vendors/' . $vendorIdentifier . '/edit') ?>" 
                                                   class="btn btn-sm btn-outline-secondary btn-icon cl-icon-action" title="Edit" aria-label="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($can_delete): ?>
                                                <button class="btn btn-sm btn-outline-danger btn-icon cl-icon-action"
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
                </div>
                </div>

                <!-- Pagination -->
                <?php if (isset($vendors['pager']) && $vendors['pager']->getPageCount() > 1): ?>
                    <div class="pl-footer justify-content-center">
                        <?= $vendors['pager']->links() ?>
                    </div>
                <?php endif ?>

            <?php else: ?>
                <div class="cl-list-empty">
                    <span class="cl-list-empty__icon" aria-hidden="true"><i class="bi bi-building"></i></span>
                    <h6>No Vendors Found</h6>
                    <p>No vendors match your current filters.</p>
                    <?php if ($can_create): ?>
                        <a href="<?= base_url('vendors/create') ?>" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-2"></i>Add First Vendor
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif ?>
</div><!-- /.cl-list-page -->

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
