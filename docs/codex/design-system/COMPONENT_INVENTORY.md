# Component Inventory

This inventory maps the current application surface to reusable component groups that should be built once and reused everywhere.

## Base primitives
- Primary Button
- Secondary Button
- Danger Button
- Ghost Button
- Icon Button
- Input
- Textarea
- Select
- Search
- Date Picker styling
- Checkbox
- Radio
- Switch
- Badge
- Alert
- Toast
- Avatar
- Skeleton Loader

## Core composites
- Card
- Statistics Card
- Breadcrumb
- Tabs
- Accordion
- Toolbar
- Action Menu
- Dropdown
- Context Menu
- Command Palette
- Empty State
- Loading State
- Pagination

## Page layouts
- Sidebar
- Topbar
- Page Header
- Document Header
- List View
- Detail View
- Dashboard Widget Grid

## Table patterns
- Table
- Compact ERP Table
- Grouped Table
- Sticky Header Table
- Row Hover state
- Bulk Selection
- Filters Bar
- Frozen Header support
- Density switch: Comfortable / Compact / Ultra Compact

## Domain cards
- Product Card
- Customer Card
- Vendor Card
- Warehouse Card

## Timeline and activity
- Timeline
- Activity Feed
- History List

## Current page families and target reusable components
| View family | Target shared components |
|---|---|
| `dashboard/`, `corelynk/` | Page Header, Statistics Card, Dashboard Widget Grid, Empty State |
| `quotations/`, `sales_orders/`, `invoices/`, `vendor_bills/`, `vendor_receive/`, `delivery_orders/`, `purchase_ui/` | Document Header, Status Badge, Action Menu, Timeline, Activity Feed, Sticky Header Table, Totals Panel, Attachment Zone |
| `products/`, `product_variants/`, `product_assets/`, `product_categories/`, `product_attributes*/` | List View, Compact ERP Table, Filter Bar, Product Card, Tabs, Bulk Selection |
| `customers/`, `vendors/`, `warehouse/`, `employees/` | List View, Card Grid, Customer/Vendor/Warehouse Card, Breadcrumb, Tabs, Form Layout |
| `accounting/`, `logs/`, `reports/` | Grouped Table, Sticky Header Table, Filters Bar, Badge, Timeline, Empty State |
| `inventory/`, `batches/`, `production/`, `work_orders/`, `quality_control/`, `workflow_templates/` | Timeline, Activity Feed, Accordion, Status Badge, Compact ERP Table |
| `settings/`, `admin/` | Sidebar, Tabs, Form Layout, Alert, Toast, Toolbar |
| `partials/`, `layouts/`, `components/` | Base primitives, nav shell, search palette, modal content, shared forms |

## Existing reusable starting points
These files already prove the app benefits from shared presentation layers:
- `app/Views/components/form.php`
- `app/Views/components/tag_input.php`
- `app/Views/components/tag_modal.php`
- `app/Views/components/search_styles.php`
- `app/Views/components/modules_panel.php`
- `app/Views/components/modules_drawer.php`
- `app/Views/partials/global_nav.php`
- `app/Views/layouts/main.php`

The new design system should absorb these into consistent primitives instead of creating more page-specific variants.

## Reuse rules
- One component API per pattern.
- One token source for color and spacing.
- One table system for all list pages.
- One document shell for all headers, statuses, actions, timelines, summaries, tabs, and attachments.
- One form layout and one validation style.

