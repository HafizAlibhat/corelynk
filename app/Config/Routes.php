<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// Default route - Corelynk landing page
$routes->get('/', 'Corelynk::index');

// Convenience: Root-level auth routes (aliases to auth/*)
$routes->get('logout', 'Auth::logout');
$routes->get('mfa-setup', 'Auth::mfaSetup');
$routes->post('mfa-setup', 'Auth::mfaSetupProcess');

// Temporary dev-only JSON API to create quotations without auth (for smoke tests).
// Keep disabled in production.
if (ENVIRONMENT !== 'production') {
    $routes->post('dev/quotations/api/create', 'Quotations::apiCreate');
}

// Explicit server-side PDF route for receipts (ensure top-level registration)
$routes->get('accounting/journals/receipt/(:num)/pdf', 'AccountingJournals::receiptPdf/$1');

// Quick debug endpoint (unauthenticated) for client-side testing


// ── Public REST API (token-based, no session) ────────────────────────
$routes->group('api', ['namespace' => 'App\Controllers\Api'], function ($routes) {
    // Auth — no token required for login
    $routes->post('login',  'AuthApi::login');
    $routes->post('logout', 'AuthApi::logout');
    $routes->get('company-info', 'CompanyInfoApi::show');

    // Dashboard summary (legacy)
    $routes->get('dashboard', 'DashboardApi::index');

    // Owner dashboard — real financial summary
    $routes->get('owner/summary', 'OwnerDashboardApi::summary');

    // Products
    $routes->get('products',            'ProductApi::index');
    $routes->get('products/(:segment)', 'ProductApi::show/$1');

    // Vendors
    $routes->get('vendors',            'VendorApi::index');
    $routes->get('vendors/(:segment)', 'VendorApi::show/$1');

    // Sales Orders
    $routes->get('sales-orders',            'SalesOrderApi::index');
    $routes->get('sales-orders/(:segment)', 'SalesOrderApi::show/$1');
    $routes->post('sales-orders',           'SalesOrderCreateApi::create');

    // Purchase Orders
    $routes->get('purchase-orders',            'PurchaseOrderApi::index');
    $routes->get('purchase-orders/(:segment)', 'PurchaseOrderApi::show/$1');
    $routes->post('purchase-orders',           'PurchaseOrderCreateApi::create');

    // Quotations
    $routes->get('quotations',            'QuotationApi::index');
    $routes->get('quotations/(:segment)', 'QuotationApi::show/$1');
    $routes->post('quotations',           'QuotationApi::create');

    // Expenses (via journal entries)
    $routes->get('expenses',          'ExpenseApi::index');
    $routes->post('expenses',         'ExpenseApi::create');
    $routes->get('expense-accounts',  'ExpenseApi::expenseAccounts');
    $routes->get('payment-accounts',  'ExpenseApi::paymentAccounts');

    // Finance — receivables & payables
    $routes->get('receivables', 'FinanceApi::receivables');
    $routes->get('payables',    'FinanceApi::payables');

    // Customers
    $routes->get('customers',            'CustomerApi::index');
    $routes->get('customers/(:segment)', 'CustomerApi::show/$1');

    // Admin — API token management
    $routes->get('admin/api-tokens',              'ApiAdminApi::tokens');
    $routes->get('admin/api-stats',               'ApiAdminApi::stats');
    $routes->post('admin/api-tokens/(:num)/revoke', 'ApiAdminApi::revokeToken/$1');

    // Warehouse operations
    $routes->get('warehouse/delivery-orders',        'WarehouseApi::deliveryOrders');
    $routes->get('warehouse/delivery-orders/(:num)', 'WarehouseApi::showDeliveryOrder/$1');
    $routes->get('warehouse/ready-to-ship',          'WarehouseApi::readyToShip');

    // Admin — Mobile user management
    $routes->get('admin/users',                     'MobileUserApi::index');
    $routes->get('admin/users/(:num)',               'MobileUserApi::show/$1');
    $routes->post('admin/users/(:num)/toggle-active','MobileUserApi::toggleActive/$1');
    $routes->post('admin/users/(:num)/reset-password','MobileUserApi::resetPassword/$1');
    $routes->post('admin/users/(:num)/set-role',     'MobileUserApi::setRole/$1');
    $routes->get('admin/roles',                      'MobileUserApi::roles');
});

// Authentication Routes
$routes->group('auth', function($routes) {
    $routes->get('login', 'Auth::login');
    $routes->post('login', 'Auth::processLogin');
    $routes->get('logout', 'Auth::logout');
    $routes->get('register', 'Auth::register');
    $routes->post('register', 'Auth::attemptRegister');
    $routes->get('forgot-password', 'Auth::forgotPassword');
    $routes->post('forgot-password', 'Auth::sendResetLink');
    $routes->get('reset-password/(:any)', 'Auth::resetPassword/$1');
    $routes->post('reset-password', 'Auth::updatePassword');
    $routes->get('change-password', 'Auth::changePassword');
    $routes->post('change-password', 'Auth::processChangePassword');
    $routes->get('settings', 'Auth::userSettings');
    $routes->post('settings', 'Auth::saveUserSettings');
    // MFA
    $routes->get('mfa-verify', 'Auth::mfaVerify');
    $routes->post('mfa-verify', 'Auth::mfaVerifyProcess');
    $routes->get('mfa-setup', 'Auth::mfaSetup');
    $routes->post('mfa-setup', 'Auth::mfaSetupProcess');
});

// ── Admin Panel (admin role only) ─────────────────────────────────────
$routes->group('admin', ['filter' => 'role:admin'], function($routes) {
    // Users
    $routes->get('users', 'Admin::users');
    $routes->get('users/create', 'Admin::createUser');
    $routes->post('users/store', 'Admin::storeUser');
    $routes->get('users/(:num)/edit', 'Admin::editUser/$1');
    $routes->post('users/(:num)/update', 'Admin::updateUser/$1');
    $routes->post('users/(:num)/toggle', 'Admin::toggleUser/$1');

    // Roles
    $routes->get('roles', 'Admin::roles');
    $routes->get('roles/create', 'Admin::createRole');
    $routes->post('roles/store', 'Admin::storeRole');
    $routes->get('roles/(:num)/edit', 'Admin::editRole/$1');
    $routes->post('roles/(:num)/update', 'Admin::updateRole/$1');
    $routes->post('roles/(:num)/delete', 'Admin::deleteRole/$1');

    // Field Permissions
    $routes->get('roles/(:num)/fields', 'Admin::fieldPermissions/$1');
    $routes->post('roles/(:num)/fields/save', 'Admin::saveFieldPermissions/$1');

    // Role Data Access Controls
    $routes->get('roles/(:num)/data-access', 'Admin::dataAccess/$1');
    $routes->post('roles/(:num)/data-access/save', 'Admin::saveDataAccess/$1');

    // Audit Log
    $routes->get('audit-log', 'Admin::auditLog');

    // Security Settings
    $routes->get('security', 'SecuritySettings::index');
    $routes->post('security/toggle-flag', 'SecuritySettings::toggleFlag');
    $routes->get('security/auth-logs', 'SecuritySettings::authLogs');

    // Mobile App Management
    $routes->get('mobile-app', 'MobileAdmin::index');
    $routes->post('mobile-app/toggle/(:num)', 'MobileAdmin::toggleMobileAccess/$1');
    $routes->post('mobile-app/reset-password/(:num)', 'MobileAdmin::resetPassword/$1');
    $routes->post('mobile-app/revoke-token/(:num)', 'MobileAdmin::revokeToken/$1');
    $routes->post('mobile-app/revoke-all/(:num)', 'MobileAdmin::revokeAllTokens/$1');
});

// Protected Routes (require authentication)
$routes->group('', function($routes) {
    
    // Dashboard
    $routes->get('dashboard', 'Dashboard::index');
    $routes->get('dashboard/stats', 'Dashboard::getStats');
    $routes->get('dashboard/charts/(:any)', 'Dashboard::getChartData/$1');
    
    // Corelynk CEO dashboard + widgets
    $routes->get('corelynk/fx-rates', 'Corelynk::fxRates');

    // POS (Point of Sale) Register
    $routes->group('pos', function($routes) {
        $routes->get('/', 'Pos::index');
        $routes->get('get-products', 'Pos::getProducts');
        $routes->get('search-products', 'Pos::searchProducts');
        $routes->post('save-order', 'Pos::saveOrder');
        $routes->get('orders', 'Pos::orders');
        $routes->get('order/(:num)', 'Pos::getOrder/$1');
        $routes->get('receipt/(:num)', 'Pos::receipt/$1');
        $routes->post('void-order/(:num)', 'Pos::voidOrder/$1');
        $routes->get('next-order-number', 'Pos::nextOrderNumber');
    });

    // Document Studio (Unified Quote / SO / RFQ / PO creator)
    $routes->group('document-studio', function($routes) {
        $routes->get('/', 'DocumentStudio::index');
        $routes->get('search-products', 'DocumentStudio::searchProducts');
        $routes->get('search-customers', 'DocumentStudio::searchCustomers');
        $routes->get('search-vendors', 'DocumentStudio::searchVendors');
        $routes->get('list-documents', 'DocumentStudio::listDocuments');
        $routes->get('load-quotation/(:num)', 'DocumentStudio::loadQuotation/$1');
        $routes->post('update-quotation/(:num)', 'DocumentStudio::updateQuotation/$1');
        $routes->post('save-quotation', 'DocumentStudio::saveQuotation');
        $routes->post('save-sales-order', 'DocumentStudio::saveSalesOrder');
        $routes->post('save-rfq', 'DocumentStudio::saveRfq');
        $routes->post('save-purchase-order', 'DocumentStudio::savePurchaseOrder');
    });

    // Products Management
    $routes->group('products', function($routes) {
        $productIdentifier = '([0-9]+|[a-f0-9-]{36})';

        $routes->get('/', 'Products::index');
        // AJAX data endpoint for products (used by products list modal)
        $routes->get('get-data', 'Products::getData');
        // Lightweight JSON search endpoint for typeahead/autocomplete (code/name)
        $routes->get('search', 'Products::search');
        $routes->get('create', 'Products::create');
        $routes->post('store', 'Products::store');
        $routes->get($productIdentifier, 'Products::show/$1');
        $routes->get($productIdentifier . '/edit', 'Products::edit/$1');
        $routes->post('(:num)/update', 'Products::update/$1');
        $routes->post('(:num)/copy-weight-to-variants', 'Products::copyWeightToVariants/$1');
        $routes->post('(:num)/copy-vendor-to-variants', 'Products::copyVendorToVariants/$1');
        $routes->post('(:num)/copy-cost-to-variants', 'Products::copyCostToVariants/$1');
        $routes->post('(:num)/copy-sale-to-variants', 'Products::copySaleToVariants/$1');
        $routes->post('(:num)/copy-vendor-pkr-to-variants', 'Products::copyVendorPkrToVariants/$1');
        $routes->post('validate-attribute-deletion', 'Products::validateAttributeValueDeletion');
        $routes->delete('(:num)', 'Products::delete/$1');
        $routes->post('(:num)/toggle-status', 'Products::toggleStatus/$1');
    $routes->post('(:num)/upload-images', 'Products::uploadImages/$1');
    $routes->post('(:num)/ajax-update-category', 'Products::ajaxUpdateCategory/$1');
    $routes->post('(:num)/ajax-update-vendor', 'Products::ajaxUpdateVendor/$1');
        $routes->post('backfill-services', 'Products::backfillServices');
        $routes->get('export', 'Products::export');
        $routes->post('import', 'Products::import');
        
        // Product Processes
        $routes->get($productIdentifier . '/processes', 'Products::processes/$1');
        $routes->post('(:num)/processes/add', 'Products::addProcess/$1');
        $routes->delete('(:num)/processes/(:num)', 'Products::removeProcess/$1/$2');
        $routes->post('(:num)/processes/reorder', 'Products::updateProcessOrder/$1');
        $routes->post('(:num)/processes/(:num)/update', 'Products::updateProcess/$1/$2');
        $routes->post('bulk-assign-processes', 'Products::bulkAssignProcesses');
        $routes->post('copy-processes', 'Products::copyProcesses');

        // Product Assets Module
        $routes->get($productIdentifier . '/assets', 'ProductAssets::index/$1');
        $routes->get($productIdentifier . '/assets/data', 'ProductAssets::data/$1');
        $routes->post($productIdentifier . '/assets/groups', 'ProductAssets::createGroup/$1');
        $routes->post($productIdentifier . '/assets/channels', 'ProductAssets::createChannel/$1');
        $routes->post($productIdentifier . '/assets/channels/(:num)/update', 'ProductAssets::updateChannel/$1/$2');
        $routes->post($productIdentifier . '/assets/channels/(:num)/delete', 'ProductAssets::deleteChannel/$1/$2');
        $routes->post($productIdentifier . '/assets/upload', 'ProductAssets::upload/$1');
        $routes->post($productIdentifier . '/assets/listings', 'ProductAssets::saveListing/$1');
        $routes->post($productIdentifier . '/assets/(:num)/primary', 'ProductAssets::setPrimary/$1/$2');
        $routes->post($productIdentifier . '/assets/(:num)/update-file', 'ProductAssets::updateAssetFile/$1/$2');
        $routes->post($productIdentifier . '/assets/(:num)/attach-source', 'ProductAssets::attachSource/$1/$2');
        $routes->post($productIdentifier . '/assets/(:num)/delete', 'ProductAssets::deleteAsset/$1/$2');
        $routes->post($productIdentifier . '/assets/(:num)/move', 'ProductAssets::moveAsset/$1');
        $routes->post($productIdentifier . '/assets/(:num)/copy', 'ProductAssets::copyAsset/$1');
    });

    // Product Assets Hub (global entry point)
    $routes->get('product-assets', 'ProductAssets::hub');
    $routes->post('product-assets/channels/store', 'ProductAssets::createChannelHub');

    // Product Preparation Profiles
    $routes->group('preparation-profiles', function($routes) {
        $routes->get('product/(:num)', 'PreparationProfiles::index/$1');
        $routes->get('product/(:num)/create', 'PreparationProfiles::create/$1');
        $routes->get('variant/(:num)', 'PreparationProfiles::indexVariant/$1');
        $routes->get('variant/(:num)/create', 'PreparationProfiles::createVariant/$1');
        $routes->post('store', 'PreparationProfiles::store');
        $routes->get('(:num)/edit', 'PreparationProfiles::edit/$1');
        $routes->post('(:num)/update', 'PreparationProfiles::update/$1');
        $routes->post('(:num)/delete', 'PreparationProfiles::delete/$1');
    });

    // Product Variants (preview & generate endpoints)
    $routes->group('product-variants', function($routes) {
        $routes->get('/', 'ProductVariants::index');
        $routes->get('search', 'ProductVariants::search');
        $routes->get('create', 'ProductVariants::create');
        $routes->post('store', 'ProductVariants::store');
        $routes->post('generate-preview', 'ProductVariants::generatePreview');
        $routes->post('generate', 'ProductVariants::generate');
        $routes->get('api/(:num)', 'ProductVariants::apiList/$1');
    $routes->delete('(:num)', 'ProductVariants::delete/$1');
    // Some clients (and HTML forms without method-override) post to /product-variants/{id}/delete
    // Accept POST for compatibility and route it to the same delete handler.
    $routes->post('(:num)/delete', 'ProductVariants::delete/$1');
    $routes->post('bulk-delete', 'ProductVariants::bulkDelete');
    });

    // Variant Exclusion Rules API (manage which attribute combos to skip)
    $routes->group('variant-exclusions', function($routes) {
        $routes->get('/', 'VariantExclusionRules::index');
        $routes->get('attribute-values/(:num)', 'VariantExclusionRules::attributeValues/$1');
        $routes->get('(:num)', 'VariantExclusionRules::show/$1');
        $routes->post('/', 'VariantExclusionRules::store');
        $routes->post('(:num)', 'VariantExclusionRules::update/$1');
        $routes->delete('(:num)', 'VariantExclusionRules::delete/$1');
        $routes->post('(:num)/conditions', 'VariantExclusionRules::addCondition/$1');
        $routes->delete('conditions/(:num)', 'VariantExclusionRules::deleteCondition/$1');
        $routes->post('(:num)/preview', 'VariantExclusionRules::preview/$1');
        $routes->post('(:num)/toggle', 'VariantExclusionRules::toggle/$1');
    });
    
    // Product Categories Management
    $routes->group('product-categories', function($routes) {
        $routes->get('/', 'ProductCategories::index');
        $routes->get('create', 'ProductCategories::create');
        $routes->post('store', 'ProductCategories::store');
        $routes->get('(:num)/edit', 'ProductCategories::edit/$1');
        $routes->post('(:num)/update', 'ProductCategories::update/$1');
        $routes->delete('(:num)', 'ProductCategories::delete/$1');
        $routes->post('(:num)/toggle-status', 'ProductCategories::toggleStatus/$1');
        $routes->get('data', 'ProductCategories::getData');
    });

    // Subcontract Orders (buy services from vendors — material issue/return flow)
    $routes->group('subcontract-orders', function($routes) {
        $routes->get('/', 'SubcontractOrders::index');
        $routes->get('create', 'SubcontractOrders::create');
        $routes->post('store', 'SubcontractOrders::store');
        $routes->get('(:segment)', 'SubcontractOrders::show/$1');
        $routes->get('(:segment)/edit', 'SubcontractOrders::edit/$1');
        $routes->post('(:segment)/update', 'SubcontractOrders::update/$1');
        $routes->post('(:segment)/confirm', 'SubcontractOrders::confirm/$1');
        $routes->post('(:segment)/issue-materials', 'SubcontractOrders::issueMaterials/$1');
        $routes->post('(:segment)/receive-materials', 'SubcontractOrders::receiveMaterials/$1');
        $routes->post('(:segment)/cancel', 'SubcontractOrders::cancel/$1');
        // AJAX search endpoints
        $routes->get('search-services', 'SubcontractOrders::searchServiceProducts');
        $routes->get('search-storable', 'SubcontractOrders::searchStorableProducts');
        $routes->get('product-variants/(:num)', 'SubcontractOrders::productVariants/$1');
    });

    // Development tools (dev only — disabled in production)
    if (ENVIRONMENT !== 'production') {
        $routes->group('devtools', function($routes) {
            $routes->get('clear-data', 'DevTools::index');
            $routes->post('clear-data', 'DevTools::clear');
            $routes->post('set-env', 'DevTools::setEnv');
        });
    }

    // Global Product Attributes
    $routes->group('product-attributes', function($routes) {
        $routes->get('/', 'ProductAttributes::index');
        $routes->get('create', 'ProductAttributes::create');
        $routes->post('store', 'ProductAttributes::store');
        $routes->get('(:num)/edit', 'ProductAttributes::edit/$1');
        $routes->post('(:num)/update', 'ProductAttributes::update/$1');
        $routes->get('data', 'ProductAttributes::data');
        // Lightweight endpoints for product form (Odoo-like search UX)
        $routes->get('list', 'ProductAttributes::list');
        $routes->get('search', 'ProductAttributes::search');
        $routes->get('(:num)/values', 'ProductAttributes::values/$1');
    $routes->delete('(:num)', 'ProductAttributes::delete/$1');
    // Accept POST to /product-attributes/{id}/delete for compatibility with non-RESTful forms
    $routes->post('(:num)/delete', 'ProductAttributes::delete/$1');
    // Update or delete a single value from an attribute (AJAX)
    $routes->post('(:num)/value/update', 'ProductAttributes::updateValue/$1');
    $routes->post('(:num)/value/check', 'ProductAttributes::checkValueUsage/$1');
    $routes->post('(:num)/value/add', 'ProductAttributes::addValue/$1');
    $routes->post('(:num)/value/delete', 'ProductAttributes::deleteValue/$1');
    });

    // Normalized attribute values (Phase 1; no behavior change)
    $routes->group('product-attribute-values', function($routes) {
        $routes->get('/', 'ProductAttributeValues::index');
        $routes->get('create', 'ProductAttributeValues::create');
        $routes->post('store', 'ProductAttributeValues::store');
        $routes->get('(:num)/edit', 'ProductAttributeValues::edit/$1');
        $routes->post('(:num)/update', 'ProductAttributeValues::update/$1');
        $routes->post('(:num)/delete', 'ProductAttributeValues::delete/$1');
        $routes->get('by-attribute', 'ProductAttributeValues::byAttribute');
    });

    // Product ↔ attribute assignments (Phase 1; no runtime enforcement yet)
    $routes->group('product-attribute-assignments', function($routes) {
        $routes->get('/', 'ProductAttributeAssignments::index');
        $routes->get('create', 'ProductAttributeAssignments::create');
        $routes->post('store', 'ProductAttributeAssignments::store');
        $routes->get('(:num)/edit', 'ProductAttributeAssignments::edit/$1');
        $routes->post('(:num)/update', 'ProductAttributeAssignments::update/$1');
        $routes->post('(:num)/delete', 'ProductAttributeAssignments::delete/$1');
    });
    
    // Process Templates Management
    $routes->group('process-templates', function($routes) {
        $routes->get('/', 'ProcessTemplates::index');
        $routes->get('create', 'ProcessTemplates::create');
        $routes->post('store', 'ProcessTemplates::store');
        $routes->get('(:num)', 'ProcessTemplates::show/$1');
        $routes->get('(:num)/edit', 'ProcessTemplates::edit/$1');
        $routes->post('(:num)/update', 'ProcessTemplates::update/$1');
        $routes->delete('(:num)', 'ProcessTemplates::delete/$1');
        $routes->post('(:num)/duplicate', 'ProcessTemplates::duplicate/$1');
        $routes->get('by-category', 'ProcessTemplates::getByCategory');
        $routes->get('for-select', 'ProcessTemplates::getForSelect');
    });
    
    // Process Categories Management
    $routes->group('process-categories', function($routes) {
        $routes->get('/', 'ProcessCategories::index');
        $routes->get('create', 'ProcessCategories::create');
        $routes->post('store', 'ProcessCategories::store');
        $routes->get('(:num)/edit', 'ProcessCategories::edit/$1');
        $routes->post('(:num)/update', 'ProcessCategories::update/$1');
        $routes->delete('(:num)', 'ProcessCategories::delete/$1');
        $routes->post('(:num)/toggle-status', 'ProcessCategories::toggleStatus/$1');
        $routes->get('data', 'ProcessCategories::getData');
    });
    
    // Work Orders Management
    $routes->group('work-orders', function($routes) {
        $routes->get('/', 'WorkOrders::index');
        $routes->get('create', 'WorkOrders::create');
        $routes->post('create', 'WorkOrders::store');
        $routes->get('(:num)', 'WorkOrders::show/$1');
        $routes->get('(:num)/products', 'WorkOrders::getProducts/$1');
        $routes->get('(:num)/edit', 'WorkOrders::edit/$1');
        $routes->post('(:num)/edit', 'WorkOrders::update/$1');
    $routes->delete('(:num)', 'WorkOrders::delete/$1');
    // Some clients call a POST/DELETE endpoint at /work-orders/{id}/delete; accept POST too for compatibility
    $routes->post('(:num)/delete', 'WorkOrders::delete/$1');
    // Change work order status
    $routes->post('(:num)/change-status', 'WorkOrders::changeStatus/$1');
        $routes->post('(:num)/start', 'WorkOrders::start/$1');
        $routes->post('(:num)/complete', 'WorkOrders::complete/$1');
        $routes->post('(:num)/hold', 'WorkOrders::hold/$1');
        $routes->post('(:num)/cancel', 'WorkOrders::cancel/$1');
        $routes->get('(:num)/operations', 'WorkOrders::operations/$1');
        $routes->post('(:num)/operations', 'WorkOrders::updateOperations/$1');
        $routes->post('ajax_create_batch', 'WorkOrders::ajaxCreateBatch');
    $routes->post('batches/(:num)/release', 'WorkOrders::ajaxReleaseBatch/$1');
    $routes->get('releases/(:num)/gatepass', 'WorkOrders::gatepass/$1');
    $routes->get('releases/(:num)/gatepass.pdf', 'WorkOrders::gatepassPdf/$1');
    // Process batch endpoints (AJAX)
    $routes->get('(:num)/items/(:num)/processes', 'WorkOrders::ajaxGetItemProcesses/$1/$2');
    $routes->post('(:num)/items/(:num)/batches/create', 'WorkOrders::ajaxCreateBatch/$1/$2');
    $routes->post('ajax_create_batch', 'WorkOrders::ajaxCreateBatch');
    $routes->post('ajax_delete_batch/(:num)', 'WorkOrders::ajax_delete_batch/$1');
    $routes->post('ajaxDeleteBatch/(:num)', 'WorkOrders::ajaxDeleteBatch/$1');
    $routes->post('batches/(:num)/logs/add', 'WorkOrders::ajaxAddBatchLog/$1');
    $routes->post('batches/(:num)/update', 'WorkOrders::ajaxUpdateBatch/$1');
    $routes->get('batches/(:num)/details', 'WorkOrders::ajaxGetBatchDetails/$1');
        $routes->get('export', 'WorkOrders::export');
        $routes->get('schedule', 'WorkOrders::schedule');
    });
    
    // Employees Management
    $routes->group('employees', function($routes) {
        $routes->get('/', 'Employees::index');
        $routes->get('create', 'Employees::create');
        $routes->post('store', 'Employees::store');
        $routes->get('(:num)', 'Employees::show/$1');
        $routes->get('(:num)/edit', 'Employees::edit/$1');
        $routes->post('(:num)/update', 'Employees::update/$1');
        $routes->delete('(:num)', 'Employees::delete/$1');
        $routes->get('by-skill', 'Employees::getBySkill');
        $routes->get('all', 'Employees::getAll');
        // Alias to support frontend calling /employees/getAll
        $routes->get('getAll', 'Employees::getAll');
    });
    
    // Batches Management (Full-page view)
    $routes->group('batches', function($routes) {
        $routes->get('/', 'Batches::index');
        $routes->get('(:num)', 'Batches::show/$1');
        $routes->get('export', 'Batches::export');
        $routes->get('(:num)/details', 'Batches::ajaxGetBatchDetails/$1');
        $routes->post('(:num)/update', 'Batches::ajaxUpdateBatch/$1');
        $routes->delete('(:num)', 'Batches::delete/$1');
    });
    
    // Gate Pass Management (Material Control)
    $routes->group('gate_passes', function($routes) {
        $routes->get('/', 'GatePassesFixed::index');
        $routes->get('create', 'GatePassesFixed::create');
        $routes->post('create', 'GatePassesFixed::create');
        $routes->get('(:segment)', 'GatePassesFixed::show/$1');
        $routes->get('(:segment)/pdf', 'GatePassesFixed::generatePDF/$1');
        $routes->post('(:segment)/status', 'GatePassesFixed::updateStatus/$1');
        $routes->get('export', 'GatePassesFixed::export');
        $routes->delete('(:segment)', 'GatePassesFixed::delete/$1');
    });
    
    // PDF Generation System
    $routes->group('pdfs', function($routes) {
        $routes->get('batch/(:num)', 'PDFs::batchReport/$1');
        $routes->get('batches', 'PDFs::batchReport');
        $routes->get('work_order/(:num)', 'PDFs::workOrderReport/$1');
        $routes->get('gate_pass/(:num)', 'PDFs::gatePassReport/$1');
    });
    
    // Processes Management
    $routes->group('processes', function($routes) {
        $routes->get('/', 'Processes::index');
        $routes->get('create', 'Processes::create');
        $routes->post('create', 'Processes::store');
        $routes->post('store', 'Processes::store'); // Add this route
        $routes->get('(:num)', 'Processes::show/$1');
        $routes->get('(:num)/edit', 'Processes::edit/$1');
        $routes->post('(:num)/edit', 'Processes::update/$1');
        $routes->delete('(:num)', 'Processes::delete/$1');
        $routes->get('export', 'Processes::export');
        $routes->get('templates', 'Processes::templates');
        $routes->post('templates', 'Processes::saveTemplate');
        $routes->post('create-vendor', 'Processes::createVendor');
    });

    // Production Logs Management
    $routes->group('production', function($routes) {
        // Main production logs page
        $routes->get('logs', 'Production::logs');
        $routes->get('/', 'Production::logs'); // Default route for /production
        
        // AJAX endpoints for batch management
        $routes->get('ajax-get-batches', 'Production::ajaxGetBatches');
        $routes->get('ajax-get-active-batches', 'Production::ajaxGetActiveBatches');
        $routes->get('ajax-get-item-processes/(:num)', 'Production::ajaxGetItemProcesses/$1');
        $routes->post('ajax-create-batch', 'Production::ajaxCreateBatch');
        $routes->post('ajax-delete-batch/(:num)', 'Production::ajaxDeleteBatch/$1');
        $routes->post('ajax-delete-batch', 'Production::ajaxDeleteBatch'); // For JSON body
        $routes->post('ajax-add-log', 'Production::ajaxAddLog');
        $routes->get('ajax-get-batch-summary/(:num)', 'Production::ajaxGetBatchSummary/$1');
        $routes->get('ajax-get-batch-details/(:num)', 'Production::ajaxGetBatchDetails/$1');
        $routes->post('ajax-update-batch-status', 'Production::ajaxUpdateBatchStatus');
        
        // Delete endpoints for hierarchical view
        $routes->post('ajax-delete-work-order', 'Production::ajaxDeleteWorkOrder');
        $routes->post('ajax-delete-product', 'Production::ajaxDeleteProduct');
        $routes->post('ajax-delete-process', 'Production::ajaxDeleteProcess');
        $routes->post('ajax-add-daily-log', 'Production::ajaxAddDailyLog');
        
        // Hierarchical view AJAX endpoints
        $routes->get('ajax-get-work-order-hierarchy', 'Production::ajaxGetWorkOrderHierarchy');
        $routes->get('ajax-get-work-order-details/(:num)', 'Production::ajaxGetWorkOrderDetails/$1');
        
        // Export functionality
        $routes->get('export', 'Production::export');
        $routes->get('export-batch/(:num)', 'Production::exportBatch/$1');
    });

    // Production Follow-Up (Kiosk / Dashboard screens)
    $routes->group('production/follow-up', function($routes) {
        // Main interactive follow-up page (has controls)
        $routes->get('/', 'ProductionFollowUp::index');

        // Kiosk-friendly per-screen URL (minimal chrome). Example: /production/follow-up/screen-1
        $routes->get('screen/(:any)', 'ProductionFollowUp::screen/$1');

        // JSON endpoints used by the follow-up screen(s)
        $routes->get('api/summary', 'ProductionFollowUp::apiSummary');
        $routes->get('api/panels', 'ProductionFollowUp::apiPanels');
    });

    // New Logs module (fresh implementation)
    $routes->group('logs', function($routes) {
        $routes->get('/', 'Logs::index');
        $routes->get('getProducts/(:num)', 'Logs::getProducts/$1');
        $routes->get('getProcesses', 'Logs::getProcesses');
        $routes->get('getVendorsForProcess', 'Logs::getVendorsForProcess');
    $routes->get('getEmployeesForProcess', 'Logs::getEmployeesForProcess');
        $routes->post('createBatch', 'Logs::createBatch');
        $routes->post('addLog', 'Logs::addLog');
        $routes->post('updateLog', 'Logs::updateLog');
        $routes->post('deleteLog', 'Logs::deleteLog');
        $routes->get('hierarchy', 'Logs::hierarchy');
    });
    
    // Workflow Templates Management
    $routes->group('workflow-templates', function($routes) {
        $routes->get('/', 'WorkflowTemplates::index');
        $routes->get('create', 'WorkflowTemplates::create');
        $routes->post('store', 'WorkflowTemplates::store');
        $routes->get('(:num)', 'WorkflowTemplates::view/$1');
        $routes->get('(:num)/edit', 'WorkflowTemplates::edit/$1');
        $routes->post('(:num)/update', 'WorkflowTemplates::update/$1');
        $routes->delete('(:num)', 'WorkflowTemplates::delete/$1');
        $routes->post('assign-to-product', 'WorkflowTemplates::assignToProduct');
        $routes->post('remove-from-product', 'WorkflowTemplates::removeFromProduct');
    });
    
    // Vendors Management
    $routes->group('vendors', function($routes) {
        $routes->get('/', 'Vendors::index');
        $routes->get('create', 'Vendors::create');
        $routes->post('store', 'Vendors::store');
        // Keep specific JSON search route above catch-all segment route.
        $routes->get('search', 'Vendors::search');
        $routes->get('(:segment)', 'Vendors::show/$1');
        $routes->post('(:segment)/addContact', 'Vendors::addContact/$1');
        $routes->post('(:segment)/updateContact/(:num)', 'Vendors::updateContact/$1/$2');
        $routes->post('(:segment)/deleteContact/(:num)', 'Vendors::deleteContact/$1/$2');
        $routes->get('(:segment)/edit', 'Vendors::edit/$1');
        $routes->post('update/(:segment)', 'Vendors::update/$1');
        $routes->delete('(:segment)', 'Vendors::delete/$1');
        $routes->get('export', 'Vendors::export');
        $routes->get('(:segment)/performance', 'Vendors::performance/$1');
        $routes->get('(:segment)/orders', 'Vendors::orders/$1');
    });

    // Customers Management (MVP)
    $routes->group('customers', function($routes) {
        $routes->get('/', 'Customers::index');
        $routes->get('create', 'Customers::create');
        $routes->post('create', 'Customers::create');
        // Debug-only JSON API — disabled in production
        if (ENVIRONMENT !== 'production') {
            $routes->post('api-create', 'Customers::apiCreate');
        }
        $routes->get('(:segment)', 'Customers::show/$1');
        $routes->get('(:segment)/edit', 'Customers::edit/$1');
        $routes->post('(:segment)/edit', 'Customers::edit/$1');
        $routes->get('(:segment)/delete', 'Customers::delete/$1');
        $routes->post('(:segment)/delete', 'Customers::delete/$1');
        $routes->post('(:segment)/toggle-status', 'Customers::toggleStatus/$1');
        $routes->post('(:segment)/add-address', 'Customers::addAddress/$1');
        $routes->post('(:segment)/add-contact', 'Customers::addContact/$1');
        $routes->post('(:segment)/update-contact/(:num)', 'Customers::updateContact/$1/$2');
        $routes->post('(:segment)/delete-contact/(:num)', 'Customers::deleteContact/$1/$2');
    // AJAX endpoints for address selects
    $routes->get('states/(:num)', 'Customers::states/$1');
    $routes->get('states', 'Customers::states');
    $routes->get('cities/(:num)', 'Customers::cities/$1');
    $routes->get('cities', 'Customers::cities');
    $routes->get('zip-search', 'Customers::zipSearch');
    $routes->post('(:num)/update-address/(:num)', 'Customers::updateAddress/$1/$2');
    $routes->post('(:num)/delete-address/(:num)', 'Customers::deleteAddress/$1/$2');
    $routes->post('(:num)/addresses/(:num)/set-default', 'Customers::setAddressDefault/$1/$2');
    });

    // Customer Invoices (System & Custom)
    $routes->group('customer-invoices', ['filter' => 'auth'], function($routes) {
        // List view
        $routes->get('/', 'CustomerInvoices::index');
        $routes->get('', 'CustomerInvoices::index');
        // Phase-1 simple lifecycle
        $routes->get('create-from-so/(:num)', 'CustomerInvoices::createFromSalesOrder/$1');
        $routes->get('edit/(:segment)', 'CustomerInvoices::edit/$1');
        $routes->post('update/(:segment)', 'CustomerInvoices::update/$1');
        $routes->post('post/(:segment)', 'CustomerInvoices::post/$1');
        $routes->get('view/(:segment)', 'CustomerInvoices::view/$1');
    $routes->get('pdf/(:segment)', 'CustomerInvoices::pdf/$1');

        // System invoices
        $routes->get('system/new', 'CustomerInvoices::createSystemView');
        $routes->post('system/create', 'CustomerInvoices::createSystemInvoice');

        // Custom invoices (based on system invoice)
        $routes->get('custom/create/(:num)', 'CustomerInvoices::createCustomView/$1');
        $routes->post('custom/create/(:num)', 'CustomerInvoices::createCustomInvoice/$1');

        // PDF generation / download
        $routes->get('(:segment)/pdf/system', 'CustomerInvoices::downloadSystemPDF/$1');
        $routes->get('(:segment)/pdf/custom', 'CustomerInvoices::downloadCustomPDF/$1');
    });

    // Sales & Quotations
    // Quotations feature was removed and archived previously; archived copies are kept here:
    // Archived copies: c:\xampp\htdocs\corelynk\archives\quotations-20251215-1344\
    // A new Quotation module has been added (minimal scaffold). Routes below register
    // a protected group for quotations and expose a JSON-aware create endpoint.
    $routes->group('quotations', ['filter' => 'auth'], function($routes) {
        // List / index (for now redirects to create)
        $routes->get('/', 'Quotations::index');
        // Show create form (GET)
        $routes->get('create', 'Quotations::create');
        // Form POST endpoint (accepts normal form POST and returns JSON when requested)
        $routes->post('create', 'Quotations::store');
        // Dedicated JSON API endpoint: POST /quotations/api/create
        $routes->post('api/create', 'Quotations::apiCreate');
        // Inline line update
        $routes->post('update-line/(:any)', 'Quotations::updateLine/$1');
    // Update shipping amount
    $routes->post('update-shipping/(:any)', 'Quotations::updateShipping/$1');
        // Bulk update quotation (edit form)
        $routes->post('update/(:any)', 'Quotations::update/$1');
        // View a saved quotation
        $routes->get('view/(:any)', 'Quotations::view/$1');
        // Download quotation PDF
        $routes->get('pdf/(:any)', 'Quotations::pdf/$1');
            // Edit alias (temporary: redirect to view until edit form exists)
            $routes->get('edit/(:any)', 'Quotations::edit/$1');
    // Delete quotation (accept POST or DELETE for compatibility with HTML forms)
    $routes->match(['GET','POST','DELETE'],'delete/(:any)', 'Quotations::delete/$1');
        // AJAX helpers
        $routes->get('search-products', 'Quotations::searchProducts');
        $routes->get('product-vendors/price', 'ProductVendors::price');
        $routes->get('search-customers', 'Quotations::searchCustomers');
        $routes->get('price-lists/(:num)', 'Quotations::getPriceLists/$1');
        $routes->post('calculate', 'Quotations::calculateQuote');
    });

    // Unified Sales Documents list (Quotations + Sales Orders)
    $routes->group('documents', ['filter' => 'auth'], function($routes) {
        $routes->get('/', 'Documents::index');
        $routes->get('search', 'Documents::search');
    });

    // Expose product vendor price lookup at a top-level path for AJAX clients
    // (keeps compatibility with frontend code that requests /product-vendors/price)
    $routes->get('product-vendors/price', 'ProductVendors::price');
    $routes->group('sales-orders', ['filter' => 'auth'], function($routes) {
        $routes->get('/', 'SalesOrders::index');
        $routes->get('create', 'SalesOrders::create');
        $routes->post('create', 'SalesOrders::store');
        $routes->get('view/(:any)', 'SalesOrders::view/$1');
        $routes->get('pdf/(:any)', 'SalesOrders::pdf/$1');
        $routes->get('create-from-quotation/(:num)', 'SalesOrders::createFromQuotation/$1');
        $routes->get('invoice/(:num)', 'SalesOrders::invoice/$1');
        $routes->post('cancel/(:any)', 'SalesOrders::cancel/$1');
        $routes->post('create-purchase-drafts/(:num)', 'SalesOrders::createPurchaseDrafts/$1'); // Phase-2
        $routes->post('preparation/send-to-vendor', 'PreparationExecution::sendToVendor');
        $routes->post('preparation/start-inhouse', 'PreparationExecution::startInHouse');
    });

    $routes->group('vendor-receive', ['filter' => 'auth'], function($routes) {
        $routes->get('/', 'VendorReceive::index');
        $routes->get('(:num)', 'VendorReceive::receiveForm/$1');
        $routes->post('store', 'VendorReceive::store');
    });

    // Warehouse queue
    $routes->group('warehouse', ['filter' => 'auth'], function($routes) {
        $routes->get('ready-to-ship', 'WarehouseDashboard::readyToShip');
        $routes->get('incoming-shipments', 'WarehouseDashboard::incomingFromVendors');
        // Debug endpoints — production-gated
        if (ENVIRONMENT !== 'production') {
            $routes->get('debugFulfillment', 'WarehouseDashboard::debugFulfillment');
            $routes->get('testReadyToShip', 'WarehouseDashboard::testReadyToShip');
            $routes->get('testDOCreation', 'WarehouseDashboard::testDOCreation');
        }
    });

    // Delivery Orders
    $routes->group('delivery-orders', ['filter' => 'auth'], function($routes) {
        $routes->get('/', 'DeliveryOrders::index');
        $routes->get('shipped', 'DeliveryOrders::shipped');
        $routes->get('pending-followup', 'DeliveryOrders::pendingFollowup');
        $routes->get('view/(:segment)', 'DeliveryOrders::view/$1');
        $routes->get('progress/so/(:num)', 'DeliveryOrders::orderProgress/$1');
        $routes->post('create-from-sales-order/(:num)', 'DeliveryOrders::createFromSalesOrder/$1');
        $routes->post('update-qty/(:segment)', 'DeliveryOrders::updateQty/$1');
        $routes->post('confirm/(:segment)', 'DeliveryOrders::confirm/$1');
        $routes->post('add-tracking/(:segment)', 'DeliveryOrders::addTracking/$1');
        $routes->post('update-delivery-status/(:segment)', 'DeliveryOrders::updateDeliveryStatus/$1');
        $routes->post('update-estimated-days/(:segment)', 'DeliveryOrders::updateEstimatedDays/$1');
        $routes->post('upload-parcel-image/(:segment)', 'DeliveryOrders::uploadParcelImage/$1');
        $routes->post('delete-parcel-image/(:segment)', 'DeliveryOrders::deleteParcelImage/$1');
        $routes->post('upload-tracking-doc/(:segment)', 'DeliveryOrders::uploadTrackingDoc/$1');
        $routes->match(['POST','DELETE'], 'delete-tracking-doc/(:segment)', 'DeliveryOrders::deleteTrackingDoc/$1');
        $routes->post('quick-vendor', 'DeliveryOrders::quickVendor');
        $routes->post('quick-service', 'DeliveryOrders::quickService');
        $routes->post('create-shipping-po/(:segment)', 'DeliveryOrders::createShippingPoManual/$1');
        $routes->match(['POST','DELETE'], 'delete/(:segment)', 'DeliveryOrders::delete/$1');
    });
    
    // Components & Inventory Management
    $routes->group('components', function($routes) {
        $routes->get('/', 'Components::index');
        $routes->get('create', 'Components::create');
        $routes->post('create', 'Components::store');
        $routes->get('(:num)', 'Components::show/$1');
        $routes->get('(:num)/edit', 'Components::edit/$1');
        $routes->post('(:num)/edit', 'Components::update/$1');
        $routes->delete('(:num)', 'Components::delete/$1');
        $routes->get('export', 'Components::export');
        $routes->post('import', 'Components::import');
        $routes->get('(:num)/history', 'Components::history/$1');
    });
    
    // Inventory Management
    $routes->group('inventory', function($routes) {
        $routes->get('transactions', 'Components::transactions');
        $routes->post('transaction', 'Components::addTransaction');
        $routes->post('quick-adjust', 'Components::quickAdjust');
        $routes->get('stock-take', 'Components::stockTake');
        $routes->post('stock-take', 'Components::processStockTake');
        $routes->get('adjustments', 'StockAdjustments::index');
        $routes->post('adjustments/save', 'StockAdjustments::save');
        $routes->get('adjustments/history', 'StockAdjustments::history');
        $routes->get('adjustments/product-search', 'StockAdjustments::productSearch');
        $routes->get('adjustments/warehouses',       'StockAdjustments::warehouses');
        $routes->get('adjustments/stock-balance',    'StockAdjustments::stockBalance');
        $routes->get('adjustments/product-vendors',  'StockAdjustments::productVendors');
        $routes->get('adjustments/vendor-search',      'StockAdjustments::vendorSearch');

        // Internal Stock Transfers
        $routes->get('transfers',                     'InternalTransfers::index');
        $routes->get('transfers/create',              'InternalTransfers::create');
        $routes->post('transfers/store',              'InternalTransfers::store');
        $routes->get('transfers/product-search',      'InternalTransfers::productSearch');
        $routes->get('transfers/location-stock',      'InternalTransfers::locationStock');
        $routes->get('transfers/api/locations',       'InternalTransfers::apiLocations');
        $routes->get('transfers/(:num)',              'InternalTransfers::show/$1');

        $routes->get('reports', 'Components::inventoryReports');
    });
    
    // Quality Control Management
    $routes->group('quality-control', function($routes) {
        $routes->get('/', 'QualityControl::index');
        $routes->get('create', 'QualityControl::create');
        $routes->post('create', 'QualityControl::store');
        $routes->get('(:num)', 'QualityControl::show/$1');
        $routes->get('(:num)/edit', 'QualityControl::edit/$1');
        $routes->post('(:num)/edit', 'QualityControl::update/$1');
        $routes->delete('(:num)', 'QualityControl::delete/$1');
        $routes->get('export', 'QualityControl::export');
        $routes->get('templates', 'QualityControl::templates');
        $routes->post('templates', 'QualityControl::saveTemplate');
        $routes->get('reports', 'QualityControl::reports');
        $routes->get('(:num)/certificate', 'QualityControl::certificate/$1');
    });
    
    // Product Ledger (purchase & sales history per product)
    $routes->get('product-ledger', 'ProductLedger::index');
    $routes->get('product-ledger/(:num)', 'ProductLedger::index/$1');
    $routes->get('product-ledger/api/history', 'ProductLedger::history');
    $routes->get('product-ledger/api/search', 'ProductLedger::searchProducts');
    $routes->get('product-ledger/api/vendors', 'ProductLedger::vendors');
    $routes->get('product-ledger/api/customers', 'ProductLedger::customers');

    // Reports & Analytics
    $routes->group('reports', function($routes) {
        $routes->get('/', 'Reports::index');
        // Existing report pages (some may be placeholders)
        $routes->get('production', 'Reports::production');
        $routes->get('quality', 'Reports::quality');
        $routes->get('inventory', 'Reports::inventory');
        $routes->get('financial', 'Reports::financial');
        $routes->get('vendor', 'Reports::vendor');
        $routes->get('custom', 'Reports::custom');
        $routes->post('generate', 'Reports::generate');
        $routes->get('export/(:any)', 'Reports::export/$1');
        $routes->post('schedule', 'Reports::schedule');
        $routes->get('templates', 'Reports::templates');
        $routes->post('templates', 'Reports::saveTemplate');

        // Deterministic forecasting JSON endpoints
        $routes->get('forecast/summary', 'Reports::forecastSummary');
        $routes->get('forecast/bottlenecks', 'Reports::forecastBottlenecks');
        $routes->get('forecast/burnup', 'Reports::forecastBurnup');
        $routes->get('forecast/risks', 'Reports::forecastRisks');
    });
    
    // Ultra-simple backup journal (completely independent) - OUTSIDE accounting group
    $routes->get('simple-journal', 'SimpleJournal::index');
    $routes->post('simple-journal', 'SimpleJournal::post');
    
    // Trial Balance (standalone)
    $routes->get('accounting/trial-balance', 'TrialBalanceNew::index');
    $routes->get('accounting/trial-balance-new', 'TrialBalanceNew::index');
    
    // Accounting
    $routes->group('accounting', function($routes) {
        $routes->get('/', 'AccountingDashboard::index'); // Dashboard as main entry point
        $routes->get('dashboard', 'AccountingDashboard::index');
        $routes->get('journals', 'AccountingJournals::index');
        $routes->get('journals/quick', 'AccountingJournals::quick');
        $routes->post('journals/quick', 'AccountingJournals::postQuick');
        $routes->get('journals/diag', 'AccountingJournals::diag');
    // Printable journal receipt/voucher (grouped route) — ensures /accounting/journals/receipt/{id} resolves
    $routes->get('journals/receipt/(:num)', 'AccountingJournals::receipt/$1');
    // Journal entry detail view (with attachments section)
    $routes->get('journals/view/(:num)', 'AccountingJournals::view/$1');
    if (ENVIRONMENT !== 'production') {
        $routes->get('journals/debug-insert', 'AccountingJournals::debugInsert');
    }
    // Cheques
    $routes->get('cheques', 'AccountingCheques::index');
    $routes->get('cheques/create', 'AccountingCheques::create');
    $routes->post('cheques/store', 'AccountingCheques::store');
    $routes->get('cheques/vendor-contacts/(:num)', 'AccountingCheques::vendorContacts/$1');
    // View Balance helpers
    $routes->get('cheques/balances', 'AccountingCheques::balances');
    $routes->get('cheques/balanceData', 'AccountingCheques::balanceData');
    $routes->get('cheques/balanceEntries', 'AccountingCheques::balanceEntries');
    // Advance cheque features (first route group)
    $routes->get('cheques/adjust-advance', 'AccountingCheques::adjustAdvance');
    $routes->get('cheques/vendorAdvanceData', 'AccountingCheques::vendorAdvanceData');
    $routes->get('cheques/vendorAdvanceBalance', 'AccountingCheques::vendorAdvanceBalance');
    $routes->post('cheques/applyAdvance', 'AccountingCheques::applyAdvance');
        // Minimal fallback journal entry form and list
        $routes->get('journal-lite', 'AccountingJournalLite::index');
        $routes->post('journal-lite', 'AccountingJournalLite::post');
        if (ENVIRONMENT !== 'production') {
            $routes->get('journal-lite/debug', 'AccountingJournalLite::debug');
            $routes->get('journal-lite/test-insert', 'AccountingJournalLite::testInsert');
            $routes->match(['GET', 'POST'], 'journal-lite/test-post', 'AccountingJournalLite::testPost');
            $routes->get('journal-lite/test', 'AccountingJournalLite::test');
            $routes->get('journal-lite/clean-db', 'AccountingJournalLite::cleanDatabase');
        }
    // JSON helper for autocomplete
    $routes->get('journal-lite/accounts', 'AccountingJournalLite::accountsJson');
        $routes->get('accounts', 'AccountingAccounts::index');
        $routes->post('accounts/create', 'AccountingAccounts::create');
        $routes->get('accounts/(:num)/edit', 'AccountingAccounts::edit/$1');
        $routes->post('accounts/(:num)/update', 'AccountingAccounts::update/$1');
        $routes->post('accounts/reparent', 'AccountingAccounts::reparent');
    $routes->match(['GET','POST'],'accounts/auto-assign-parents','AccountingAccounts::autoAssignParents');
    $routes->post('accounts/(:num)/delete','AccountingAccounts::delete/$1');
    $routes->post('accounts/(:num)/merge','AccountingAccounts::merge/$1');
    $routes->post('accounts/(:num)/deactivate','AccountingAccounts::deactivate/$1');
        // Accounting reports
        $routes->get('reports/trial-balance', 'AccountingReports::trialBalance');
        $routes->get('reports/income-statement', 'AccountingReports::incomeStatement');
        $routes->get('reports/balance-sheet', 'AccountingReports::balanceSheet');
        // Credit Notes & Purchase Orders
        $routes->get('credit-notes', 'AccountingCreditNotes::index');
        $routes->post('credit-notes/create', 'AccountingCreditNotes::create');
        $routes->get('purchase-orders', 'AccountingPurchaseOrders::index');
        $routes->get('purchase-orders/(:num)/pdf', 'AccountingPurchaseOrders::pdf/$1');
        $routes->post('purchase-orders/create', 'AccountingPurchaseOrders::create');

    // Cheques
    $routes->get('cheques', 'AccountingCheques::index');
    $routes->get('cheques/create', 'AccountingCheques::create');
    $routes->post('cheques/store', 'AccountingCheques::store');
    $routes->get('cheques/vendor-contacts/(:num)', 'AccountingCheques::vendorContacts/$1');
    // Receipts
    $routes->get('receipts/(:num)', 'AccountingReceipts::show/$1');
    // Printable journal receipt/voucher (handled by accounting group 'journals/receipt')
    $routes->get('cheques/vendor-bills/(:num)', 'AccountingCheques::vendorBills/$1');
    $routes->get('cheques/balances', 'AccountingCheques::balances');
    $routes->get('cheques/balanceData', 'AccountingCheques::balanceData');
    $routes->get('cheques/balanceEntries', 'AccountingCheques::balanceEntries');
    $routes->get('cheques/entrySummary', 'AccountingCheques::entrySummary');
    $routes->get('cheques/paymentBreakdown', 'AccountingCheques::paymentBreakdown');
    // Advance cheque features
    $routes->get('cheques/adjust-advance', 'AccountingCheques::adjustAdvance');
    $routes->get('cheques/vendorAdvanceData', 'AccountingCheques::vendorAdvanceData');
    $routes->get('cheques/vendorAdvanceBalance', 'AccountingCheques::vendorAdvanceBalance');
    $routes->post('cheques/applyAdvance', 'AccountingCheques::applyAdvance');
    $routes->get('cheques/(:num)/view', 'AccountingCheques::view/$1');
    $routes->get('cheques/(:num)/pdf', 'AccountingCheques::pdf/$1');
        $routes->get('vendor-payments/pay', 'AccountingVendorPayments::pay');
        $routes->get('vendor-payments', 'AccountingVendorPayments::index');
        $routes->get('vendor-payments/(:num)', 'AccountingVendorPayments::view/$1');
        $routes->get('vendor-payments/(:num)/edit', 'AccountingVendorPayments::edit/$1');
        $routes->post('vendor-payments/draft', 'AccountingVendorPayments::createDraft');
        $routes->post('vendor-payments/update', 'AccountingVendorPayments::updateDraft');
        $routes->post('vendor-payments/confirm', 'AccountingVendorPayments::confirm');
        $routes->post('vendor-payments/attachment/delete', 'AccountingVendorPayments::deleteAttachment');
        // Customer payments (Receive payments)
        $routes->get('customer-payments/pay', 'AccountingCustomerPayments::pay');
        $routes->get('customer-payments', 'AccountingCustomerPayments::index');
        $routes->get('customer-payments/(:num)', 'AccountingCustomerPayments::view/$1');
        $routes->get('customer-payments/open-invoices/(:num)', 'AccountingCustomerPayments::openInvoices/$1');
        $routes->get('customer-payments/(:num)/edit', 'AccountingCustomerPayments::edit/$1');
        $routes->post('customer-payments/(:num)/delete', 'AccountingCustomerPayments::delete/$1');
        // Compatibility: allow explicit 'view' segment in URLs (some links use /view/{id})
        $routes->get('customer-payments/view/(:num)', 'AccountingCustomerPayments::view/$1');
        $routes->post('customer-payments/draft', 'AccountingCustomerPayments::createDraft');
        // Backwards-compatible alias: some clients/pages call createDraft
        $routes->post('customer-payments/createDraft', 'AccountingCustomerPayments::createDraft');
        $routes->post('customer-payments/confirm', 'AccountingCustomerPayments::confirm');
    });
    
    // User Management (Admin only)
    $routes->group('users', ['filter' => 'role:admin'], function($routes) {
        $routes->get('/', 'Users::index');
        $routes->get('create', 'Users::create');
        $routes->post('create', 'Users::store');
        $routes->get('(:num)', 'Users::show/$1');
        $routes->get('(:num)/edit', 'Users::edit/$1');
        $routes->post('(:num)/edit', 'Users::update/$1');
        $routes->delete('(:num)', 'Users::delete/$1');
        $routes->get('roles', 'Users::roles');
        $routes->post('roles', 'Users::updateRoles');
        $routes->get('permissions', 'Users::permissions');
        $routes->post('permissions', 'Users::updatePermissions');
    });
    
    // Settings & Configuration (Admin only)
    $routes->group('settings', ['filter' => 'role:admin'], function($routes) {
        $routes->get('/', 'Settings::index');
        // New save endpoints matching this module
        $routes->post('saveCompany', 'Settings::saveCompany');
        $routes->post('saveFiscalYear', 'Settings::saveFiscalYear');
        $routes->post('saveSecurity', 'Settings::saveSecurity');
        $routes->post('addPaymentMethod', 'Settings::addPaymentMethod');
        $routes->post('addExchangeRate', 'Settings::addExchangeRate');
        $routes->post('saveOdoo', 'Settings::saveOdoo');
        $routes->post('cleanDatabase', 'Settings::cleanDatabase');
        $routes->post('saveNetwork', 'Settings::saveNetwork');
        $routes->post('saveMobileSettings', 'Settings::saveMobileSettings');
        $routes->post('saveDateFormat', 'Settings::saveDateFormat');
    });

    // Integrations (Odoo)
    $routes->group('integrations/odoo', function($routes) {
        $routes->get('/', 'OdooController::index');
        $routes->get('api/sales', 'OdooController::apiSales');
        $routes->get('api/purchases', 'OdooController::apiPurchases');
        $routes->get('api/test', 'OdooController::apiTest');
        $routes->get('api/customers', 'OdooController::apiCustomers');
        $routes->post('import/customers', 'OdooController::importCustomers');
        // Screen & aggregated endpoints
        $routes->get('screen', 'OdooController::screenView');
        $routes->get('screen/api/summary', 'OdooController::screenSummary');
        $routes->get('screen/api/pending', 'OdooController::screenPending');
        $routes->get('screen/api/ready', 'OdooController::screenReady');
        $routes->get('screen/debug/sale_lines', 'OdooController::screenDebugSaleLines');
    $routes->get('screen/product_image', 'OdooController::screenProductImage');
    $routes->get('screen/api/sales', 'OdooController::screenApiSales');
        $routes->get('screen/api/purchases', 'OdooController::screenPurchases');
        $routes->get('screen/api/alerts', 'OdooController::screenAlerts');
        $routes->post('screen/action/claim', 'OdooController::screenActionClaim');
        $routes->post('screen/action/close', 'OdooController::screenActionClose');
    $routes->post('screen/action/refresh', 'OdooController::screenActionRefresh');
    $routes->post('screen/action/pull', 'OdooController::screenActionPull');
    });
    
    // API Routes
    $routes->group('api/v1', function($routes) {
        // Dashboard API
        $routes->get('dashboard/stats', 'Api\Dashboard::stats');
        $routes->get('dashboard/charts/(:any)', 'Api\Dashboard::chartData/$1');
        
        // Production API
        $routes->get('work-orders', 'Api\WorkOrders::index');
        $routes->get('work-orders/(:num)', 'Api\WorkOrders::show/$1');
        $routes->post('work-orders/(:num)/status', 'Api\WorkOrders::updateStatus/$1');
        
        // Quality API
        $routes->get('quality/stats', 'Api\QualityControl::stats');
        $routes->post('quality/quick-check', 'Api\QualityControl::quickCheck');
        
        // Inventory API
        $routes->get('inventory/alerts', 'Api\Components::alerts');
        $routes->get('inventory/(:num)/stock', 'Api\Components::stockLevel/$1');
    $routes->get('inventory/stock', 'InventoryStock::api');
        $routes->post('inventory/(:num)/adjust', 'Api\Components::adjustStock/$1');
        
        // Notifications API
        $routes->get('notifications', 'Api\Notifications::index');
        $routes->post('notifications/(:num)/read', 'Api\Notifications::markRead/$1');
        $routes->post('notifications/read-all', 'Api\Notifications::markAllRead');
    });
    
    // Profile Management
    $routes->get('profile', 'Users::profile');
    $routes->post('profile', 'Users::updateProfile');
    $routes->post('profile/password', 'Users::changePassword');
    $routes->post('profile/avatar', 'Users::uploadAvatar');
    
    // Notifications
    $routes->get('notifications', 'Notifications::index');
    $routes->post('notifications/(:num)/read', 'Notifications::markRead/$1');
    $routes->post('notifications/read-all', 'Notifications::markAllRead');
    
    // File uploads and downloads
    $routes->post('upload/(:any)', 'Files::upload/$1');
    $routes->get('download/(:any)', 'Files::download/$1');
    $routes->delete('files/(:num)', 'Files::delete/$1');
    
    // Search functionality
    $routes->get('search', 'Search::index');
    $routes->get('search/(:any)', 'Search::query/$1');

    // Document Activity Log API
    $routes->get('activity-log/(:alpha)/(:num)', 'ActivityLog::forDocument/$1/$2', ['filter' => 'auth']);
    
    // Help and Documentation
    $routes->get('help', 'Help::index');
    $routes->get('help/(:any)', 'Help::topic/$1');

    // Integrations
    $routes->group('integrations', function($routes) {
        $routes->group('odoo', function($routes) {
            $routes->get('/', 'OdooController::index');
            $routes->get('api/sales', 'OdooController::apiSales');
            $routes->get('api/purchases', 'OdooController::apiPurchases');
        });
    });
});

// Error pages
$routes->get('404', 'Errors::show404');
$routes->get('403', 'Errors::show403');
$routes->get('500', 'Errors::show500');

// Products routes (explicit - works even if auto routing is disabled)
$routes->group('products', static function ($routes) {
	$routes->get('/', 'Products::index');
	$routes->get('create', 'Products::create');
});
$routes->post('products/store', 'Products::store');
// Product variants update (AJAX)
$routes->post('product-variants/(:num)/update', 'ProductVariants::update/$1');
// Product variants list
$routes->get('product-variants', 'ProductVariants::index');
// Product variant edit page
$routes->get('product-variants/(:num)/edit', 'ProductVariants::edit/$1');

//New purchase module rotes
$routes->get('newpurchaseui/rfqpo', 'NewPurchaseUI::rfqpo');
$routes->get('newpurchaseui/rfqs', 'NewPurchaseUI::rfqs');
$routes->get('newpurchaseui/pos', 'NewPurchaseUI::pos');
$routes->get('newpurchaseui/po/(:segment)', 'NewPurchaseUI::po/$1');
$routes->get('newpurchaseui/grn', 'NewPurchaseUI::grn');
// Friendly purchase detail aliases
$routes->get('purchases/po/(:segment)', 'NewPurchaseUI::po/$1');
$routes->get('purchases/rfq/(:segment)', 'NewPurchaseUI::rfq/$1');
// API endpoint for creating RFQs (match UI POST path exactly)
$routes->post('new-purchase-rfqs/create', 'NewPurchaseRfqs::create');
// RFQ lifecycle and listing
$routes->get('new-purchase-rfqs/next-number', 'NewPurchaseRfqs::nextNumber');
$routes->get('new-purchase-rfqs', 'NewPurchaseRfqs::index');
$routes->get('new-purchase-rfqs/(:segment)', 'NewPurchaseRfqs::show/$1');
$routes->get('new-purchase-rfqs/(:segment)/pdf', 'NewPurchaseRfqs::pdf/$1');
$routes->post('new-purchase-rfqs/(:segment)/update', 'NewPurchaseRfqs::update/$1');
$routes->post('new-purchase-rfqs/(:segment)/confirm', 'NewPurchaseRfqs::confirm/$1');
$routes->post('new-purchase-rfqs/(:segment)/send', 'NewPurchaseRfqs::send/$1');
$routes->post('new-purchase-rfqs/(:segment)/accept', 'NewPurchaseRfqs::accept/$1');
$routes->post('new-purchase-rfqs/(:segment)/cancel', 'NewPurchaseRfqs::cancel/$1');

// PO from RFQ conversion
$routes->post('new-purchase-orders/from-rfq/(:num)', 'NewPurchaseOrders::from_rfq/$1');

// PO detail, update and cancel endpoints used by UI
$routes->get('new-purchase-orders/(:segment)', 'NewPurchaseOrders::show/$1');
$routes->get('new-purchase-orders/(:segment)/pdf', 'NewPurchaseOrders::pdf/$1');
$routes->post('new-purchase-orders/(:segment)/update', 'NewPurchaseOrders::update/$1');
$routes->post('new-purchase-orders/(:segment)/set-draft', 'NewPurchaseOrders::setDraft/$1');
$routes->post('new-purchase-orders/(:segment)/cancel', 'NewPurchaseOrders::cancel/$1');
$routes->post('new-purchase-orders/(:segment)/close', 'NewPurchaseOrders::close/$1');

// PO Advance Payment
$routes->post('new-purchase-orders/pay-advance/(:num)', 'NewPurchaseOrders::payAdvance/$1');
$routes->get('new-purchase-orders/advance-info/(:num)', 'NewPurchaseOrders::getAdvanceInfo/$1');

// Create vendor bill from PO
$routes->post('new-purchase-orders/create-bill/(:num)', 'NewPurchaseOrders::createBill/$1');
$routes->post('new-purchase-orders/create-extra-bill/(:num)', 'NewPurchaseOrders::createExtraBill/$1');

// Delete draft/pending PO from the combined RFQ/PO UI
$routes->post('new-purchase-orders/(:segment)/delete', 'NewPurchaseOrders::delete/$1');

// Confirm a PO (change draft -> confirmed) from UI
$routes->post('new-purchase-orders/confirm/(:segment)', 'NewPurchaseOrders::confirm/$1');

// PO listing for UI
$routes->get('new-purchase-orders', 'NewPurchaseOrders::index');

// GRN helpers
$routes->get('new-purchase-grns/eligible-pos', 'NewPurchaseGrns::eligible_pos');
$routes->get('new-purchase-grns/po-lines/(:num)', 'NewPurchaseGrns::po_lines/$1');
$routes->get('new-purchase-grns/pending-lines', 'NewPurchaseGrns::pending_lines');
$routes->get('new-purchase-grns/product-variants/(:num)', 'NewPurchaseGrns::product_variants/$1');
$routes->get('new-purchase-grns/next-number', 'NewPurchaseGrns::next_number');
$routes->get('new-purchase-grns/locations', 'NewPurchaseGrns::locations');
$routes->post('new-purchase-grns/locations', 'NewPurchaseGrns::create_location');
$routes->post('new-purchase-grns/create', 'NewPurchaseGrns::create');
$routes->post('new-purchase-grns/(:num)/lines/(:num)/issue', 'NewPurchaseGrns::issue/$1/$2');
$routes->get('new-purchase-grns/list', 'NewPurchaseGrns::list');
$routes->get('new-purchase-grns/detail/(:segment)', 'NewPurchaseGrns::detail/$1');

 // Inventory: warehouses & locations management
 $routes->get('inventory/locations', 'InventoryLocations::index');
 $routes->get('inventory/stock', 'InventoryStock::index');
 $routes->get('inventory/stock/data', 'InventoryStock::api');
 $routes->get('inventory/stock/product/(:num)', 'InventoryStock::product/$1');
 $routes->get('inventory/journal', 'InventoryStock::journal');
 $routes->get('inventory/product-autocomplete', 'InventoryStock::product_autocomplete');
 $routes->get('inventory/warehouses', 'InventoryLocations::warehouses');
 $routes->get('inventory/warehouses/(:num)', 'InventoryLocations::warehouse/$1');
 $routes->post('inventory/warehouses/save', 'InventoryLocations::save_warehouse');
 $routes->post('inventory/warehouses/(:num)/deactivate', 'InventoryLocations::deactivate_warehouse/$1');
 $routes->post('inventory/warehouses/(:num)/locations', 'InventoryLocations::create_location/$1');
 $routes->post('inventory/locations/(:num)/deactivate', 'InventoryLocations::deactivate_location/$1');
 $routes->post('inventory/locations/(:num)/rename',     'InventoryLocations::rename_location/$1');
 $routes->post('inventory/locations/(:num)/move',       'InventoryLocations::move_location/$1');
 $routes->post('inventory/locations/(:num)/delete',     'InventoryLocations::delete_location/$1');
 $routes->get('inventory/locations/(:num)/stock-check', 'InventoryLocations::location_stock_check/$1');

// Vendor Ledger
$routes->get('vendors/ledger/(:num)', 'VendorLedger::index/$1');

// Vendor Bills
$routes->get('vendor-bills', 'VendorBills::index');
$routes->get('vendor-bills/(:segment)', 'VendorBills::show/$1');
$routes->post('vendor-bills/(:segment)/confirm', 'VendorBills::confirm/$1');
$routes->post('vendor-bills/(:segment)/update', 'VendorBills::update/$1');
$routes->post('vendor-bills/(:segment)/cancel', 'VendorBills::cancel/$1');

// GRN UI aliases (primary entry from PO and menu)
$routes->get('newpurchaseui/grn', 'NewPurchaseUI::grn');
$routes->get('purchases/grn', 'NewPurchaseUI::grn');
