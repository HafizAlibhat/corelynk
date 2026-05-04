<?php
// LEGACY MENU PARTIAL (NOT ACTIVE SOURCE OF TRUTH)
// Keep for historical compatibility only.
// Do not add/update primary navigation items here.
// Active menu is rendered by app/Views/partials/global_nav.php via app/Config/ModuleNav.php.
?>
<div class="nav-item dropdown me-2 d-none d-md-block">
    <a class="btn btn-sm btn-outline-primary dropdown-toggle" href="#" id="allLinksMenuTop" data-bs-toggle="dropdown" aria-expanded="false" title="All Links">
        <i class="bi bi-compass"></i>
    </a>
    <div class="dropdown-menu p-3" aria-labelledby="allLinksMenuTop" style="min-width:720px;">
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <h6 class="dropdown-header">Core</h6>
                <a class="dropdown-item" href="<?= base_url('/') ?>"><i class="bi bi-house me-2"></i>Home</a>
                <a class="dropdown-item" href="<?= base_url('/dashboard') ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
                <a class="dropdown-item" href="<?= base_url('/notifications') ?>"><i class="bi bi-bell me-2"></i>Notifications</a>
                <a class="dropdown-item" href="<?= base_url('/search') ?>"><i class="bi bi-search me-2"></i>Search</a>
                <a class="dropdown-item" href="<?= base_url('/help') ?>"><i class="bi bi-question-circle me-2"></i>Help</a>
                <a class="dropdown-item" href="<?= base_url('/profile') ?>"><i class="bi bi-person-circle me-2"></i>Profile</a>
                <a class="dropdown-item" href="<?= base_url('/settings') ?>"><i class="bi bi-gear me-2"></i>Settings</a>
                <?php if (defined('ENVIRONMENT') && ENVIRONMENT === 'development'): ?>
                    <a class="dropdown-item text-danger" href="<?= base_url('/devtools/clear-data') ?>"><i class="bi bi-broom me-2"></i>Development: Clear Data</a>
                <?php endif; ?>
            </div>
            <div class="col-12 col-md-4">
                <h6 class="dropdown-header">Purchasing</h6>
                <a class="dropdown-item" href="<?= base_url('/accounting/purchase-orders') ?>"><i class="bi bi-cart4 me-2"></i>Purchase Orders</a>
                <a class="dropdown-item" href="<?= base_url('/accounting/purchase-orders') ?>"><i class="bi bi-box-arrow-in-down me-2"></i>Receipts / GRNs</a>
                <a class="dropdown-item" href="<?= base_url('/vendors') ?>"><i class="bi bi-people me-2"></i>Vendors</a>
                <a class="dropdown-item" href="<?= base_url('/vendor-contacts') ?>"><i class="bi bi-person-lines-fill me-2"></i>Vendor Contacts</a>
                <a class="dropdown-item" href="<?= base_url('/accounting/cheques') ?>"><i class="bi bi-receipt me-2"></i>Vendor Payments</a>
                <a class="dropdown-item" href="<?= base_url('/subcontract-orders') ?>"><i class="bi bi-building-gear me-2"></i>Subcontracting</a>
                <a class="dropdown-item" href="<?= base_url('/gate_passes') ?>"><i class="bi bi-door-open me-2"></i>Gate Passes</a>
            </div>
            <div class="col-12 col-md-4">
                <h6 class="dropdown-header">Inventory</h6>
                <a class="dropdown-item" href="<?= base_url('/products') ?>"><i class="bi bi-box-seam me-2"></i>Products</a>
                <a class="dropdown-item" href="<?= base_url('/product-categories') ?>"><i class="bi bi-tags me-2"></i>Product Categories</a>
                    <a class="dropdown-item" href="<?= base_url('/product-attributes') ?>"><i class="bi bi-list-ul me-2"></i>Attributes</a>
                <a class="dropdown-item" href="<?= base_url('/product-processes') ?>"><i class="bi bi-layout-text-sidebar-reverse me-2"></i>Product Processes</a>
                <a class="dropdown-item" href="<?= base_url('/components') ?>"><i class="bi bi-tools me-2"></i>Components</a>
                    <a class="dropdown-item" href="<?= base_url('/inventory/warehouses') ?>"><i class="bi bi-building me-2"></i>Warehouses</a>
                    <a class="dropdown-item" href="<?= base_url('/inventory/locations') ?>"><i class="bi bi-geo-alt me-2"></i>Locations</a>
                <a class="dropdown-item" href="<?= base_url('/inventory') ?>"><i class="bi bi-boxes me-2"></i>Stock</a>
                <a class="dropdown-item" href="<?= base_url('/inventory/transactions') ?>"><i class="bi bi-stack me-2"></i>Inventory Transactions</a>
            </div>
            <div class="col-12 col-md-4">
                <h6 class="dropdown-header">Production</h6>
                <a class="dropdown-item" href="<?= base_url('/work-orders') ?>"><i class="bi bi-list-check me-2"></i>Work Orders</a>
                <a class="dropdown-item" href="<?= base_url('/processes') ?>"><i class="bi bi-gear me-2"></i>Processes</a>
                <a class="dropdown-item" href="<?= base_url('/process-templates') ?>"><i class="bi bi-journal-bookmark me-2"></i>Process Templates</a>
                <a class="dropdown-item" href="<?= base_url('/process-categories') ?>"><i class="bi bi-sliders me-2"></i>Process Categories</a>
                <a class="dropdown-item" href="<?= base_url('/batches') ?>"><i class="bi bi-diagram-3 me-2"></i>Batches</a>
                <a class="dropdown-item" href="<?= base_url('/subcontract-orders') ?>"><i class="bi bi-building-gear me-2"></i>Subcontracting</a>
                <a class="dropdown-item" href="<?= base_url('/gate_passes') ?>"><i class="bi bi-door-open me-2"></i>Gate Passes</a>
            </div>
            <div class="col-12 col-md-4">
                <h6 class="dropdown-header">Sales & CRM</h6>
                <a class="dropdown-item" href="<?= base_url('/document-studio') ?>"><i class="bi bi-easel me-2"></i>Document Studio</a>
                <a class="dropdown-item" href="<?= base_url('/pos') ?>"><i class="bi bi-display me-2"></i>POS Register</a>
                <a class="dropdown-item" href="<?= base_url('/customers') ?>"><i class="bi bi-people-fill me-2"></i>Customers</a>
                <a class="dropdown-item" href="<?= base_url('/quotations') ?>"><i class="bi bi-file-earmark-text me-2"></i>Quotations</a>
                <a class="dropdown-item" href="<?= base_url('/product-categories') ?>"><i class="bi bi-tags me-2"></i>Categories</a>
                <a class="dropdown-item" href="<?= base_url('/reports') ?>"><i class="bi bi-bar-chart-line me-2"></i>Reporting</a>
                <a class="dropdown-item" href="<?= base_url('/integrations/odoo/screen') ?>"><i class="bi bi-display me-2"></i>Odoo Screen</a>
            </div>
            <div class="col-12 col-md-4">
                <h6 class="dropdown-header">Accounting & Finance</h6>
                <a class="dropdown-item" href="<?= base_url('/accounting') ?>"><i class="bi bi-journal-text me-2"></i>Accounting</a>
                <a class="dropdown-item" href="<?= base_url('/accounting/journals') ?>"><i class="bi bi-journal me-2"></i>Journals</a>
                <a class="dropdown-item" href="<?= base_url('/accounting/cheques') ?>"><i class="bi bi-receipt me-2"></i>Cheques</a>
                <a class="dropdown-item" href="<?= base_url('/accounting/accounts') ?>"><i class="bi bi-list-ul me-2"></i>Accounts</a>
                <a class="dropdown-item" href="<?= base_url('/tax-codes') ?>"><i class="bi bi-percent me-2"></i>Tax Codes</a>
                <a class="dropdown-item" href="<?= base_url('/users') ?>"><i class="bi bi-people-gear me-2"></i>Users</a>
            </div>
        </div>
    </div>
</div>
