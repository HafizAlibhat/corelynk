<?php
// SINGLE SOURCE OF TRUTH FOR NAVIGATION.
// Update menu items here only — array order IS the menu order (groups and items).
// Renderers (partials/global_nav, partials/top_nav) read this through helper cl_nav_modules().
// Legacy menu partials/blocks exist in layout files for reference only and must not be used.
//
// Ordering convention (standard ERP flow), applied inside every group:
//   transactions -> master data -> reporting -> configuration
return [
    'Sales' => [
        'icon' => 'bi-bag',
        'submodules' => [
            ['label' => 'Products',            'icon' => 'bi-box-seam',          'route' => '/products',           'perm' => 'products.read'],
            ['label' => 'Customers',           'icon' => 'bi-people',            'route' => '/customers',          'perm' => 'customers.read'],
            ['label' => 'Quote / Sale Orders', 'icon' => 'bi-files',             'route' => '/documents',          'perm' => 'sales_orders.read'],
            ['label' => 'Document Studio',     'icon' => 'bi-easel',             'route' => '/document-studio',    'perm' => 'quotations.read'],
            ['label' => 'Sales Orders',        'icon' => 'bi-receipt',           'route' => '/sales-orders',       'perm' => 'sales_orders.read'],
            ['label' => 'Customer Invoices',   'icon' => 'bi-file-earmark-text', 'route' => '/customer-invoices',  'perm' => 'invoices.read'],
            ['label' => 'POS Register',        'icon' => 'bi-display',           'route' => '/pos',                'perm' => 'pos.read'],
            ['label' => 'Product Categories',  'icon' => 'bi-tags',              'route' => '/product-categories', 'perm' => 'products.edit'],
            ['label' => 'Reports',             'icon' => 'bi-bar-chart',         'route' => '/reports',            'perm' => 'reports.read'],
        ],
    ],
    'Purchases' => [
        'icon' => 'bi-cart-check',
        'submodules' => [
            ['label' => 'RFQ / PO',             'icon' => 'bi-arrow-left-right',   'route' => '/newpurchaseui/rfqpo',            'perm' => 'rfq.read'],
            ['label' => 'Purchase Orders',      'icon' => 'bi-cart4',              'route' => '/accounting/purchase-orders',     'perm' => 'purchase_orders.read'],
            ['label' => 'Goods Receipt Notes',  'icon' => 'bi-box-seam',           'route' => '/newpurchaseui/grn',              'perm' => 'grn.read'],
            ['label' => 'GRN List',             'icon' => 'bi-journal-text',       'route' => '/new-purchase-grns/list',         'perm' => 'grn.read'],
            ['label' => 'Vendor Bills',         'icon' => 'bi-receipt',            'route' => '/vendor-bills',                   'perm' => 'vendors.read'],
            ['label' => 'Pay Vendor Bills',     'icon' => 'bi-wallet2',            'route' => '/accounting/vendor-payments/pay', 'perm' => 'accounting.write'],
            ['label' => 'Vendor Payments',      'icon' => 'bi-cash-stack',         'route' => '/accounting/cheques',             'perm' => 'accounting.read'],
            ['label' => 'Vendors',              'icon' => 'bi-truck',              'route' => '/vendors',                        'perm' => 'vendors.read'],
            ['label' => 'Subcontracting',       'icon' => 'bi-building-gear',      'route' => '/subcontract-orders',             'perm' => 'purchase_orders.read'],
            ['label' => 'Gate Passes',          'icon' => 'bi-door-open',          'route' => '/gate_passes',                    'perm' => 'work_orders.read'],
        ],
    ],
    'Inventory' => [
        'icon' => 'bi-boxes',
        'submodules' => [
            ['label' => 'Stock',            'icon' => 'bi-boxes',            'route' => '/inventory/stock',       'perm' => 'inventory.read'],
            ['label' => 'Adjustments',      'icon' => 'bi-sliders',          'route' => '/inventory/adjustments', 'perm' => 'inventory.edit'],
            ['label' => 'Stock Transfers',  'icon' => 'bi-arrow-left-right', 'route' => '/inventory/transfers',   'perm' => 'inventory.edit'],
            ['label' => 'Product Ledger',   'icon' => 'bi-journal-text',     'route' => '/product-ledger',        'perm' => 'products.read'],
            ['label' => 'Products',         'icon' => 'bi-box-seam',         'route' => '/products',              'perm' => 'products.read'],
            ['label' => 'Categories',       'icon' => 'bi-tags',             'route' => '/product-categories',    'perm' => 'products.edit'],
            ['label' => 'Attributes',       'icon' => 'bi-list-ul',          'route' => '/product-attributes',    'perm' => 'products.read'],
        ],
    ],
    'Production' => [
        'icon' => 'bi-gear',
        'submodules' => [
            ['label' => 'Work Orders',    'icon' => 'bi-list-check',    'route' => '/work-orders',       'perm' => 'work_orders.read'],
            ['label' => 'Batches',        'icon' => 'bi-diagram-3',     'route' => '/batches',           'perm' => 'work_orders.read'],
            ['label' => 'Subcontracting', 'icon' => 'bi-building-gear', 'route' => '/subcontract-orders','perm' => 'purchase_orders.read'],
            ['label' => 'Gate Passes',    'icon' => 'bi-door-open',     'route' => '/gate_passes',       'perm' => 'work_orders.read'],
            ['label' => 'Production Logs','icon' => 'bi-clipboard-data','route' => '/logs',              'perm' => 'work_orders.read'],
            ['label' => 'Processes',      'icon' => 'bi-gear',          'route' => '/processes',         'perm' => 'work_orders.read'],
        ],
    ],
    'Warehouse' => [
        'icon' => 'bi-truck',
        'submodules' => [
            ['label' => 'Incoming Stock',    'icon' => 'bi-box-arrow-in-down', 'route' => '/warehouse/incoming-shipments',     'perm' => 'grn.read'],
            ['label' => 'Ready to Ship',     'icon' => 'bi-check-circle',      'route' => '/warehouse/ready-to-ship',          'perm' => 'delivery_orders.read'],
            ['label' => 'Pending Shipments', 'icon' => 'bi-hourglass-split',   'route' => '/delivery-orders/pending-followup', 'perm' => 'delivery_orders.read'],
            ['label' => 'Shipped Orders',    'icon' => 'bi-truck-flatbed',     'route' => '/delivery-orders/shipped',          'perm' => 'delivery_orders.read'],
        ],
    ],
    'Finance' => [
        'icon' => 'bi-currency-dollar',
        'submodules' => [
            ['label' => 'Customer Invoices',   'icon' => 'bi-file-earmark-text', 'route' => '/customer-invoices',              'perm' => 'invoices.read'],
            ['label' => 'Vendor Bill Payment', 'icon' => 'bi-wallet2',           'route' => '/accounting/vendor-payments/pay', 'perm' => 'accounting.write'],
            ['label' => 'Cheques & Payments',  'icon' => 'bi-receipt',           'route' => '/accounting/cheques',             'perm' => 'accounting.read'],
            ['label' => 'Journals',            'icon' => 'bi-journal-text',      'route' => '/accounting/journals',            'perm' => 'accounting.read'],
            ['label' => 'Accounts',            'icon' => 'bi-list-ul',           'route' => '/accounting/accounts',            'perm' => 'accounting.read'],
            ['label' => 'Balances',            'icon' => 'bi-cash-stack',        'route' => '/accounting/cheques/balances',    'perm' => 'accounting.read'],
            ['label' => 'Trial Balance',       'icon' => 'bi-gem',               'route' => '/accounting/trial-balance',       'perm' => 'accounting.read'],
            ['label' => 'Settings',            'icon' => 'bi-gear',              'route' => '/settings',                       'perm' => 'settings.read'],
        ],
    ],
    'HR' => [
        'icon' => 'bi-person-badge',
        'submodules' => [
            ['label' => 'Employees', 'icon' => 'bi-person-badge', 'route' => '/employees', 'perm' => 'employees.read'],
            ['label' => 'Salaries',  'icon' => 'bi-cash-stack',   'route' => '/employees/payroll', 'perm' => 'employees.read'],
        ],
    ],
    // Add more groups as needed
];
