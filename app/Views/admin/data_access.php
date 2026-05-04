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
    </div>

    <div class="d-flex justify-content-end gap-2 mt-4">
        <a href="<?= base_url('admin/roles') ?>" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Data Access</button>
    </div>
    <?= form_close() ?>
</div>
<?= $this->endSection() ?>
