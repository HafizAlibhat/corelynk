<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="bi bi-building me-2"></i>
                <?= isset($vendor) && $vendor ? 'Edit Vendor' : 'Create New Vendor' ?>
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= base_url('vendors') ?>">Vendors</a></li>
                    <li class="breadcrumb-item active"><?= isset($vendor) && $vendor ? 'Edit' : 'Create' ?></li>
                </ol>
            </nav>
        </div>
        <a href="<?= base_url('vendors') ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Vendors
        </a>
    </div>

    <!-- Form Card -->
    <div class="card shadow">
        <div class="card-header">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="bi bi-plus-circle me-2"></i>Vendor Information
            </h6>
        </div>
        <div class="card-body">
            <!-- Validation Messages -->
            <?php if (session()->has('validation')): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach (session('validation') as $error): ?>
                            <li><?= esc($error) ?></li>
                        <?php endforeach ?>
                    </ul>
                </div>
            <?php endif ?>

            <!-- Success Message -->
            <?php if (session()->has('success')): ?>
                <div class="alert alert-success">
                    <?= session('success') ?>
                </div>
            <?php endif ?>

            <!-- Form -->
            <?php if (isset($vendor) && $vendor):
                $vendorIdentifier = entityRouteIdentifier($vendor);
            ?>
                <?= form_open(base_url('vendors/update/' . $vendorIdentifier), ['class' => 'needs-validation', 'novalidate' => '']) ?>
            <?php else: ?>
                <?= form_open(base_url('vendors/store'), ['class' => 'needs-validation', 'novalidate' => '']) ?>
            <?php endif ?>
            
            <?php if (isset($vendor) && $vendor): ?>
                <input type="hidden" name="id" value="<?= $vendor['id'] ?>">
            <?php endif ?>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="vendor_code_preview" class="form-label">Vendor Code</label>
                    <input type="text"
                           class="form-control"
                           id="vendor_code_preview"
                           value="<?= esc(old('vendor_code', $vendor['vendor_code'] ?? ($next_vendor_code ?? ''))) ?>"
                           readonly>
                    <div class="form-text">
                        <?= isset($vendor) && $vendor ? 'Auto-generated at creation.' : 'System-generated from next available vendor number.' ?>
                    </div>
                    <?php if (!isset($vendor) || !$vendor): ?>
                        <div class="small text-muted mt-1">
                            Next available number: <strong><?= esc((string)($next_vendor_number ?? '')) ?></strong>
                            <?php if (!empty($vendor_code_prefix ?? '')): ?>
                                &nbsp;|&nbsp; Prefix: <strong><?= esc((string)$vendor_code_prefix) ?></strong>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Vendor Name -->
                <div class="col-md-8 mb-3">
                    <label for="name" class="form-label">
                        Vendor Name <span class="text-danger">*</span>
                    </label>
                    <input type="text" 
                           class="form-control" 
                           id="name" 
                           name="name" 
                           value="<?= old('name', $vendor['name'] ?? '') ?>" 
                           required>
                    <div class="invalid-feedback">
                        Please provide a vendor name.
                    </div>
                </div>

                <!-- Contact Person -->
                <div class="col-md-6 mb-3">
                    <label for="contact_person" class="form-label">Contact Person</label>
                    <input type="text" 
                           class="form-control" 
                           id="contact_person" 
                           name="contact_person" 
                           value="<?= old('contact_person', $vendor['contact_person'] ?? '') ?>">
                </div>

                <!-- Email -->
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" 
                           class="form-control" 
                           id="email" 
                           name="email" 
                           value="<?= old('email', $vendor['email'] ?? '') ?>">
                    <div class="invalid-feedback">
                        Please provide a valid email address.
                    </div>
                </div>

                <!-- Phone -->
                <div class="col-md-6 mb-3">
                    <label for="phone" class="form-label">Phone</label>
                    <input type="text" 
                           class="form-control" 
                           id="phone" 
                           name="phone" 
                           value="<?= old('phone', $vendor['phone'] ?? '') ?>">
                </div>

                <!-- Address -->
                <div class="col-12 mb-3">
                    <label for="address" class="form-label">Address</label>
                    <textarea class="form-control" 
                              id="address" 
                              name="address" 
                              rows="3"><?= old('address', $vendor['address'] ?? '') ?></textarea>
                </div>

                <!-- Active Status -->
                <div class="col-12 mb-3">
                    <div class="form-check">
                        <input class="form-check-input" 
                               type="checkbox" 
                               id="is_active" 
                               name="is_active" 
                               value="1" 
                               <?= old('is_active', $vendor['is_active'] ?? true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">
                            Active Vendor
                        </label>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="d-flex justify-content-between">
                <a href="<?= base_url('vendors') ?>" class="btn btn-secondary">
                    <i class="bi bi-x-circle me-1"></i>Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle me-1"></i>
                    <?= isset($vendor) && $vendor ? 'Update Vendor' : 'Create Vendor' ?>
                </button>
            </div>

            <?= form_close() ?>
        </div>
    </div>
</div>

<script>
// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();
</script>

<?= $this->endSection() ?>
