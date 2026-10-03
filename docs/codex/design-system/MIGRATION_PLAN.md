# Migration Plan

This plan describes how the design system should be adopted after approval.
No migration should begin before the foundation is reviewed and accepted.

## Phase 0: Foundation
- Freeze tokens.
- Freeze component names.
- Freeze theme names.
- Publish the CSS and JS contract.
- Document current page families and reuse targets.

## Phase 1: Shared primitives
- Buttons.
- Inputs.
- Selects.
- Badges.
- Alerts.
- Cards.
- Skeletons.
- Empty states.

## Phase 2: Core layouts
- Page header.
- Sidebar.
- Topbar.
- Toolbar.
- Breadcrumbs.
- Tabs.
- Accordion.

## Phase 3: List surfaces
- Standard list view wrapper.
- Table density modes.
- Sticky header table.
- Bulk selection.
- Filters and pagination.

## Phase 4: Document workflow shell
- Document header.
- Status badge.
- Timeline.
- Activity feed.
- Summary panel.
- Attachment panel.
- History panel.

## Phase 5: Domain wrappers
- Product card.
- Customer card.
- Vendor card.
- Warehouse card.
- ERP dashboard widgets.

## Effort estimate
Approximate effort after approval:
- Foundation and primitives: low to medium.
- Shared layouts: medium.
- List view conversion: medium to high.
- Document shell conversion: high.
- Dashboard harmonization: medium.

The largest cost is not the design tokens themselves.
The cost is replacing repeated page-level markup with shared components safely and incrementally.

## Recommended adoption order
1. Settings and admin surfaces.
2. Product, customer, and vendor lists.
3. Quotations and sales order documents.
4. Purchase RFQ/PO/GRN flows.
5. Delivery, invoice, and accounting pages.
6. Reports, logs, and dashboards.

## Rollout rules
- Migrate one module family at a time.
- Keep behavior, selectors, and payloads stable.
- Do not change business logic during UI migration.
- Remove page-level CSS only after a shared component exists.
- Verify dark mode, keyboard flow, and mobile widths in each step.

