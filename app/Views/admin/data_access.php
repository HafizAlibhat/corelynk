<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Data Access: <?= esc($role['name']) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-sliders me-2"></i>Role Data Access Controls</h2>
            <small class="text-muted">Role: <?= esc($role['name']) ?> (<?= esc($role['slug']) ?>)</small>
        </div>
        <a href="<?= base_url('admin/roles') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Roles</a>
    </div>

    <?php if (session()->has('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i><?= session('success') ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if (session()->has('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-2"></i><?= session('error') ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <?= form_open(base_url('admin/roles/' . $role['id'] . '/data-access/save')) ?>
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><strong>Dashboard Visibility</strong></div>
                <div class="card-body">
                    <p class="small text-muted">Control what users with this role can see on the main dashboard.</p>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="dashboard_sales_visible" name="dashboard_sales_visible" value="1" <?= !empty($settings['dashboard_sales_visible']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="dashboard_sales_visible">Show sales widgets/cards</label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="dashboard_purchases_visible" name="dashboard_purchases_visible" value="1" <?= !empty($settings['dashboard_purchases_visible']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="dashboard_purchases_visible">Show purchases/vendor widgets/cards</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="dashboard_finance_visible" name="dashboard_finance_visible" value="1" <?= !empty($settings['dashboard_finance_visible']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="dashboard_finance_visible">Show finance widgets/cards</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><strong>Per-User Data Isolation</strong></div>
                <div class="card-body">
                    <p class="small text-muted">When enabled, users only see records created by themselves for that document type.</p>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="isolate_quotations" name="isolate_quotations" value="1" <?= !empty($settings['isolate_quotations']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isolate_quotations">Isolate Quotations by creator</label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="isolate_sales_orders" name="isolate_sales_orders" value="1" <?= !empty($settings['isolate_sales_orders']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isolate_sales_orders">Isolate Sales Orders by creator</label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="isolate_purchase_orders" name="isolate_purchase_orders" value="1" <?= !empty($settings['isolate_purchase_orders']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isolate_purchase_orders">Isolate Purchase Orders by creator</label>
                    </div>
                </div>
            </div>
    </div>

    <!-- Product Visibility Restrictions -->
    <div class="row g-3 mt-1">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="bi bi-box-seam"></i>
                    <strong>Products Visibility</strong>
                    <span class="badge bg-secondary ms-auto">Optional restrictions</span>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3">Control which product types and categories users with this role can see in the Products list.</p>

                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" id="product_hide_services"
                               name="product_hide_services" value="1"
                               <?= !empty($settings['product_hide_services']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="product_hide_services">
                            <strong>Hide Service products</strong>
                            <span class="d-block text-muted small">Users will not see products with type = Service (e.g. shipping, subcontracting services)</span>
                        </label>
                    </div>

                    <hr>
                    <label class="form-label fw-semibold"><i class="bi bi-tags me-1"></i>Allowed Categories</label>
                    <p class="small text-muted mb-2">
                        Check only the categories this role is allowed to see.
                        <strong>Leave all unchecked to allow all categories</strong> (no restriction).
                    </p>
                    <?php if (!empty($productCategories)): ?>
                    <div class="row g-2">
                        <?php foreach ($productCategories as $cat): ?>
                        <div class="col-sm-6 col-md-4 col-lg-3">
                            <div class="form-check">
                                <input class="form-check-input cat-cb" type="checkbox"
                                       id="cat_<?= (int) $cat['id'] ?>"
                                       name="product_allowed_categories[]"
                                       value="<?= (int) $cat['id'] ?>"
                                       <?= in_array((int) $cat['id'], $savedCategoryIds ?? []) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="cat_<?= (int) $cat['id'] ?>">
                                    <?= esc($cat['name']) ?>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-2 d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.querySelectorAll('.cat-cb').forEach(c=>c.checked=true)">
                            <i class="bi bi-check-all me-1"></i>Select All
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.querySelectorAll('.cat-cb').forEach(c=>c.checked=false)">
                            <i class="bi bi-x me-1"></i>Clear All (no restriction)
                        </button>
                    </div>
                    <?php else: ?>
                    <p class="text-muted small">No product categories found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-4">
        <a href="<?= base_url('admin/roles') ?>" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Data Access</button>
    </div>
    <?= form_close() ?>
</div>
<?= $this->endSection() ?>
