<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="container-fluid">
    <?php $productIdentifier = entityRouteIdentifier($product); ?>
    <style>
        .variant-thumb {
            width: 56px;
            height: 56px;
            border-radius: 6px;
            border: 1px solid #2b3444;
            background: #0f172a;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            font-size: 1.1rem;
        }
        .variant-thumb img {
            width: 56px;
            height: 56px;
            object-fit: cover;
            border-radius: 6px;
        }
    </style>
    <div class="row">
        <div class="col-12">
            <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1"><?= esc($product['name']) ?></h1>
                    <?php $isVariable = isset($product['product_type']) && $product['product_type'] === 'variable'; ?>
                    <p class="text-muted mb-0">
                        Product Code:
                        <strong>
                            <?= $isVariable ? '— (Template product)' : esc($product['code'] ?? '—') ?>
                        </strong>
                    </p>
                </div>
                <div>
                    <a href="<?= base_url('product-variants') ?>" class="btn btn-outline-dark">
                        <i class="bi bi-grid-3x3-gap"></i> All Variants
                    </a>
                    <?php
                        $pvCount = 0;
                        try {
                            $dbTmp = \Config\Database::connect();
                            $pvCount = (int) $dbTmp->table('product_variants')->where('product_id', $product['id'])->countAllResults();
                        } catch (\Throwable $e) { $pvCount = 0; }
                    ?>
                    <a href="<?= base_url('product-variants?product_id=' . $product['id']) ?>" class="btn btn-outline-primary">
                        <i class="bi bi-list"></i> View Variants (<?= $pvCount ?>)
                    </a>
                    <a href="<?= base_url('products') ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Products
                    </a>
                    <a href="<?= base_url('products/' . $productIdentifier . '/processes') ?>" class="btn btn-outline-info">
                        <i class="bi bi-gear-wide-connected"></i> Processes
                    </a>
                    <?php if ($can_edit): ?>
                        <a href="<?= base_url('products/' . $productIdentifier . '/edit') ?>" class="btn btn-primary">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                    <?php endif; ?>
                    <?php if ($can_delete): ?>
                        <button type="button" class="btn btn-danger" onclick="confirmDelete(<?= $product['id'] ?>)">
                            <i class="bi bi-trash"></i> Delete
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php $activeTab = $active_tab ?? 'overview'; ?>
            <ul class="nav nav-tabs mb-3" id="productMainTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'overview' ? 'active' : '' ?>" id="tab-overview-btn" data-bs-toggle="tab" data-bs-target="#tab-overview" type="button" role="tab" aria-controls="tab-overview" aria-selected="<?= $activeTab === 'overview' ? 'true' : 'false' ?>">Overview</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'preparation' ? 'active' : '' ?>" id="tab-preparation-btn" data-bs-toggle="tab" data-bs-target="#tab-preparation" type="button" role="tab" aria-controls="tab-preparation" aria-selected="<?= $activeTab === 'preparation' ? 'true' : 'false' ?>">Preparation</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'assets' ? 'active' : '' ?>" id="tab-assets-btn" data-bs-toggle="tab" data-bs-target="#tab-assets" type="button" role="tab" aria-controls="tab-assets" aria-selected="<?= $activeTab === 'assets' ? 'true' : 'false' ?>">Assets</button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade <?= $activeTab === 'overview' ? 'show active' : '' ?>" id="tab-overview" role="tabpanel" aria-labelledby="tab-overview-btn">
            <div class="row">
                <!-- Product Information -->
                <div class="col-lg-8">
                    <?php $isVariable = isset($product['product_type']) && $product['product_type'] === 'variable'; ?>
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Template Product</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <dl class="row">
                                        <dt class="col-sm-4">Name:</dt>
                                        <dd class="col-sm-8"><?= esc($product['name']) ?></dd>
                                        
                                        <dt class="col-sm-4">Code:</dt>
                                        <dd class="col-sm-8"><code><?= $isVariable ? '— (Template product)' : esc($product['code'] ?? '—') ?></code></dd>
                                        
                                        <dt class="col-sm-4">Category:</dt>
                                        <dd class="col-sm-8">
                                            <?= isset($product['category_name']) ? esc($product['category_name']) : 'Not specified' ?>
                                        </dd>
                                    </dl>
                                </div>
                                <div class="col-md-6">
                                    <dl class="row">
                                        <dt class="col-sm-4">Unit:</dt>
                                        <dd class="col-sm-8"><?= esc($product['unit'] ?? 'Not specified') ?></dd>

                                        <dt class="col-sm-4">Status:</dt>
                                        <dd class="col-sm-8">
                                            <?php if ($product['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                        </dd>

                                        <dt class="col-sm-4">Type:</dt>
                                        <dd class="col-sm-8">
                                            <?php
                                                $dtype = $product['detailed_type'] ?? 'storable';
                                                $dtBadge = match($dtype) {
                                                    'service' => '<span class="badge bg-info text-dark"><i class="bi bi-tools me-1"></i>Service</span>',
                                                    'consumable' => '<span class="badge bg-warning text-dark"><i class="bi bi-box me-1"></i>Consumable</span>',
                                                    default => '<span class="badge bg-primary"><i class="bi bi-box-seam me-1"></i>Storable</span>',
                                                };
                                                echo $dtBadge;
                                                if ($dtype === 'service' && !empty($product['service_policy'])) {
                                                    $policyLabel = $product['service_policy'] === 'delivered_qty' ? 'Invoice on Delivery' : 'Invoice on Order';
                                                    echo ' <small class="text-muted ms-1">' . $policyLabel . '</small>';
                                                }
                                            ?>
                                        </dd>
                                        
                                        <dt class="col-sm-4">Created:</dt>
                                        <dd class="col-sm-8"><?= date('M j, Y g:i A', strtotime($product['created_at'])) ?></dd>
                                    </dl>
                                </div>
                            </div>
                            
                            <?php if (!empty($product['description'])): ?>
                                <div class="mt-3">
                                    <h6>Description:</h6>
                                    <p class="text-muted"><?= nl2br(esc($product['description'])) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Overview and Variants sections (variants shown on separate page) -->
                    <div id="productViewSections" class="border border-top-0 rounded-bottom bg-white shadow-sm p-2">
                        <div id="overviewSection">
                            <!-- Weight & Pricing (compact) -->
                            <div class="card mt-2">
                                <div class="card-body py-2">
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <small class="text-muted">Weight</small>
                                            <div>
                                                <?php if (!empty($product['weight'])): ?>
                                                    <strong><?= esc($product['weight']) ?></strong>
                                                    <small class="text-muted"><?= esc($product['weight_unit'] ?? 'KG') ?></small>
                                                    <?php if (!empty($product['unit'])): ?>
                                                        <div class="text-muted small">Inventory unit: <?= esc($product['unit']) ?></div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Cost</small>
                                            <div>
                                                <?php $cost = $product['cost_price'] ?? ($product['standard_cost'] ?? 0); $costCurr = $product['cost_currency'] ?? ($default_currency ?? 'USD'); ?>
                                                <strong><?= esc(number_format((float)$cost, 2)) ?></strong>
                                                <small class="text-muted"><?= esc($costCurr) ?></small>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Sale</small>
                                            <div>
                                                <?php $sale = $product['sale_price'] ?? ($product['selling_price'] ?? 0); $saleCurr = $product['sale_currency'] ?? ($default_currency ?? 'USD'); ?>
                                                <strong><?= esc(number_format((float)$sale, 2)) ?></strong>
                                                <small class="text-muted"><?= esc($saleCurr) ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card mt-3">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="bi bi-geo-alt me-1"></i>Available Stock by Location</h6>
                                    <span class="badge bg-primary">Total: <?= number_format((float)($stock_total_available ?? 0), 2) ?></span>
                                </div>
                                <div class="card-body py-2">
                                    <?php if (!empty($stock_by_location ?? [])): ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm align-middle mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Warehouse</th>
                                                        <th>Location</th>
                                                        <th class="text-end">Available Qty</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach (($stock_by_location ?? []) as $s): ?>
                                                        <?php $sq = (float)($s['available_qty'] ?? 0); ?>
                                                        <tr>
                                                            <td><?= esc($s['warehouse_name'] ?? 'Unassigned Warehouse') ?></td>
                                                            <td><?= esc($s['location_name'] ?? 'Unassigned Location') ?></td>
                                                            <td class="text-end <?= $sq < 0 ? 'text-danger' : 'text-success' ?> fw-semibold"><?= number_format($sq, 2) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-muted small">No location-wise stock currently available for this product.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div id="variantsSection" class="d-none">
                            <!-- Variants (Odoo-like summary) -->
                            <div class="card mt-2">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">Variants</h5>
                                    <a href="<?= base_url('product-variants?product_id=' . $product['id']) ?>" class="btn btn-sm btn-outline-primary">View All</a>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($variants ?? [])): ?>
                                        <div class="text-muted">No variants found for this product.</div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm align-middle">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Art #</th>
                                                        <th style="width:70px">Image</th>
                                                        <th>Details</th>
                                                        <th class="text-end">Price</th>
                                                        <th class="text-end">Cost</th>
                                                        <th class="text-end">Weight</th>
                                                        <th class="text-end">On Hand</th>
                                                        <th class="text-end">Reserved</th>
                                                        <th class="text-end">Available</th>
                                                        <th class="text-end" style="width:90px"></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach (($variants ?? []) as $v): ?>
                                                        <?php
                                                            $attrMap = [];
                                                            if (!empty($v['attributes'])) {
                                                                $attrMap = is_string($v['attributes']) ? (json_decode($v['attributes'], true) ?? []) : (is_array($v['attributes']) ? $v['attributes'] : []);
                                                            }
                                                            $attrParts = [];
                                                            if (is_array($attrMap)) {
                                                                foreach ($attrMap as $ak => $av) {
                                                                    $attrParts[] = trim((string)$ak) . ': ' . trim((string)$av);
                                                                }
                                                            }
                                                            $attrDisplay = !empty($attrParts) ? implode(' • ', $attrParts) : '—';
                                                            $imgName = $v['image'] ?? '';
                                                            $imgUrl = $imgName ? base_url('uploads/variants/' . $imgName) : '';
                                                            $onHand = (float)($v['on_hand'] ?? 0);
                                                            $reserved = (float)($v['reserved'] ?? 0);
                                                            $available = $onHand - $reserved;
                                                        ?>
                                                        <tr>
                                                            <td><?= esc($v['art_number'] ?? '-') ?></td>
                                                            <td>
                                                                <?php if ($imgUrl): ?>
                                                                    <span class="variant-thumb"><img src="<?= esc($imgUrl) ?>" alt="Variant"></span>
                                                                <?php else: ?>
                                                                    <span class="variant-thumb"><i class="bi bi-image"></i></span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <div class="fw-semibold"><?= esc($product['name'] ?? '') ?></div>
                                                                <div class="small text-muted"><?= esc($attrDisplay) ?></div>
                                                            </td>
                                                            <td class="text-end"><?= $v['price'] !== null && $v['price'] !== '' ? number_format((float)$v['price'], 2) : '-' ?></td>
                                                            <td class="text-end"><?= $v['cost'] !== null && $v['cost'] !== '' ? number_format((float)$v['cost'], 2) : '-' ?></td>
                                                            <td class="text-end">
                                                                <?php if (isset($v['weight']) && $v['weight'] !== ''): ?>
                                                                    <?= number_format((float)$v['weight'], 3) ?> <?= esc($product['weight_unit'] ?? 'KG') ?>
                                                                <?php else: ?>
                                                                    -
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="text-end"><?= number_format($onHand, 2) ?></td>
                                                            <td class="text-end"><?= number_format($reserved, 2) ?></td>
                                                            <td class="text-end <?= $available < 0 ? 'text-danger' : '' ?>"><?= number_format($available, 2) ?></td>
                                                            <td class="text-end">
                                                                <a href="<?= base_url('product-variants/' . $v['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Category / vendor (read-only on view page) -->
                    <div class="card border-0 shadow-sm mt-3">
                        <div class="card-body py-3">
                            <div class="row gy-3">
                                <div class="col-md-6">
                                    <div class="d-flex flex-column h-100">
                                        <div>
                                            <small class="text-muted d-block mb-2"><i class="bi bi-tag me-1"></i>Category</small>
                                            <?php if (!empty($product['category_name'])): ?>
                                                <span class="badge bg-info text-dark px-3 py-2"><?= esc($product['category_name']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted small">Not assigned</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="small text-muted mt-3">To change category, click <strong>Edit</strong>.</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex flex-column h-100">
                                        <div>
                                            <small class="text-muted d-block mb-2"><i class="bi bi-person-badge me-1"></i>Vendor Information</small>
                                            <?php $vendorName = $product['vendor_name'] ?? ($product['vendor']['name'] ?? null); ?>
                                            <?php if (!empty($vendorName)): ?>
                                                <div class="mb-2">
                                                    <span class="badge bg-success px-3 py-2"><?= esc($vendorName) ?></span>
                                                    <?php if (!empty($product['vendor_price'])): ?>
                                                        <div class="small text-muted mt-1">Price: <strong><?= esc(number_format((float)$product['vendor_price'], 2)) ?> <?= esc($product['vendor_currency'] ?? 'USD') ?></strong></div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted small">Not assigned</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="small text-muted mt-3">To change vendor details, click <strong>Edit</strong>.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Related Information -->
                <div class="col-lg-4">
                    <!-- Recent Work Orders -->
                    <?php if (!empty($work_orders)): ?>
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Recent Work Orders</h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($work_orders as $wo): ?>
                                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                        <div>
                                            <h6 class="mb-1"><?= esc($wo['wo_number'] ?? 'WO-' . $wo['id']) ?></h6>
                                            <small class="text-muted">Customer: <?= esc($wo['customer_name'] ?? 'N/A') ?></small><br>
                                            <small class="text-muted"><?= date('M j, Y', strtotime($wo['created_at'])) ?></small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-<?= $wo['status'] === 'completed' ? 'success' : ($wo['status'] === 'in_progress' ? 'warning' : 'secondary') ?>">
                                                <?= ucfirst(str_replace('_', ' ', $wo['status'])) ?>
                                            </span>
                                            <br><small class="text-muted">Qty: <?= esc($wo['quantity_ordered'] ?? 0) ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <div class="text-center mt-3">
                                    <a href="<?= base_url('work-orders?product_id=' . $product['id']) ?>" class="btn btn-sm btn-outline-primary">
                                        View All Work Orders
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Product Images (moved to sidebar for compact layout) -->
                    <?php 
                    $images = !empty($product['images']) ? json_decode($product['images'], true) : [];
                    if (!empty($images)): ?>
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Images</h5>
                            </div>
                            <div class="card-body py-2">
                                <div class="row g-2">
                                    <?php foreach ($images as $image): ?>
                                        <div class="col-6">
                                            <div class="ratio ratio-1x1 rounded overflow-hidden bg-dark">
                                                <img src="<?= base_url('uploads/products/' . $image) ?>" 
                                                     class="w-100 h-100" 
                                                     style="object-fit: cover; cursor: pointer;"
                                                     onclick="openLightbox('<?= base_url('uploads/products/' . $image) ?>')"
                                                     alt="Product Image">
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="text-center mt-2">
                                    <a href="<?= base_url('products/' . $productIdentifier) ?>" class="btn btn-sm btn-outline-secondary">View Gallery</a>
                                </div>
                                <div class="small text-muted mt-3">To upload or change images, click <strong>Edit</strong>.</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card mt-4">
                            <div class="card-body text-center py-3 text-muted">
                                No images available
                            </div>
                            <div class="card-body border-top pt-3">
                                <div class="small text-muted">No image upload allowed on view page. Click <strong>Edit</strong> to add images.</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
                </div>
                <div class="tab-pane fade <?= $activeTab === 'preparation' ? 'show active' : '' ?>" id="tab-preparation" role="tabpanel" aria-labelledby="tab-preparation-btn">
                    <?= view('preparation_profiles/_product_tab', [
                        'product' => $product,
                        'preparation_profiles' => $preparation_profiles ?? [],
                    ]) ?>
                </div>
                <div class="tab-pane fade <?= $activeTab === 'assets' ? 'show active' : '' ?>" id="tab-assets" role="tabpanel" aria-labelledby="tab-assets-btn">
                    <?= view('product_assets/_product_tab', [
                        'productIdentifier' => $productIdentifier,
                    ]) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lightbox Modal -->
<div class="modal fade" id="lightboxModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Product Image</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="lightboxImage" src="" class="img-fluid" style="max-height: 80vh;">
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this product?</p>
                <p class="text-danger"><strong>This action cannot be undone.</strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" style="display: inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit" class="btn btn-danger">Delete Product</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Lightbox functionality
function openLightbox(imageSrc) {
    document.getElementById('lightboxImage').src = imageSrc;
    const lightboxModal = new bootstrap.Modal(document.getElementById('lightboxModal'));
    lightboxModal.show();
}

// Delete confirmation
function confirmDelete(productId) {
    document.getElementById('deleteForm').action = '<?= base_url('products') ?>/' + productId;
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    deleteModal.show();
}

// View Variants button now toggles the variants section
document.querySelectorAll('[data-bs-target="#tab-variants-view"]').forEach(btn => {
    btn.addEventListener('click', function() {
        const tab = document.getElementById('tab-variants-btn');
        if (tab && window.bootstrap && bootstrap.Tab) {
            const instance = bootstrap.Tab.getOrCreateInstance(tab);
            instance.show();
        }
    });
});
</script>

<?= $this->endSection() ?>
