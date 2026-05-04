<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">
                <i class="bi bi-collection me-2"></i>
                <?= isset($category) && $category ? 'Edit Process Category' : 'Create Process Category' ?>
            </h2>
            <a href="<?= base_url('/process-categories') ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>
                Back to Categories
            </a>
        </div>
    </div>
</div>

<?php if (session()->getFlashdata('validation')): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach (session()->getFlashdata('validation') as $error): ?>
                <li><?= esc($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?= form_open(isset($category) && $category ? base_url('/process-categories/' . $category['id'] . '/update') : base_url('/process-categories/store'), 
    ['class' => 'needs-validation', 'novalidate' => true, 'id' => 'categoryForm']) ?>

<div class="row">
    <!-- Main Information -->
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-info-circle me-2"></i>
                    Category Information
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="name" class="form-label">Category Name <span class="text-danger">*</span></label>
                    <input type="text" 
                           class="form-control" 
                           id="name" 
                           name="name" 
                           value="<?= old('name', $category['name'] ?? '') ?>" 
                           required 
                           maxlength="100"
                           placeholder="Enter category name (e.g., Manufacturing, Quality Control)">
                    <div class="invalid-feedback">
                        Please provide a valid category name.
                    </div>
                    <div class="form-text">
                        The name should be unique and descriptive (2-100 characters).
                    </div>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" 
                              id="description" 
                              name="description" 
                              rows="4"
                              maxlength="500"
                              placeholder="Describe this category and what processes it contains..."><?= old('description', $category['description'] ?? '') ?></textarea>
                    <div class="form-text">
                        Optional description to help users understand this category (max 500 characters).
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" 
                               type="checkbox" 
                               id="is_active" 
                               name="is_active" 
                               value="1"
                               <?= old('is_active', $category['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">
                            <strong>Active Category</strong>
                        </label>
                        <div class="form-text">
                            Only active categories will be available for process assignment.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <a href="<?= base_url('/process-categories') ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle me-2"></i>
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-2"></i>
                        <?= isset($category) && $category ? 'Update Category' : 'Create Category' ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar Information -->
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light">
                <h6 class="card-title mb-0">
                    <i class="bi bi-info-circle me-2"></i>
                    Category Guidelines
                </h6>
            </div>
            <div class="card-body">
                <div class="small">
                    <div class="mb-3">
                        <strong>Common Categories:</strong>
                        <ul class="mt-1 mb-0">
                            <li>Manufacturing & Production</li>
                            <li>Quality Control & Testing</li>
                            <li>Assembly & Integration</li>
                            <li>Packaging & Shipping</li>
                            <li>Setup & Preparation</li>
                            <li>Finishing & Coating</li>
                        </ul>
                    </div>

                    <div class="mb-3">
                        <strong>Best Practices:</strong>
                        <ul class="mt-1 mb-0">
                            <li>Use descriptive, industry-standard names</li>
                            <li>Group similar process types together</li>
                            <li>Keep category names concise</li>
                            <li>Consider your workflow sequence</li>
                        </ul>
                    </div>

                    <div class="mb-0">
                        <strong>Status Information:</strong>
                        <ul class="mt-1 mb-0">
                            <li><strong>Active:</strong> Available for new processes</li>
                            <li><strong>Inactive:</strong> Hidden from process creation</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isset($category) && $category): ?>
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-light">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-graph-up me-2"></i>
                        Usage Statistics
                    </h6>
                </div>
                <div class="card-body">
                    <div class="small">
                        <div class="mb-2">
                            <strong>Processes:</strong><br>
                            <span class="badge bg-primary"><?= $category['process_count'] ?? 0 ?> processes</span>
                        </div>
                        <div class="mb-2">
                            <strong>Templates:</strong><br>
                            <span class="badge bg-success"><?= $category['template_count'] ?? 0 ?> templates</span>
                        </div>
                        <div class="mb-2">
                            <strong>Status:</strong><br>
                            <span class="badge bg-<?= $category['is_active'] ? 'success' : 'secondary' ?>">
                                <?= $category['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?= form_close() ?>

<!-- Form Validation Script -->
<script>
// Bootstrap form validation
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

// Character counter for description
document.getElementById('description').addEventListener('input', function(e) {
    const current = e.target.value.length;
    const max = 500;
    let counter = document.querySelector('.char-counter');
    
    if (!counter) {
        const counterDiv = document.createElement('div');
        counterDiv.className = 'char-counter form-text text-end';
        e.target.parentNode.appendChild(counterDiv);
        counter = counterDiv;
    }
    
    counter.textContent = `${current}/${max} characters`;
    
    if (current > max * 0.9) {
        counter.classList.add('text-warning');
    } else {
        counter.classList.remove('text-warning');
    }
});
</script>

<?= $this->endSection() ?>
