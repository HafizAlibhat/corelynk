# Risk and Impact Report

## Scope
This foundation affects presentation architecture only.
It does not change routes, controllers, models, services, SQL, calculations, or statuses.

## Main risks
### 1. Visual drift during migration
Different modules currently use different local styling conventions.
If migration happens too fast, pages may look inconsistent until the shared components are fully adopted.

### 2. Theme compatibility
The app already uses `body.theme-dark` and `data-bs-theme`.
The new theme engine must stay compatible with that behavior while introducing named presets.

### 3. Page-specific CSS dependencies
Many views contain local CSS assumptions.
Replacing them without a component inventory could break spacing, tables, or button density.

### 4. Density regressions
ERP users often rely on compact information density.
A visually clean redesign can accidentally reduce scan speed if row height and spacing are too large.

### 5. Over-generalization
Some workflows need special document behavior.
The design system should standardize the shell, not flatten business-specific content.

## Impact areas
- All list views.
- All document pages.
- All settings and admin forms.
- All dashboards and reports.
- All tables, filters, and action toolbars.

## Current codebase impact
The repository already contains:
- a global theme stylesheet,
- multiple page-specific style blocks,
- shared component partials,
- theme toggling in the navbar,
- several list/document patterns that can be normalized.

That means the design-system effort is mostly consolidation and harmonization, not invention.

## Risk level
Overall risk for the foundation phase: low.

Reason:
- no existing pages are being migrated yet,
- no controllers or data contracts change,
- no DB schema change is required,
- the work is documentation and scaffold oriented.

## Approval gate
Do not start page migration until the following are approved:
- token names and theme names,
- component API names,
- folder structure,
- rollout order,
- density defaults,
- theme persistence strategy.

## Success criteria for the foundation
- One documented source of truth exists.
- A reusable component inventory exists.
- A migration sequence exists.
- Theme architecture is defined.
- The foundation can be reviewed without inspecting unrelated page diffs.

