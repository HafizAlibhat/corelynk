---
name: corelynk-safe-change
description: Implement a CoreLynk feature or bug fix with focused discovery, minimal diffs, business-invariant checks, tests, and regression protection.
---
# CoreLynk Safe Change
1. Read root `AGENTS.md`, `SYSTEM_MAP`, `BUSINESS_INVARIANTS`, and the matching `IMPACT_MAP` row.
2. Capture `git status --short`; preserve pre-existing changes.
3. Translate the request into observable acceptance criteria.
4. Identify exact route → controller → service → model/table → view/PDF/API chain.
5. Classify risk low/medium/high and choose the corresponding QA gate.
6. Search exact symbol/field/status usages; do not scan unrelated modules.
7. Write a focused failing test or reproducible check before changing high-risk logic.
8. Implement the smallest coherent patch. Preserve permissions, CSRF, transactions, public IDs, numbering, and audit data.
9. Test happy, invalid, unauthorized, retry/duplicate, partial, and rollback cases where applicable.
10. Inspect final diff and report evidence plus unverified items.
