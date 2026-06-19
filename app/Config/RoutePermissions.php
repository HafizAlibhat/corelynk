<?php

/**
 * Route → Permission Mapping
 * ===========================================================================
 * Maps URI patterns to required permission keys (module.action).
 *
 * Format:  'uri/pattern' => 'module.action'
 *
 * Wildcards:
 *   *   matches any single segment  (e.g. "products/*" matches "products/5")
 *   **  matches any number of segments (e.g. "accounting/**" matches
 *       "accounting/journals/receipt/42")
 *
 * The RbacFilter iterates top-to-bottom and uses the FIRST matching rule.
 * Place more specific patterns before broader ones.
 *
 * HTTP method awareness:
 *   By default the mapping applies to all methods.
 *   You may prefix with "GET:", "POST:", etc. for method-specific rules:
 *     'GET:products'     => 'products.read'
 *     'POST:products/*'  => 'products.write'
 *
 * FAIL-SAFE: If a route matches NO rule, access is DENIED (403).
 * Routes explicitly mapped to `null` or `true` are always allowed
 * (for authenticated users).
 * ===========================================================================
 */
return [

    // -----------------------------------------------------------------
    //  PUBLIC / ALWAYS ALLOWED (for authenticated users)
    // -----------------------------------------------------------------
    'dashboard'              => 'dashboard.read',
    'dashboard/stats'        => 'dashboard.read',
    'dashboard/charts/*'     => 'dashboard.read',
    'corelynk/fx-rates'      => 'dashboard.read',
    'corelynk/activity-center/feed' => 'dashboard.read',
    'POST:corelynk/activity-center/read/*' => 'dashboard.read',
    'POST:corelynk/activity-center/read-all' => 'dashboard.read',

    // Profile & personal settings — always allowed for logged-in users
    'logout'                 => true,
    'profile'                => true,
    'profile/**'             => true,
    'auth/**'                => true,
    'notifications'          => true,
    'notifications/**'       => true,
    'search'                 => true,
    'search/**'              => true,
    'help'                   => true,
    'help/**'                => true,
    'upload/**'              => true,
    'download/**'            => true,
    'files/**'               => true,
    'activity-log/**'        => true,

    // Error pages
    '404'                    => true,
    '403'                    => true,
    '500'                    => true,

    // -----------------------------------------------------------------
    //  POS
    // -----------------------------------------------------------------
    'pos'                        => 'pos.read',
    'pos/**'                     => 'pos.read',
    'POST:pos/save-order'        => 'pos.write',
    'POST:pos/void-order/*'      => 'pos.delete',

    // -----------------------------------------------------------------
    //  PRODUCTS
    // -----------------------------------------------------------------
    'GET:products'               => 'products.read',
    'GET:products/get-data'      => 'products.read',
    'GET:products/search'        => 'products.read',
    'GET:products/export'        => 'products.read',
    'GET:products/*/assets'          => 'product_assets.read',
    'GET:products/*/assets/data'     => 'product_assets.read',
    'POST:products/*/assets/groups'  => 'product_assets.edit',
    'POST:products/*/assets/channels' => 'product_assets.edit',
    'POST:products/*/assets/channels/*/update' => 'product_assets.edit',
    'POST:products/*/assets/channels/*/delete' => 'product_assets.delete',
    'POST:products/*/assets/upload'  => 'product_assets.edit',
    'POST:products/*/assets/listings' => 'product_assets.edit',
    'POST:products/*/assets/*/primary' => 'product_assets.edit',
    'POST:products/*/assets/*/update-file' => 'product_assets.edit',
    'POST:products/*/assets/*/attach-source' => 'product_assets.edit',
    'POST:products/*/assets/*/delete' => 'product_assets.delete',
    'POST:products/*/assets/*/move'  => 'product_assets.edit',
    'POST:products/*/assets/*/copy'  => 'product_assets.edit',
    'GET:products/*'             => 'products.read',
    'GET:product-assets'          => 'product_assets.read',
    'POST:product-assets/channels/store' => 'product_assets.edit',
    'GET:products/create'        => 'products.write',
    'POST:products/store'        => 'products.write',
    'POST:products/import'       => 'products.write',
    'POST:products/*/update'     => 'products.edit',
    'POST:products/**'           => 'products.edit',
    'DELETE:products/*'          => 'products.delete',

    // Product Variants
    'GET:product-variants'       => 'products.read',
    'GET:product-variants/**'    => 'products.read',
    'POST:product-variants/**'   => 'products.edit',
    'DELETE:product-variants/**' => 'products.delete',

    // Variant Exclusions
    'GET:variant-exclusions'       => 'products.read',
    'GET:variant-exclusions/**'    => 'products.read',
    'POST:variant-exclusions/**'   => 'products.edit',
    'DELETE:variant-exclusions/**' => 'products.delete',

    // Product Categories
    'GET:product-categories'       => 'products.read',
    'GET:product-categories/**'    => 'products.read',
    'POST:product-categories/**'   => 'products.edit',
    'DELETE:product-categories/**' => 'products.delete',

    // Product Attributes
    'GET:product-attributes'       => 'products.read',
    'GET:product-attributes/**'    => 'products.read',
    'POST:product-attributes/**'   => 'products.edit',
    'DELETE:product-attributes/**' => 'products.delete',

    // Product Attribute Values
    'GET:product-attribute-values'       => 'products.read',
    'GET:product-attribute-values/**'    => 'products.read',
    'POST:product-attribute-values/**'   => 'products.edit',

    // Product Attribute Assignments
    'GET:product-attribute-assignments'       => 'products.read',
    'GET:product-attribute-assignments/**'    => 'products.read',
    'POST:product-attribute-assignments/**'   => 'products.edit',

    // Product Vendors price lookup
    'GET:product-vendors/**'     => 'products.read',

    // Preparation Profiles
    'GET:preparation-profiles/**'  => 'products.read',
    'POST:preparation-profiles/**' => 'products.edit',

    // Product Ledger
    'GET:product-ledger'         => 'products.read',
    'GET:product-ledger/**'      => 'products.read',

    // -----------------------------------------------------------------
    //  CUSTOMERS
    // -----------------------------------------------------------------
    'GET:customers'              => 'customers.read',
    'GET:customers/*'            => 'customers.read',
    'GET:customers/*/edit'       => 'customers.edit',
    'GET:customers/create'       => 'customers.write',
    'POST:customers/create'      => 'customers.write',
    'POST:customers/api-create'  => 'customers.write',
    'POST:customers/*/edit'      => 'customers.edit',
    'POST:customers/*/toggle-status' => 'customers.edit',
    'POST:customers/**'          => 'customers.edit',
    'GET:customers/*/delete'     => 'customers.delete',
    'POST:customers/*/delete'    => 'customers.delete',

    // -----------------------------------------------------------------
    //  QUOTATIONS
    // -----------------------------------------------------------------
    'GET:quotations'             => 'quotations.read',
    'GET:quotations/view/*'      => 'quotations.read',
    'GET:quotations/pdf/*'       => 'quotations.read',
    'GET:quotations/edit/*'      => 'quotations.edit',
    'GET:quotations/create'      => 'quotations.write',
    'POST:quotations/create'     => 'quotations.write',
    'POST:quotations/api/create' => 'quotations.write',
    'POST:quotations/update/*'   => 'quotations.edit',
    'POST:quotations/update-line/*'    => 'quotations.edit',
    'POST:quotations/add-line/*'       => 'quotations.edit',
    'DELETE:quotations/delete-line/*'  => 'quotations.edit',
    'POST:quotations/delete-line/*'    => 'quotations.edit',
    'POST:quotations/update-shipping/*'=> 'quotations.edit',
    'quotations/delete/*'        => 'quotations.delete',
    'GET:quotations/search-products'   => 'quotations.read',
    'GET:quotations/search-customers'  => 'quotations.read',
    'GET:quotations/price-lists/*'     => 'quotations.read',
    'POST:quotations/calculate'        => 'quotations.read',

    // Documents (unified list)
    'GET:documents'              => 'sales_orders.read',
    'GET:documents/**'           => 'sales_orders.read',
    'GET:documents/order-alert-state' => 'sales_orders.read',
    'POST:documents/acknowledge-orders-alarm' => 'sales_orders.read',

    // Document Studio
    'GET:document-studio'        => 'quotations.read',
    'GET:document-studio/**'     => 'quotations.read',
    'POST:document-studio/save-quotation'     => 'quotations.write',
    'POST:document-studio/save-sales-order'   => 'sales_orders.write',
    'POST:document-studio/save-rfq'           => 'rfq.write',
    'POST:document-studio/save-purchase-order'=> 'purchase_orders.write',
    'POST:document-studio/**'                 => 'quotations.edit',

    // -----------------------------------------------------------------
    //  SALES ORDERS
    // -----------------------------------------------------------------
    'GET:sales-orders'           => 'sales_orders.read',
    'GET:sales-orders/view/*'    => 'sales_orders.read',
    'GET:sales-orders/pdf/*'     => 'sales_orders.read',
    'GET:sales-orders/warehouse-print/*' => 'sales_orders.read',
    'GET:sales-orders/create'    => 'sales_orders.write',
    'POST:sales-orders/create'   => 'sales_orders.write',
    'GET:sales-orders/create-from-quotation/*' => 'sales_orders.write',
    'GET:sales-orders/invoice/*' => 'sales_orders.read',
    'POST:sales-orders/cancel/*' => 'sales_orders.edit',
    'POST:sales-orders/reset-to-quotation/*' => 'sales_orders.edit',
    'POST:sales-orders/create-purchase-drafts/*' => 'purchase_orders.write',
    'POST:sales-orders/preparation/send-to-vendor' => 'sales_orders.edit',
    'POST:sales-orders/preparation/start-inhouse' => 'sales_orders.edit',

    // -----------------------------------------------------------------
    //  CUSTOMER INVOICES
    // -----------------------------------------------------------------
    'GET:customer-invoices'             => 'invoices.read',
    'GET:customer-invoices/**'          => 'invoices.read',
    'POST:customer-invoices/post/*'     => 'invoices.write',
    'POST:customer-invoices/update/*'   => 'invoices.edit',
    'POST:customer-invoices/**'         => 'invoices.write',

    // -----------------------------------------------------------------
    //  VENDORS
    // -----------------------------------------------------------------
    'GET:vendors'                => 'vendors.read',
    'GET:vendors/search'         => 'vendors.read',
    'GET:vendors/export'         => 'vendors.read',
    'GET:vendors/*'              => 'vendors.read',
    'GET:vendors/create'         => 'vendors.write',
    'POST:vendors/store'         => 'vendors.write',
    'POST:vendors/update/*'      => 'vendors.edit',
    'POST:vendors/**'            => 'vendors.edit',
    'DELETE:vendors/*'           => 'vendors.delete',
    'GET:vendors/ledger/*'       => 'vendors.read',

    // Vendor Bills
    'GET:vendor-bills'           => 'vendors.read',
    'GET:vendor-bills/*'         => 'vendors.read',
    'POST:vendor-bills/**'       => 'vendors.edit',

    // Vendor Receive + QC
    'GET:vendor-receive'         => 'grn.read',
    'GET:vendor-receive/*'       => 'grn.read',
    'POST:vendor-receive/store'  => 'sales_orders.edit',

    // -----------------------------------------------------------------
    //  PURCHASES / RFQ / PO
    // -----------------------------------------------------------------
    // New Purchase UI
    'GET:newpurchaseui/**'       => 'purchase_orders.read',
    'GET:purchases/**'           => 'purchase_orders.read',

    // RFQs
    'GET:new-purchase-rfqs'            => 'rfq.read',
    'GET:new-purchase-rfqs/next-number'=> 'rfq.read',
    'GET:new-purchase-rfqs/*'          => 'rfq.read',
    'GET:new-purchase-rfqs/*/pdf'      => 'rfq.read',
    'POST:new-purchase-rfqs/create'    => 'rfq.write',
    'POST:new-purchase-rfqs/*/update'  => 'rfq.edit',
    'POST:new-purchase-rfqs/*/confirm' => 'rfq.edit',
    'POST:new-purchase-rfqs/*/send'    => 'rfq.edit',
    'POST:new-purchase-rfqs/*/accept'  => 'rfq.edit',
    'POST:new-purchase-rfqs/*/cancel'  => 'rfq.edit',

    // Purchase Orders
    'GET:new-purchase-orders'          => 'purchase_orders.read',
    'GET:new-purchase-orders/*'        => 'purchase_orders.read',
    'GET:new-purchase-orders/*/pdf'    => 'purchase_orders.read',
    'POST:new-purchase-orders/from-rfq/*'    => 'purchase_orders.write',
    'POST:new-purchase-orders/*/update'      => 'purchase_orders.edit',
    'POST:new-purchase-orders/*/set-draft'   => 'purchase_orders.edit',
    'POST:new-purchase-orders/*/cancel'      => 'purchase_orders.edit',
    'POST:new-purchase-orders/*/close'       => 'purchase_orders.edit',
    'POST:new-purchase-orders/confirm/*'     => 'purchase_orders.edit',
    'POST:new-purchase-orders/*/delete'      => 'purchase_orders.delete',
    'POST:new-purchase-orders/pay-advance/*' => 'purchase_orders.edit',
    'GET:new-purchase-orders/advance-info/*' => 'purchase_orders.read',
    'POST:new-purchase-orders/create-bill/*' => 'purchase_orders.edit',
    'POST:new-purchase-orders/create-extra-bill/*' => 'purchase_orders.edit',

    // GRN
    'GET:new-purchase-grns/**'   => 'grn.read',
    'POST:new-purchase-grns/**'  => 'grn.write',

    // -----------------------------------------------------------------
    //  WAREHOUSE
    // -----------------------------------------------------------------
    'GET:warehouse/**'           => 'delivery_orders.read',

    // Delivery Orders
    'GET:delivery-orders'        => 'delivery_orders.read',
    'GET:delivery-orders/**'     => 'delivery_orders.read',
    'POST:delivery-orders/create-from-sales-order/*' => 'delivery_orders.write',
    'POST:delivery-orders/confirm/*'    => 'delivery_orders.edit',
    'POST:delivery-orders/**'           => 'delivery_orders.edit',
    'DELETE:delivery-orders/*'          => 'delivery_orders.delete',

    // -----------------------------------------------------------------
    //  INVENTORY
    // -----------------------------------------------------------------
    'GET:inventory/**'           => 'inventory.read',
    'POST:inventory/transaction' => 'inventory.write',
    'POST:inventory/quick-adjust'=> 'inventory.edit',
    'POST:inventory/stock-take'  => 'inventory.edit',
    'POST:inventory/adjustments/save'  => 'inventory.edit',
    'POST:inventory/**'          => 'inventory.edit',

    // Components
    'GET:components'             => 'inventory.read',
    'GET:components/*'           => 'inventory.read',
    'GET:components/*/edit'      => 'inventory.read',
    'GET:components/create'      => 'inventory.write',
    'POST:components/create'     => 'inventory.write',
    'POST:components/*/edit'     => 'inventory.edit',
    'POST:components/import'     => 'inventory.write',
    'DELETE:components/*'        => 'inventory.delete',
    'GET:components/export'      => 'inventory.read',

    // -----------------------------------------------------------------
    //  PRODUCTION / WORK ORDERS
    // -----------------------------------------------------------------
    'GET:work-orders'            => 'work_orders.read',
    'GET:work-orders/schedule'   => 'work_orders.read',
    'GET:work-orders/export'     => 'work_orders.read',
    'GET:work-orders/*'          => 'work_orders.read',
    'GET:work-orders/create'     => 'work_orders.write',
    'POST:work-orders/create'    => 'work_orders.write',
    'POST:work-orders/*/edit'    => 'work_orders.edit',
    'POST:work-orders/**'        => 'work_orders.edit',
    'DELETE:work-orders/*'       => 'work_orders.delete',

    // Processes
    'GET:processes'              => 'work_orders.read',
    'GET:processes/**'           => 'work_orders.read',
    'GET:processes/create'       => 'work_orders.write',
    'POST:processes/**'          => 'work_orders.edit',
    'DELETE:processes/*'         => 'work_orders.delete',

    // Process Templates / Categories
    'GET:process-templates'      => 'work_orders.read',
    'GET:process-templates/**'   => 'work_orders.read',
    'POST:process-templates/**'  => 'work_orders.edit',
    'DELETE:process-templates/*' => 'work_orders.delete',

    'GET:process-categories'     => 'work_orders.read',
    'GET:process-categories/**'  => 'work_orders.read',
    'POST:process-categories/**' => 'work_orders.edit',
    'DELETE:process-categories/*'=> 'work_orders.delete',

    // Batches
    'GET:batches'                => 'work_orders.read',
    'GET:batches/**'             => 'work_orders.read',
    'POST:batches/**'            => 'work_orders.edit',
    'DELETE:batches/*'           => 'work_orders.delete',

    // Production Logs
    'GET:production'             => 'work_orders.read',
    'GET:production/**'          => 'work_orders.read',
    'POST:production/**'         => 'work_orders.edit',

    // Logs module
    'GET:logs'                   => 'work_orders.read',
    'GET:logs/**'                => 'work_orders.read',
    'POST:logs/**'               => 'work_orders.edit',

    // Gate Passes
    'GET:gate_passes'            => 'work_orders.read',
    'GET:gate_passes/**'         => 'work_orders.read',
    'POST:gate_passes/**'        => 'work_orders.edit',
    'DELETE:gate_passes/*'       => 'work_orders.delete',

    // Workflow Templates
    'GET:workflow-templates'     => 'work_orders.read',
    'GET:workflow-templates/**'  => 'work_orders.read',
    'POST:workflow-templates/**' => 'work_orders.edit',
    'DELETE:workflow-templates/*'=> 'work_orders.delete',

    // Subcontract Orders
    'GET:subcontract-orders'     => 'purchase_orders.read',
    'GET:subcontract-orders/**'  => 'purchase_orders.read',
    'POST:subcontract-orders/**' => 'purchase_orders.edit',

    // PDFs
    'GET:pdfs/**'                => 'work_orders.read',

    // -----------------------------------------------------------------
    //  EMPLOYEES / HR
    // -----------------------------------------------------------------
    'GET:employees'              => 'work_orders.read',
    'GET:employees/**'           => 'work_orders.read',
    'POST:employees/**'          => 'work_orders.edit',
    'DELETE:employees/*'         => 'work_orders.delete',

    // Quality Control
    'GET:quality-control'        => 'work_orders.read',
    'GET:quality-control/**'     => 'work_orders.read',
    'POST:quality-control/**'    => 'work_orders.edit',
    'DELETE:quality-control/*'   => 'work_orders.delete',

    // -----------------------------------------------------------------
    //  ACCOUNTING / FINANCE
    // -----------------------------------------------------------------
    'GET:accounting'             => 'accounting.read',
    'GET:accounting/dashboard'   => 'accounting.read',
    'GET:accounting/**'          => 'accounting.read',
    'POST:accounting/**'         => 'accounting.write',
    'GET:accounting/vendor-payments/*/edit' => 'accounting.write',

    'GET:simple-journal'         => 'accounting.read',
    'POST:simple-journal'        => 'accounting.write',

    // Price Lists
    'GET:price-lists'            => 'products.read',
    'GET:price-lists/**'         => 'products.read',
    'POST:price-lists/**'        => 'products.edit',

    // -----------------------------------------------------------------
    //  REPORTS
    // -----------------------------------------------------------------
    'GET:reports'                => 'reports.read',
    'GET:reports/**'             => 'reports.read',
    'POST:reports/**'            => 'reports.read',

    // -----------------------------------------------------------------
    //  ADMIN (handled by role:admin filter on routes, but belt-and-suspenders)
    // -----------------------------------------------------------------
    'GET:admin/security'             => 'users.read',
    'POST:admin/security/**'         => 'users.read',
    'GET:admin/security/**'          => 'users.read',
    'admin/**'                       => 'users.read',

    // Settings
    'GET:settings'               => 'settings.read',
    'GET:settings/**'            => 'settings.read',
    'POST:settings/**'           => 'settings.edit',

    // Users
    'GET:users'                  => 'users.read',
    'GET:users/**'               => 'users.read',
    'POST:users/**'              => 'users.edit',
    'DELETE:users/*'             => 'users.delete',

    // -----------------------------------------------------------------
    //  INTEGRATIONS
    // -----------------------------------------------------------------
    'GET:integrations/**'        => 'settings.read',
    'POST:integrations/**'       => 'settings.edit',

    // -----------------------------------------------------------------
    //  DEVTOOLS (admin only)
    // -----------------------------------------------------------------
    'devtools/**'                => 'settings.edit',

    // Dev quotation API (should be removed in production)
    'POST:dev/quotations/api/create' => 'quotations.write',

    // -----------------------------------------------------------------
    //  API v1 (internal)
    // -----------------------------------------------------------------
    'GET:api/v1/dashboard/**'        => 'dashboard.read',
    'GET:api/v1/work-orders'         => 'work_orders.read',
    'GET:api/v1/work-orders/*'       => 'work_orders.read',
    'POST:api/v1/work-orders/**'     => 'work_orders.edit',
    'GET:api/v1/quality/**'          => 'work_orders.read',
    'POST:api/v1/quality/**'         => 'work_orders.edit',
    'GET:api/v1/inventory/**'        => 'inventory.read',
    'POST:api/v1/inventory/**'       => 'inventory.edit',
    'GET:api/v1/notifications'       => true,
    'POST:api/v1/notifications/**'   => true,
];
