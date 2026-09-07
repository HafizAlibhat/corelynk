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
        /* Compact hero header: title | actions | image, image always pinned right */
        .product-hero {
            display: flex !important;
            align-items: center;
            gap: 16px;
        }
        .product-hero__main {
            flex: 1 1 auto;
            min-width: 0;
        }
        .product-hero__main .section-title {
            font-size: clamp(1.15rem, 1.6vw, 1.45rem);
            line-height: 1.25;
            overflow-wrap: anywhere;
        }
        .product-hero__meta {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 4px;
            font-size: 0.82rem;
        }
        .product-hero__actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }
        .product-hero-thumb {
            width: 64px;
            height: 64px;
            object-fit: cover;
            border-radius: 12px;
            flex-shrink: 0;
            border: 1px solid var(--cl-color-border, #e5e7eb);
        }
        img.product-hero-thumb { cursor: pointer; }
        .product-hero-thumb--empty {
            display: grid;
            place-items: center;
            font-size: 1.5rem;
            color: var(--cl-color-text-muted, #8a8e99);
            background: var(--cl-color-surface-2, rgba(127,127,127,.06));
        }
        @media (max-width: 767.98px) {
            .product-hero {
                display: grid !important;
                grid-template-columns: 1fr auto;
                align-items: start;
            }
            .product-hero__main { grid-column: 1; grid-row: 1; }
            .product-hero-thumb  { grid-column: 2; grid-row: 1; }
            .product-hero__actions { grid-column: 1 / -1; grid-row: 2; }
        }

        /* Flat Odoo/SAP-style field groups */
        .field-panel {
            background: var(--cl-color-surface-2, rgba(127,127,127,.05));
            border-radius: 12px;
            padding: 16px 20px;
        }
        .field-panel + .field-panel { margin-top: 14px; }
        .field-panel__title {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--cl-color-text-muted, #8a8e99);
            margin-bottom: 12px;
        }
        .field-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            column-gap: 32px;
            row-gap: 12px;
        }
        .field-item .field-label {
            display: block;
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--cl-color-text-muted, #8a8e99);
            margin-bottom: 2px;
        }
        .field-item .field-value {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--cl-color-text-primary, inherit);
        }
        @media (max-width: 575.98px) {
            .field-grid { grid-template-columns: 1fr; }
        }

        /* Quick actions list (sidebar) */
        .quick-action-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            padding: 9px 12px;
            border-radius: 10px;
            border: 1px solid var(--cl-color-border, #e5e7eb);
            background: var(--cl-color-surface-1, #fff);
            color: var(--cl-color-text-primary, inherit);
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            margin-bottom: 8px;
            transition: background-color .15s ease, border-color .15s ease;
        }
        .quick-action-btn:last-child { margin-bottom: 0; }
        .quick-action-btn:hover {
            background: var(--cl-color-brand-50, #edf8ff);
            border-color: var(--cl-color-brand-200, #b9e2ff);
            color: inherit;
        }
        .quick-action-btn .quick-action-icon {
            display: grid;
            place-items: center;
            width: 30px;
            height: 30px;
            border-radius: 8px;
            background: var(--cl-color-brand-50, #edf8ff);
            color: var(--cl-color-brand-600, #168de3);
            flex-shrink: 0;
        }
        .quick-action-btn.danger .quick-action-icon {
            background: var(--cl-color-danger-soft, #ffe2e5);
            color: var(--cl-color-danger, #ef5b6b);
        }

        /* ERP metric strip — hairline-divided KPI row */
        .metric-strip {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1px;
            background: var(--cl-color-border, #e5e7eb);
            border: 1px solid var(--cl-color-border, #e5e7eb);
            border-radius: 14px;
            overflow: hidden;
        }
        .metric-strip__item {
            background: var(--cl-color-surface-1, #fff);
            padding: 14px 18px;
        }
        .metric-strip__label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--cl-color-text-muted, #8a8e99);
            margin-bottom: 6px;
        }
        .metric-strip__value {
            font-size: 1.35rem;
            font-weight: 700;
            line-height: 1.15;
            letter-spacing: -0.02em;
            color: var(--cl-color-text-primary, inherit);
        }
        .metric-strip__value small {
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--cl-color-text-muted, #8a8e99);
        }

        /* Modern underline tabs */
        #productMainTabs {
            border-bottom: 1px solid var(--cl-color-border, #e5e7eb);
            gap: 2px;
        }
        #productMainTabs .nav-link {
            border: 0;
            border-bottom: 2px solid transparent;
            border-radius: 0;
            padding: 10px 18px;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--cl-color-text-muted, #8a8e99);
            background: transparent;
        }
        #productMainTabs .nav-link:hover {
            color: var(--cl-color-brand-600, #168de3);
        }
        #productMainTabs .nav-link.active {
            color: var(--cl-color-brand-600, #168de3);
            border-bottom-color: var(--cl-color-brand-600, #168de3);
            background: transparent;
        }

        /* Stock table share bars */
        .stock-bar {
            height: 5px;
            border-radius: 999px;
            background: var(--cl-color-surface-3, #eee);
            overflow: hidden;
            margin-top: 5px;
            min-width: 90px;
        }
        .stock-bar__fill {
            display: block;
            height: 100%;
            border-radius: 999px;
            background: var(--cl-color-success, #249e55);
        }

        .empty-state {
            padding: 28px 16px;
            text-align: center;
            color: var(--cl-color-text-muted, #8a8e99);
        }
        .empty-state i {
            display: block;
            font-size: 1.75rem;
            opacity: .45;
            margin-bottom: 8px;
        }

        .spec-table th,
        .spec-table td {
            padding: 0.55rem 0.9rem;
        }
        .spec-table th {
            width: 160px;
            background: var(--cl-color-surface-2, rgba(127,127,127,.06));
            color: var(--cl-color-text-secondary, inherit);
            font-weight: 600;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            white-space: nowrap;
        }
        .spec-table td {
            font-weight: 500;
        }
    </style>
    <div class="row">
        <div class="col-12">
            <!-- Header -->
            <div class="card cl-detail-page mb-4">
                <?php
                    $isVariable   = isset($product['product_type']) && $product['product_type'] === 'variable';
                    $images       = !empty($product['images']) ? json_decode($product['images'], true) : [];
                    $primaryImage = !empty($images[0]) ? base_url('uploads/products/' . $images[0]) : null;

                    $pvCount = 0;
                    try {
                        $dbTmp = \Config\Database::connect();
                        $pvCount = (int) $dbTmp->table('product_variants')->where('product_id', $product['id'])->countAllResults();
                    } catch (\Throwable $e) { $pvCount = 0; }
                ?>
                <div class="card-header section-header product-hero">
                    <div class="product-hero__main">
                        <h3 class="section-title mb-0"><?= esc($product['name']) ?></h3>
                        <div class="product-hero__meta">
                            <?php if ($product['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Inactive</span>
                            <?php endif; ?>
                            <span class="text-muted">Code: <strong><?= $isVariable ? '— (Template)' : esc($product['code'] ?? '—') ?></strong></span>
                        </div>
                    </div>

                    <div class="product-hero__actions">
                        <?= view('partials/record_nav', [
                            'table'   => 'products',
                            'id'      => $productIdentifier,
                            'pattern' => 'products/{id}',
                            'list'    => 'products',
                        ]) ?>
                        <?php if ($can_edit): ?>
                            <a href="<?= base_url('products/' . $productIdentifier . '/edit') ?>" class="btn btn-sm btn-primary">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                        <?php endif; ?>
                        <a href="<?= base_url('products') ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
                    </div>

                    <?php if ($primaryImage): ?>
                        <img src="<?= esc($primaryImage) ?>" alt="<?= esc($product['name']) ?>"
                             class="product-hero-thumb"
                             onclick="openLightbox('<?= esc($primaryImage) ?>')">
                    <?php else: ?>
                        <div class="product-hero-thumb product-hero-thumb--empty">
                            <i class="bi bi-box-seam"></i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php
                $canViewSensitiveOverview = !empty($can_view_sensitive_overview);
                $cost     = $product['cost_price'] ?? ($product['standard_cost'] ?? 0);
                $costCurr = $product['cost_currency'] ?? ($default_currency ?? 'USD');
                $sale     = $product['sale_price'] ?? ($product['selling_price'] ?? 0);
                $saleCurr = $product['sale_currency'] ?? ($default_currency ?? 'USD');
            ?>
            <?php if ($canViewSensitiveOverview): ?>
                <div class="metric-strip mb-4">
                    <div class="metric-strip__item">
                        <div class="metric-strip__label"><i class="bi bi-boxes"></i>Available Stock</div>
                        <div class="metric-strip__value"><?= number_format((float)($stock_total_available ?? 0), 2) ?></div>
                    </div>
                    <div class="metric-strip__item">
                        <div class="metric-strip__label"><i class="bi bi-graph-up-arrow"></i>Units Sold</div>
                        <div class="metric-strip__value text-success"><?= number_format((float)($sales_units_total ?? 0), 2) ?></div>
                    </div>
                    <div class="metric-strip__item">
                        <div class="metric-strip__label"><i class="bi bi-truck"></i>Units Purchased</div>
                        <div class="metric-strip__value text-info"><?= number_format((float)($purchased_units_total ?? 0), 2) ?></div>
                    </div>
                    <div class="metric-strip__item">
                        <div class="metric-strip__label"><i class="bi bi-tag"></i>Cost Price</div>
                        <div class="metric-strip__value"><?= number_format((float)$cost, 2) ?> <small><?= esc($costCurr) ?></small></div>
                    </div>
                    <div class="metric-strip__item">
                        <div class="metric-strip__label"><i class="bi bi-cash-coin"></i>Sale Price</div>
                        <div class="metric-strip__value"><?= number_format((float)$sale, 2) ?> <small><?= esc($saleCurr) ?></small></div>
                    </div>
                </div>
            <?php endif; ?>

            <?php $activeTab = $active_tab ?? 'overview'; ?>
            <ul class="nav nav-tabs mb-3" id="productMainTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'overview' ? 'active' : '' ?>" id="tab-overview-btn" data-bs-toggle="tab" data-bs-target="#tab-overview" type="button" role="tab" aria-controls="tab-overview" aria-selected="<?= $activeTab === 'overview' ? 'true' : 'false' ?>">Overview</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'preparation' ? 'active' : '' ?>" id="tab-preparation-btn" data-bs-toggle="tab" data-bs-target="#tab-preparation" type="button" role="tab" aria-controls="tab-preparation" aria-selected="<?= $activeTab === 'preparation' ? 'true' : 'false' ?>">Preparation</button>
                </li>
                <?php if (!empty($can_view_assets)): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'assets' ? 'active' : '' ?>" id="tab-assets-btn" data-bs-toggle="tab" data-bs-target="#tab-assets" type="button" role="tab" aria-controls="tab-assets" aria-selected="<?= $activeTab === 'assets' ? 'true' : 'false' ?>">Assets</button>
                </li>
                <?php endif; ?>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade <?= $activeTab === 'overview' ? 'show active' : '' ?>" id="tab-overview" role="tabpanel" aria-labelledby="tab-overview-btn">
            <div class="row">
                <!-- Product Information -->
                <div class="col-lg-8">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-info-circle me-1"></i>General Information</h6>
                        </div>
                        <div class="card-body">
                                    <div class="field-grid">
                                        <div class="field-item">
                                            <span class="field-label">Unit</span>
                                            <span class="field-value"><?= esc($product['unit'] ?? 'Not specified') ?></span>
                                        </div>
                                        <div class="field-item">
                                            <span class="field-label">Category</span>
                                            <span class="field-value"><?= isset($product['category_name']) ? esc($product['category_name']) : 'Not specified' ?></span>
                                        </div>
                                        <div class="field-item">
                                            <span class="field-label">Type</span>
                                            <span class="field-value">
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
                                            </span>
                                        </div>
                                        <div class="field-item">
                                            <span class="field-label">Created</span>
                                            <span class="field-value"><?= date('M j, Y g:i A', strtotime($product['created_at'])) ?></span>
                                        </div>
                                    </div>

                            <?php if ($canViewSensitiveOverview && !empty($product['description'])): ?>
                                <div class="field-panel mt-3">
                                    <div class="field-panel__title">Description</div>
                                    <p class="mb-0"><?= nl2br(esc($product['description'])) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Overview and Variants sections (variants shown on separate page) -->
                    <div id="productViewSections">
                        <div id="overviewSection">
                            <!-- Logistics / margin details -->
                            <?php if ($canViewSensitiveOverview): ?>
                            <?php
                                $margin = (float)$sale - (float)$cost;
                                $marginPct = (float)$sale > 0 ? ($margin / (float)$sale) * 100 : null;
                            ?>
                            <div class="card mt-3">
                                <div class="card-body">
                                    <div class="field-panel">
                                        <div class="field-panel__title"><i class="bi bi-clipboard-data me-1"></i>Logistics &amp; Margin</div>
                                        <div class="field-grid">
                                            <div class="field-item">
                                                <span class="field-label">Weight</span>
                                                <span class="field-value">
                                                    <?php if (!empty($product['weight'])): ?>
                                                        <?= esc($product['weight']) ?> <small class="text-muted fw-normal"><?= esc($product['weight_unit'] ?? 'KG') ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted fw-normal">Not specified</span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <div class="field-item">
                                                <span class="field-label">Inventory Unit</span>
                                                <span class="field-value"><?= !empty($product['unit']) ? esc($product['unit']) : '<span class="text-muted fw-normal">Not specified</span>' ?></span>
                                            </div>
                                            <div class="field-item">
                                                <span class="field-label">Unit Margin</span>
                                                <span class="field-value <?= $margin < 0 ? 'text-danger' : 'text-success' ?>">
                                                    <?= number_format($margin, 2) ?> <small class="text-muted fw-normal"><?= esc($saleCurr) ?></small>
                                                </span>
                                            </div>
                                            <div class="field-item">
                                                <span class="field-label">Margin %</span>
                                                <span class="field-value <?= $margin < 0 ? 'text-danger' : 'text-success' ?>">
                                                    <?= $marginPct === null ? '<span class="text-muted fw-normal">—</span>' : number_format($marginPct, 1) . '%' ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($canViewSensitiveOverview): ?>
                            <div class="card mt-3">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="bi bi-geo-alt me-1"></i>Available Stock by Location</h6>
                                    <span class="badge bg-primary">Total: <?= number_format((float)($stock_total_available ?? 0), 2) ?></span>
                                </div>
                                <?php if (!empty($stock_by_location ?? [])): ?>
                                    <?php
                                        $stockPeak = 0.0;
                                        foreach (($stock_by_location ?? []) as $s) {
                                            $stockPeak = max($stockPeak, (float)($s['available_qty'] ?? 0));
                                        }
                                    ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Warehouse</th>
                                                    <th>Location</th>
                                                    <th class="text-end" style="width:170px">Available Qty</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach (($stock_by_location ?? []) as $s): ?>
                                                    <?php
                                                        $sq = (float)($s['available_qty'] ?? 0);
                                                        $share = $stockPeak > 0 ? max(0, min(100, ($sq / $stockPeak) * 100)) : 0;
                                                    ?>
                                                    <tr>
                                                        <td class="fw-semibold"><?= esc($s['warehouse_name'] ?? 'Unassigned Warehouse') ?></td>
                                                        <td class="text-muted"><?= esc($s['location_name'] ?? 'Unassigned Location') ?></td>
                                                        <td class="text-end">
                                                            <span class="fw-semibold <?= $sq < 0 ? 'text-danger' : 'text-success' ?>"><?= number_format($sq, 2) ?></span>
                                                            <span class="stock-bar d-block ms-auto">
                                                                <span class="stock-bar__fill" style="width:<?= number_format($share, 2, '.', '') ?>%<?= $sq < 0 ? ';background:var(--cl-color-danger,#ef5b6b)' : '' ?>"></span>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="bi bi-inboxes"></i>
                                        No location-wise stock currently available for this product.
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
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
                    <?php if ($canViewSensitiveOverview): ?>
                    <div class="card mt-3">
                        <div class="card-body">
                            <div class="field-panel">
                                <div class="field-panel__title"><i class="bi bi-diagram-3 me-1"></i>Classification &amp; Sourcing</div>
                                <div class="field-grid">
                                    <div class="field-item">
                                        <span class="field-label">Category</span>
                                        <span class="field-value">
                                            <?php if (!empty($product['category_name'])): ?>
                                                <span class="badge bg-info text-dark"><?= esc($product['category_name']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted fw-normal">Not assigned</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="field-item">
                                        <span class="field-label">Vendor</span>
                                        <span class="field-value">
                                            <?php $vendorName = $product['vendor_name'] ?? ($product['vendor']['name'] ?? null); ?>
                                            <?php if (!empty($vendorName)): ?>
                                                <span class="badge bg-success"><?= esc($vendorName) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted fw-normal">Not assigned</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($product['vendor_price'])): ?>
                                        <div class="field-item">
                                            <span class="field-label">Vendor Price</span>
                                            <span class="field-value"><?= esc(number_format((float)$product['vendor_price'], 2)) ?> <small class="text-muted fw-normal"><?= esc($product['vendor_currency'] ?? 'USD') ?></small></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="small text-muted mt-2">To change these values, click <strong>Edit</strong>.</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Related Information -->
                <div class="col-lg-4">
                    <!-- Quick Actions -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Actions</h6>
                        </div>
                        <div class="card-body">
                            <a href="<?= base_url('product-variants?product_id=' . $product['id']) ?>" class="quick-action-btn">
                                <span class="quick-action-icon"><i class="bi bi-list"></i></span>
                                View Variants (<?= $pvCount ?>)
                            </a>
                            <a href="<?= base_url('product-variants') ?>" class="quick-action-btn">
                                <span class="quick-action-icon"><i class="bi bi-grid-3x3-gap"></i></span>
                                All Variants
                            </a>
                            <a href="<?= base_url('products/' . $productIdentifier . '/processes') ?>" class="quick-action-btn">
                                <span class="quick-action-icon"><i class="bi bi-gear-wide-connected"></i></span>
                                Processes
                            </a>
                            <?php if ($can_delete): ?>
                                <button type="button" class="quick-action-btn danger" onclick="confirmDelete(<?= $product['id'] ?>)">
                                    <span class="quick-action-icon"><i class="bi bi-trash"></i></span>
                                    Delete Product
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Work Orders -->
                    <?php if (!empty($work_orders)): ?>
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-clipboard-check me-1"></i>Recent Work Orders</h6>
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
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-images me-1"></i>Images</h6>
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
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-images me-1"></i>Images</h6>
                            </div>
                            <div class="card-body">
                                <div class="empty-state">
                                    <i class="bi bi-image"></i>
                                    No images available
                                </div>
                                <div class="small text-muted">Click <strong>Edit</strong> to add images.</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
                </div>
                <div class="tab-pane fade <?= $activeTab === 'preparation' ? 'show active' : '' ?>" id="tab-preparation" role="tabpanel" aria-labelledby="tab-preparation-btn">
                    <?= view('preparation_profiles/_product_tab', [
                        'product' => $product,
                        'variants' => $variants ?? [],
                        'preparation_profiles' => $preparation_profiles ?? [],
                        'variant_preparation_profiles' => $variant_preparation_profiles ?? [],
                    ]) ?>
                </div>
                <?php if (!empty($can_view_assets)): ?>
                <div class="tab-pane fade <?= $activeTab === 'assets' ? 'show active' : '' ?>" id="tab-assets" role="tabpanel" aria-labelledby="tab-assets-btn">
                    <?= view('product_assets/_product_tab', [
                        'productIdentifier' => $productIdentifier,
                    ]) ?>
                </div>
                <?php endif; ?>
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
