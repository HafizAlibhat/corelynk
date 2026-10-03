---
name: corelynk-qa-review
description: Review a CoreLynk diff for logic bugs, security, accounting/inventory errors, regressions, test gaps, and deployment risks without making unrelated edits.
---
# CoreLynk QA Review
Review in this order:
1. User intent and acceptance criteria.
2. Diff scope and accidental/unrelated changes.
3. Authentication, authorization, CSRF, validation, escaping, upload safety.
4. Transactions, rollback, concurrency, duplicate submissions, idempotency.
5. Accounting Dr/Cr, source linkage, balances, reversal semantics.
6. Inventory movement/balance parity, partial quantities, no double deduction.
7. Status transitions and downstream consumers.
8. Currency, decimal precision, tax, discount, shipping, totals parity across UI/PDF/API/posting.
9. Migration compatibility, old rows, FK/index/default, down migration.
10. Tests: happy, negative, permission, retry, partial, failure rollback.
Output findings by severity with exact file/line, failure scenario, and smallest safe fix. Do not claim success without command/test evidence.
