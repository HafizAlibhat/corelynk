# Token Documentation

This file defines the canonical token set for the CoreLynk Enterprise Design System.
All colors, shadows, spacing, and sizing should resolve through CSS variables.

## Naming rules
- Use the `--cl-` prefix for CoreLynk design-system tokens.
- Prefer semantic names over raw hex references.
- Do not hardcode component colors in page templates.
- Theme presets override tokens; components consume tokens.

## Color system
### Brand
- `--cl-color-brand-50`
- `--cl-color-brand-100`
- `--cl-color-brand-200`
- `--cl-color-brand-300`
- `--cl-color-brand-400`
- `--cl-color-brand-500`
- `--cl-color-brand-600`
- `--cl-color-brand-700`
- `--cl-color-brand-800`
- `--cl-color-brand-900`

### Neutral surfaces
- `--cl-color-canvas`
- `--cl-color-surface-1`
- `--cl-color-surface-2`
- `--cl-color-surface-3`
- `--cl-color-border`
- `--cl-color-border-strong`
- `--cl-color-divider`
- `--cl-color-overlay`

### Text
- `--cl-color-text-primary`
- `--cl-color-text-secondary`
- `--cl-color-text-muted`
- `--cl-color-text-disabled`
- `--cl-color-text-inverse`

### Semantic
- `--cl-color-success`
- `--cl-color-warning`
- `--cl-color-danger`
- `--cl-color-info`
- `--cl-color-link`
- `--cl-color-focus`

### Domain-specific semantic colors
- `--cl-color-accounting-asset`
- `--cl-color-accounting-liability`
- `--cl-color-accounting-equity`
- `--cl-color-accounting-revenue`
- `--cl-color-accounting-expense`
- `--cl-color-inventory-onhand`
- `--cl-color-inventory-low`
- `--cl-color-inventory-out`
- `--cl-color-inventory-reserved`
- `--cl-color-inventory-committed`
- `--cl-color-timeline-created`
- `--cl-color-timeline-approved`
- `--cl-color-timeline-posted`
- `--cl-color-timeline-shipped`
- `--cl-color-timeline-received`
- `--cl-color-timeline-reversed`

## Typography scale
- `--cl-font-family-sans`
- `--cl-font-family-mono`
- `--cl-font-size-xs`
- `--cl-font-size-sm`
- `--cl-font-size-md`
- `--cl-font-size-lg`
- `--cl-font-size-xl`
- `--cl-font-size-2xl`
- `--cl-font-size-3xl`
- `--cl-line-height-tight`
- `--cl-line-height-base`
- `--cl-line-height-relaxed`
- `--cl-letter-spacing-tight`
- `--cl-letter-spacing-normal`

Recommended sizing intent:
- `xs`: helper labels, metadata, badges.
- `sm`: form labels, dense supporting text.
- `md`: body copy, table cells, list rows.
- `lg`: page section headings.
- `xl` and above: page headers and dashboards.

## Spacing system
- `--cl-space-0`
- `--cl-space-1`
- `--cl-space-2`
- `--cl-space-3`
- `--cl-space-4`
- `--cl-space-5`
- `--cl-space-6`
- `--cl-space-8`
- `--cl-space-10`
- `--cl-space-12`
- `--cl-space-16`
- `--cl-space-20`

Design intent:
- 4px base unit for dense ERP surfaces.
- 8px and 12px are the main rhythm values.
- 16px and 24px are reserved for section separation.

## Radius
- `--cl-radius-none`
- `--cl-radius-sm`
- `--cl-radius-md`
- `--cl-radius-lg`
- `--cl-radius-xl`
- `--cl-radius-pill`

Use smaller radius on tables and dense document surfaces.
Use larger radius only for cards, overlays, and preview blocks.

## Shadows and elevation
- `--cl-shadow-none`
- `--cl-shadow-1`
- `--cl-shadow-2`
- `--cl-shadow-3`
- `--cl-shadow-4`
- `--cl-elevation-1`
- `--cl-elevation-2`
- `--cl-elevation-3`
- `--cl-elevation-4`

Principle:
- shadows should signal hierarchy, not decoration.
- dark mode should use reduced-opacity shadows, not heavy blur.

## Motion
- `--cl-motion-duration-fast`
- `--cl-motion-duration-normal`
- `--cl-motion-duration-slow`
- `--cl-motion-ease-standard`
- `--cl-motion-ease-emphasized`

Recommended values:
- fast: 120ms
- normal: 160ms
- slow: 180ms

## Icon sizing
- `--cl-icon-size-xs`
- `--cl-icon-size-sm`
- `--cl-icon-size-md`
- `--cl-icon-size-lg`
- `--cl-icon-size-xl`

Recommended usage:
- xs: inline metadata
- sm: buttons and table utilities
- md: page actions
- lg: featured cards and empty states

## Responsive breakpoints
The design system should align with common ERP breakpoints:
- `--cl-breakpoint-sm`: 576px
- `--cl-breakpoint-md`: 768px
- `--cl-breakpoint-lg`: 992px
- `--cl-breakpoint-xl`: 1200px
- `--cl-breakpoint-2xl`: 1400px

## Density tokens
- `--cl-density-comfortable`
- `--cl-density-compact`
- `--cl-density-ultra-compact`

Use density as a layout mode for tables and documents, not as a theme replacement.

## Theme presets
Theme presets should be a thin override layer on top of the same component contract.

### CoreLynk Enterprise
- Balanced neutral default.
- Professional SaaS look.
- Suitable for most workflows.

### Minimal White
- Bright, highly legible, low ornamentation.
- Good for light-only environments.

### Executive Blue
- Strong blue brand accent.
- Good for dashboards and finance-heavy views.

### Graphite Dark
- Dark-first enterprise theme.
- High contrast without neon colors.

### Glass
- Soft translucent overlays.
- Reserved for advanced analytic surfaces only.

### Modern Purple
- Premium accent preset.
- Use sparingly and keep surfaces neutral.

### Classic ERP
- Familiar, conservative, office-friendly palette.

### Compact ERP
- Same visual language with tighter spacing and stronger density.

