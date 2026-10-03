# CoreLynk Enterprise Design System

Status: foundation only. No existing business logic, routes, controllers, models, or current page templates were modified for activation.

## Purpose
Create one centralized visual language that can scale across the full CoreLynk ERP surface without building one-off UI per page.

This foundation is intended to:
- reduce duplicated CSS and scattered page styling,
- standardize dense ERP layouts,
- keep tables, forms, documents, and dashboards visually consistent,
- support instant theme switching,
- preserve accessibility and keyboard flow,
- allow future migration page-by-page with very small diffs.

## What already exists
CoreLynk already has pieces of a theme layer:
- `public/assets/css/corelynk-theme.css` contains a large global token and override set.
- `app/Views/partials/global_nav.php` already applies a user theme preference with `localStorage`.
- `public/assets/css/style.css` and page-level view styles still carry a lot of duplicated layout treatment.

This design system does not replace those files yet. It defines the target architecture and the reusable contract the app should converge on.

## Design rules
- Minimal.
- Professional.
- Compact.
- Information dense.
- Fast to scan.
- Keyboard friendly.
- Dark mode first-class.
- Responsive from mobile to desktop.
- AA accessibility target.
- All colors come from CSS variables.
- No inline CSS in the new system.
- No page-specific visual reinvention unless a module truly needs a unique pattern.

## Architecture Layers
1. Tokens
   - color, type, spacing, radius, shadow, elevation, motion, icon sizing, breakpoints.
2. Primitives
   - buttons, inputs, selects, badges, alerts, avatar, skeletons, pagination.
3. Composites
   - cards, stat cards, toolbar, filters, tabs, accordion, command palette, tables.
4. Layouts
   - sidebar, topbar, page header, document header, list views, detail views, dashboards.
5. Domain patterns
   - document summary, timeline, activity feed, compact ERP table, customer/vendor/product cards.

## Proposed folder structure
The app should converge on one shared structure:

```text
public/assets/css/design-system.css
public/assets/js/theme-engine.js
app/Views/components/design_system/
app/Views/components/design_system/primitives/
app/Views/components/design_system/composites/
app/Views/components/design_system/layouts/
app/Views/components/design_system/domain/
docs/codex/design-system/
```

## Theme engine contract
The theme engine should:
- apply a theme immediately,
- persist the chosen theme for the user,
- support light, dark, and named enterprise presets,
- update both design-system and Bootstrap theme attributes when needed,
- avoid page reloads,
- degrade gracefully when JavaScript is unavailable.

## Current-state compatibility
The new system must remain compatible with the current app during migration:
- existing selectors should not be renamed,
- existing JS hooks should not be broken,
- existing page data flows should remain unchanged,
- current pages should keep rendering while components are gradually adopted.

## Migration philosophy
- Build the library first.
- Approve the library before migrating pages.
- Convert one shared pattern at a time.
- Keep diffs tiny and reversible.
- Prefer one reusable component over repeated page-specific markup.

## Delivery sequence
1. Lock tokens and theme names.
2. Freeze component API and naming.
3. Build shared primitives.
4. Build shared layouts.
5. Migrate the highest-duplication list/detail pages first.
6. Retire page-level CSS only after coverage exists in the design system.

