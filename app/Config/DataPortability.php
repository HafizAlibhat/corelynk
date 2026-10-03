<?php

namespace Config;

use App\Models\CompanySettingsModel;
use App\Models\CustomerModel;
use App\Models\DeliveryOrderLineModel;
use App\Models\DeliveryOrderModel;
use App\Models\ProductCategoryModel;
use App\Models\ProductModel;
use App\Models\ProductVariantModel;
use App\Models\PurchaseGrnLineModel;
use App\Models\PurchaseGrnModel;
use App\Models\PurchaseOrderLineModel;
use App\Models\PurchaseOrderModel;
use App\Models\PurchaseRfqLineModel;
use App\Models\PurchaseRfqModel;
use App\Models\QuotationLineModel;
use App\Models\QuotationModel;
use App\Models\SalesOrderLineModel;
use App\Models\SalesOrderModel;
use App\Models\VendorModel;

/**
 * Declarative registry for the Data Export/Import engine
 * (App\Services\DataPortabilityService).
 *
 * - tier: import processing order, lowest first, so a row's foreign keys
 *   already exist in the target DB by the time it's imported.
 * - key_column: the business key used to match rows on import.
 *   'public_id' for most modules, a natural column for tables that don't
 *   have one, or null for singleton (single-row) tables.
 * - foreign_keys: local column => registry key it references. Exported as
 *   a portable {module,key} reference instead of a raw numeric id, and
 *   resolved back to a local id at import time.
 * - children: line/child tables belonging to a parent row. Always fully
 *   replaced (delete + reinsert) under the parent on import, so no
 *   line-level key is needed even for tables with no natural unique column.
 */
class DataPortability
{
    public static array $modules = [
        'product_categories' => [
            'label' => 'Product Categories',
            'tier' => 0,
            'table' => 'product_categories',
            'model' => ProductCategoryModel::class,
            'key_column' => 'name',
            'foreign_keys' => [],
        ],
        'customers' => [
            'label' => 'Customers',
            'tier' => 0,
            'table' => 'customers',
            'model' => CustomerModel::class,
            'key_column' => 'public_id',
            'foreign_keys' => [],
        ],
        'vendors' => [
            'label' => 'Vendors',
            'tier' => 0,
            'table' => 'vendors',
            'model' => VendorModel::class,
            'key_column' => 'public_id',
            'foreign_keys' => [],
        ],
        'products' => [
            'label' => 'Products',
            'tier' => 1,
            'table' => 'products',
            'model' => ProductModel::class,
            'key_column' => 'public_id',
            'foreign_keys' => [
                'category_id' => 'product_categories',
                'vendor_id' => 'vendors',
            ],
            'children' => [
                'product_variants' => [
                    'model' => ProductVariantModel::class,
                    'parent_fk' => 'product_id',
                    'foreign_keys' => ['vendor_id' => 'vendors'],
                ],
            ],
        ],
        'quotations' => [
            'label' => 'Quotations',
            'tier' => 2,
            'table' => 'quotations',
            'model' => QuotationModel::class,
            'key_column' => 'public_id',
            'foreign_keys' => ['customer_id' => 'customers'],
            'children' => [
                'quotation_lines' => [
                    'model' => QuotationLineModel::class,
                    'parent_fk' => 'quotation_id',
                    'foreign_keys' => ['product_id' => 'products', 'product_variant_id' => 'product_variants'],
                ],
            ],
        ],
        'sales_orders' => [
            'label' => 'Sales Orders',
            'tier' => 2,
            'table' => 'sales_orders',
            'model' => SalesOrderModel::class,
            'key_column' => 'public_id',
            'foreign_keys' => ['customer_id' => 'customers', 'quotation_id' => 'quotations'],
            'children' => [
                'sales_order_lines' => [
                    'model' => SalesOrderLineModel::class,
                    'parent_fk' => 'sales_order_id',
                    'foreign_keys' => ['product_id' => 'products', 'product_variant_id' => 'product_variants'],
                ],
            ],
        ],
        'purchase_rfqs' => [
            'label' => 'Purchase RFQs',
            'tier' => 2,
            'table' => 'purchase_rfqs',
            'model' => PurchaseRfqModel::class,
            'key_column' => 'public_id',
            'foreign_keys' => ['vendor_id' => 'vendors'],
            'children' => [
                'purchase_rfq_lines' => [
                    'model' => PurchaseRfqLineModel::class,
                    'parent_fk' => 'rfq_id',
                    'foreign_keys' => ['product_id' => 'products', 'product_variant_id' => 'product_variants'],
                ],
            ],
        ],
        'purchase_orders' => [
            'label' => 'Purchase Orders',
            'tier' => 3,
            'table' => 'purchase_orders',
            'model' => PurchaseOrderModel::class,
            'key_column' => 'public_id',
            'foreign_keys' => [
                'rfq_id' => 'purchase_rfqs',
                'vendor_id' => 'vendors',
                'sales_order_id' => 'sales_orders',
            ],
            'children' => [
                'purchase_order_lines' => [
                    'model' => PurchaseOrderLineModel::class,
                    'parent_fk' => 'po_id',
                    'foreign_keys' => ['product_id' => 'products', 'variant_id' => 'product_variants'],
                ],
            ],
        ],
        'delivery_orders' => [
            'label' => 'Delivery Orders',
            'tier' => 3,
            'table' => 'delivery_orders',
            'model' => DeliveryOrderModel::class,
            'key_column' => 'public_id',
            'foreign_keys' => ['sales_order_id' => 'sales_orders'],
            'children' => [
                'delivery_order_lines' => [
                    'model' => DeliveryOrderLineModel::class,
                    'parent_fk' => 'delivery_order_id',
                    'foreign_keys' => ['product_id' => 'products', 'variant_id' => 'product_variants'],
                ],
            ],
        ],
        'purchase_grns' => [
            'label' => 'Purchase GRNs',
            'tier' => 4,
            'table' => 'purchase_grns',
            'model' => PurchaseGrnModel::class,
            'key_column' => 'public_id',
            'foreign_keys' => ['po_id' => 'purchase_orders', 'vendor_id' => 'vendors'],
            'children' => [
                'purchase_grn_lines' => [
                    'model' => PurchaseGrnLineModel::class,
                    'parent_fk' => 'grn_id',
                    // po_line_id references a line row, not a top-level module row — resolved
                    // separately by DataPortabilityService via the in-run line-id map.
                    'foreign_keys' => ['product_id' => 'products', 'variant_id' => 'product_variants'],
                    'line_foreign_keys' => ['po_line_id' => 'purchase_order_lines'],
                ],
            ],
        ],
        'company_settings' => [
            'label' => 'Company Settings',
            'tier' => 0,
            'table' => 'company_settings',
            'model' => CompanySettingsModel::class,
            'key_column' => null,
            'singleton' => true,
            'foreign_keys' => [],
        ],
    ];
}
