---
name: corelynk-ui-ux
description: Improve CoreLynk UI and UX without changing business logic, routes, queries, permissions, accounting, inventory, calculations, or statuses.
---
# CoreLynk UI/UX
- Establish a UI-only file boundary before editing.
- Reuse shared layout/components/assets and existing design tokens.
- No new inline CSS/JS unless the existing local file convention makes extraction impossible; explain exceptions.
- Preserve form names, IDs, request payloads, CSRF, validation messages, selectors, and action URLs.
- Make hierarchy obvious: page title, status, key facts, primary action, secondary actions, warnings.
- Include loading, disabled, success, error, empty, and long-content states.
- Verify 360, 390, 768, and desktop widths; keyboard navigation; focus visibility; labels; contrast; reduced motion.
- Test dark/light/system modes where present.
- Capture before/after evidence and ensure no controller/model/service/routes diff for a UI-only task.
