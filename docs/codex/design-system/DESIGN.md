# CoreLynk SaaS UI Design Contract

This is the visual source of truth for the CoreLynk UI migration. It is derived
from the uploaded Outstaff dashboard reference and applies to every authenticated
list, form, dashboard, detail, document, report, and settings surface.

## Reference Character

- Modern productivity SaaS, not a traditional boxed ERP.
- Soft lavender page canvas surrounding a light application workspace.
- White navigation and content cards with generous rounded corners.
- Near-black typography and navigation selection.
- Bright sky-blue primary actions and selected controls.
- Pastel lavender, blue, mint, and gray status surfaces.
- Thin cool-gray dividers; no heavy card borders or dense grid lines.
- Information remains compact even though containers feel spacious.

## Canonical Tokens

| Role | Value |
|---|---|
| Canvas | `#b6aec8` |
| App workspace | `#f2f3f5` |
| Primary surface | `#ffffff` |
| Secondary surface | `#f7f7f8` |
| Selected navigation | `#1d1d27` |
| Primary text | `#171720` |
| Secondary text | `#5f6370` |
| Muted text | `#8a8e99` |
| Divider | `#e5e7eb` |
| Primary blue | `#2f9ff3` |
| Primary blue hover | `#168de3` |
| Lavender accent | `#ded8f3` |
| Blue status | `#cfddff` |
| Mint status | `#baf3c2` |
| Gray status | `#eeeeef` |
| Danger | `#ef5b6b` |
| Focus ring | `rgba(47, 159, 243, 0.28)` |

Typography uses `Manrope`, with a system sans-serif fallback. Headings use
700–800 weight, labels and actions use 600, and data uses 500.

## Geometry

- Desktop outer gutter: `24px`.
- Desktop sidebar: `280px` wide.
- Application frame radius: `30px`.
- Primary card radius: `24px`.
- Controls and small panels: `12–16px`.
- Status and filter chips: pill radius.
- Card gap: `18px`.
- Card padding: `24–28px`; dense table cards may use `16–20px`.
- Shadows are broad and faint: `0 14px 40px rgba(35, 38, 49, 0.07)`.

## Global Shell

- Desktop navigation is a fixed white left rail inside the lavender canvas.
- Brand sits at the top, modules form a vertical navigation stack, and user or
  utility actions remain visually separated.
- The active module is a near-black rounded pill with white icon and text.
- Main content uses a light-gray workspace and white cards.
- Mobile replaces the fixed rail with the existing accessible navbar collapse;
  no navigation link, permission check, or URL may change.

## Page Families

### Lists

- Page title and primary creation action form the first visual row.
- Filters use a single white toolbar with rounded inputs.
- Tables use whitespace and horizontal dividers, not full cell borders.
- Header labels are compact gray text without decorative uppercase.
- Hover uses pale blue; selected rows use `#e9f6ff`.
- Actions are compact rounded icon buttons with accessible names.
- Empty states use a centered icon, direct explanation, and primary action.

### Forms

- Forms are grouped into white section cards with clear titles and helper text.
- Labels sit above controls and remain visible.
- Inputs use a soft-gray fill, subtle border, 12px radius, and blue focus ring.
- Primary submit action is blue; cancel and secondary actions are neutral.
- Validation, loading, disabled, success, and error states remain explicit.

### Dashboards

- Use an asymmetric card grid like the reference rather than equal generic tiles.
- Key values are large and direct; supporting labels are muted.
- Charts use blue with lavender, mint, and cyan supporting colors.
- Avoid gradients except subtle chart fills and progress visualization.
- No decorative metric may replace or alter real ERP data.

### Detail And Document Views

- Header presents document title, status, key metadata, and actions.
- Primary action is blue; secondary actions are grouped and visually quieter.
- Facts, addresses, totals, activity, and line items use separate white cards.
- Tables retain accounting precision and existing line behavior.
- Warnings remain prominent and are never hidden for visual cleanliness.

### Settings

- Settings use a white vertical section rail and a separate content card.
- Active section uses the same near-black pill as primary navigation.
- Related fields are grouped with concise headings and descriptions.
- Destructive controls remain red and retain confirmation behavior.

## Responsive Rules

- `>= 1200px`: fixed 280px sidebar and multi-column dashboard/detail grids.
- `768–1199px`: compact top navigation and two-column cards where space allows.
- `390–767px`: single-column content, wrapped action bars, scrollable data tables.
- `360–389px`: 12px workspace gutters and full-width primary actions.
- No viewport may have horizontal page overflow; dense tables scroll internally.

## Accessibility And Motion

- WCAG AA text contrast is mandatory.
- Every icon-only control has an accessible name.
- Focus uses the blue reference ring and is never removed.
- Status is communicated with text, not color alone.
- Motion is limited to 120–180ms state transitions.
- `prefers-reduced-motion` disables non-essential animation.

## Strict UI-Only Boundary

- Preserve all IDs, field names, request payloads, action URLs, selectors, CSRF,
  permissions, calculations, statuses, and backend response shapes.
- Do not edit controllers, models, services, routes, migrations, queries, RBAC,
  accounting, inventory, or business workflows.
- Shared assets and layouts should produce system-wide consistency; avoid touching
  hundreds of views solely to duplicate styling.
