<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0"><?= $page_title ?></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="<?= base_url('/') ?>">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= base_url('/work-orders') ?>">Work Orders</a></li>
                    <li class="breadcrumb-item active"><?= isset($work_order) && $work_order ? 'Edit' : 'Create' ?></li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-plus-circle mr-2"></i>
                            Work Order Information
                        </h3>
                        <div class="card-tools">
                            <a href="<?= base_url('/work-orders') ?>" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left mr-1"></i> Back to List
                            </a>
                        </div>
                    </div>

                    <?= form_open(isset($work_order) && $work_order ? '/work-orders/' . $work_order['id'] . '/edit' : '/work-orders/create', [
                        'class' => 'needs-validation',
                        'novalidate' => true
                    ]) ?>
                    <?= csrf_field() ?>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger">
                            <?= esc($error_message) ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="card-body">
                        <div class="row">
                            <!-- Work Order Number -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="wo_number">Work Order Number</label>
                     <input type="text" 
                         class="form-control <?= $validation->hasError('wo_number') ? 'is-invalid' : '' ?>" 
                         id="wo_number" 
                         name="wo_number" 
                         value="<?= old('wo_number', $work_order['wo_number'] ?? '') ?>"
                         placeholder="Leave empty for auto-generation"
                         <?= isset($work_order) && $work_order ? 'disabled' : '' ?>>
                     <?php if (isset($work_order) && $work_order): ?>
                         <input type="hidden" name="wo_number" value="<?= esc($work_order['wo_number']) ?>">
                     <?php endif; ?>
                                    <?php if ($validation->hasError('wo_number')): ?>
                                        <div class="invalid-feedback">
                                            <?= $validation->getError('wo_number') ?>
                                        </div>
                                    <?php endif; ?>
                                    <small class="form-text text-muted">Leave empty for automatic generation or enter custom number</small>
                                </div>
                            </div>

                            <!-- Customer Name -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="customer_name">Customer Name <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           class="form-control <?= $validation->hasError('customer_name') ? 'is-invalid' : '' ?>" 
                                           id="customer_name" 
                                           name="customer_name" 
                                           value="<?= old('customer_name', $work_order['customer_name'] ?? '') ?>"
                                           required>
                                    <?php if ($validation->hasError('customer_name')): ?>
                                        <div class="invalid-feedback">
                                            <?= $validation->getError('customer_name') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Products Section -->
                            <div class="col-12">
                                <div class="form-group">
                                    <label>Products <span class="text-danger">*</span></label>
                                    <div id="products-container">
                                        <?php if (isset($work_order) && !empty($work_order['items'])): ?>
                                            <?php foreach ($work_order['items'] as $index => $item): ?>
                                                <div class="product-row border p-3 mb-3 rounded">
                                                    <div class="row">
                                                        <div class="col-md-8">
                                                            <label for="products[<?= $index ?>][product_id]">Product</label>
                                                            <select class="form-control product-select" 
                                                                    name="products[<?= $index ?>][product_id]" 
                                                                    required>
                                                                <option value="">Select Product</option>
                                                                <?php foreach ($products as $product): ?>
                                                                    <option value="<?= $product['id'] ?>" 
                                                                            <?= $product['id'] == $item['product_id'] ? 'selected' : '' ?>>
                                                                        <?= esc($product['name']) ?> (<?= esc($product['code']) ?>)
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label for="products[<?= $index ?>][quantity]">Quantity</label>
                                                            <input type="number" 
                                                                   class="form-control" 
                                                                   name="products[<?= $index ?>][quantity]" 
                                                                   value="<?= $item['quantity_ordered'] ?>"
                                                                   min="1" 
                                                                   required>
                                                        </div>
                                                        <div class="col-md-1 d-flex align-items-end">
                                                            <button type="button" class="btn btn-danger btn-sm remove-product" style="<?= count($work_order['items']) > 1 ? '' : 'display: none;' ?>">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="product-row border p-3 mb-3 rounded">
                                                <div class="row">
                                                    <div class="col-md-8">
                                                        <label for="products[0][product_id]">Product</label>
                                                        <select class="form-control product-select" 
                                                                name="products[0][product_id]" 
                                                                required>
                                                            <option value="">Select Product</option>
                                                            <?php foreach ($products as $product): ?>
                                                                <option value="<?= $product['id'] ?>">
                                                                    <?= esc($product['name']) ?> (<?= esc($product['code']) ?>)
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label for="products[0][quantity]">Quantity</label>
                                                        <input type="number" 
                                                               class="form-control" 
                                                               name="products[0][quantity]" 
                                                               min="1" 
                                                               required>
                                                    </div>
                                                    <div class="col-md-1 d-flex align-items-end">
                                                        <button type="button" class="btn btn-danger btn-sm remove-product" style="display: none;">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" class="btn btn-secondary btn-sm" id="add-product">
                                        <i class="bi bi-plus"></i> Add Another Product
                                    </button>
                                </div>
                            </div>
                        </div>



                        <div class="row">
                            <!-- Due Date -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="due_date">Due Date <span class="text-danger">*</span></label>
                                    <input type="date" 
                                           class="form-control <?= $validation->hasError('due_date') ? 'is-invalid' : '' ?>" 
                                           id="due_date" 
                                           name="due_date" 
                                           value="<?= old('due_date', $work_order['due_date'] ?? '') ?>"
                                           min="<?= date('Y-m-d') ?>"
                                           required>
                                    <?php if ($validation->hasError('due_date')): ?>
                                        <div class="invalid-feedback">
                                            <?= $validation->getError('due_date') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Priority -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="priority">Priority <span class="text-danger">*</span></label>
                                    <select class="form-control <?= $validation->hasError('priority') ? 'is-invalid' : '' ?>" 
                                            id="priority" 
                                            name="priority" 
                                            required>
                                        <option value="">Select Priority</option>
                                        <option value="low" <?= old('priority', $work_order['priority'] ?? '') == 'low' ? 'selected' : '' ?>>Low</option>
                                        <option value="normal" <?= old('priority', $work_order['priority'] ?? '') == 'normal' ? 'selected' : '' ?>>Normal</option>
                                        <option value="high" <?= old('priority', $work_order['priority'] ?? '') == 'high' ? 'selected' : '' ?>>High</option>
                                        <option value="urgent" <?= old('priority', $work_order['priority'] ?? '') == 'urgent' ? 'selected' : '' ?>>Urgent</option>
                                    </select>
                                    <?php if ($validation->hasError('priority')): ?>
                                        <div class="invalid-feedback">
                                            <?= $validation->getError('priority') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="row">
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="notes">Notes</label>
                                    <textarea class="form-control" 
                                              id="notes" 
                                              name="notes" 
                                              rows="3"
                                              placeholder="Additional notes or instructions..."><?= old('notes', $work_order['notes'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer">
                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save mr-1"></i>
                                    <?= isset($work_order) && $work_order ? 'Update Work Order' : 'Create Work Order' ?>
                                </button>
                                <a href="<?= base_url('/work-orders') ?>" class="btn btn-secondary ml-2">
                                    <i class="fas fa-times mr-1"></i> Cancel
                                </a>
                            </div>
                        </div>
                    </div>

                    <?= form_close() ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let productIndex = <?= isset($work_order) && !empty($work_order['items']) ? count($work_order['items']) : 1 ?>;
    
    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // Priority color coding
    const prioritySelect = document.getElementById('priority');
    prioritySelect.addEventListener('change', function() {
        this.className = 'form-control';
        switch(this.value) {
            case 'urgent':
                this.classList.add('border-danger');
                break;
            case 'high':
                this.classList.add('border-warning');
                break;
            case 'normal':
                this.classList.add('border-info');
                break;
            case 'low':
                this.classList.add('border-success');
                break;
        }
    });

    // Trigger initial priority color
    prioritySelect.dispatchEvent(new Event('change'));

    // Add product functionality
    document.getElementById('add-product').addEventListener('click', function() {
        const container = document.getElementById('products-container');
        const productOptions = `<?php foreach ($products as $product): ?>
                                    <option value="<?= $product['id'] ?>">
                                        <?= esc($product['name']) ?> (<?= esc($product['code']) ?>)
                                    </option>
                                <?php endforeach; ?>`;
        
        const newProductRow = document.createElement('div');
        newProductRow.className = 'product-row border p-3 mb-3 rounded';
        newProductRow.innerHTML = `
            <div class="row">
                <div class="col-md-8">
                    <label for="products[${productIndex}][product_id]">Product</label>
                    <select class="form-control product-select" 
                            name="products[${productIndex}][product_id]" 
                            required>
                        <option value="">Select Product</option>
                        ${productOptions}
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="products[${productIndex}][quantity]">Quantity</label>
                    <input type="number" 
                           class="form-control" 
                           name="products[${productIndex}][quantity]" 
                           min="1" 
                           required>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="button" class="btn btn-danger btn-sm remove-product">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        `;
        
        container.appendChild(newProductRow);
        productIndex++;
        updateRemoveButtons();
    });

    // Remove product functionality
    document.addEventListener('click', function(e) {
        if (e.target.closest('.remove-product')) {
            e.target.closest('.product-row').remove();
            updateRemoveButtons();
        }
    });

    // Update remove button visibility
    function updateRemoveButtons() {
        const productRows = document.querySelectorAll('.product-row');
        productRows.forEach((row, index) => {
            const removeButton = row.querySelector('.remove-product');
            if (productRows.length > 1) {
                removeButton.style.display = 'block';
            } else {
                removeButton.style.display = 'none';
            }
        });
    }

    // Initial call to set up remove buttons
    updateRemoveButtons();
});
</script>

<?= $this->endSection() ?>
