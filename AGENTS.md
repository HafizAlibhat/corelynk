# CoreLynk ERP — Codex Operating Instructions

## Mission
Make small, correct, reviewable changes to CoreLynk without breaking accounting, inventory, purchasing, sales, production, shipping, RBAC, document totals, or existing user workflows.

## Ground truth and reading order
Before editing, read only what is needed in this order:
1. This `AGENTS.md`.
2. `docs/codex/SYSTEM_MAP.md`.
3. `docs/codex/BUSINESS_INVARIANTS.md`.
4. The task-specific controller/model/service/view/routes/migration/tests listed in `docs/codex/IMPACT_MAP.md`.
5. Relevant database tables from `docs/codex/DB_QUICK_MAP.md` or the SQL dump only when a schema fact is missing.

Do not re-scan the whole repository unless the task is explicitly architectural or the focused map proves incomplete. Prefer `rg` on exact class, method, route, table, status, or field names.

## Existing work protection
- Start with `git status --short` and `git diff --stat`.
- Never discard, reset, overwrite, format, or “clean up” unrelated changes.
- Treat pre-existing modifications as user-owned.
- Never edit production, server backups, uploads, database dumps, `.env`, or credentials.
- Never run destructive scripts in `testing/`, `tools/`, or root without reading them and receiving explicit approval.

## Required workflow
1. Restate the requested behavior and acceptance criteria.
2. Identify the minimum affected files and tables.
3. Trace read/write side effects using `docs/codex/IMPACT_MAP.md`.
4. Inspect existing tests and nearby patterns.
5. Make the smallest coherent diff. No unrelated refactor.
6. Add or update tests for the changed behavior.
7. Run the narrow QA gate first; run broader tests only when impact requires it.
8. Self-review the diff for logic, security, permissions, transactions, totals, statuses, and regressions.
9. Report changed files, tests run, results, risks, and anything not verified.

## Non-negotiable business safety
- Accounting and inventory are append/reversal domains. Never delete posted journals or stock movements to “fix” data.
- Debit must equal credit for every posted journal entry.
- Stock cannot go negative unless an existing, explicit policy allows it.
- Sales Orders do not directly deduct stock; confirmed shipment/delivery workflows must use inventory services and transactions.
- GRN quantity, PO quantity, billed quantity, paid amount, shipped quantity, and remaining quantity must be reconciled independently.
- Advances are assets until applied; normal vendor bill payments reduce Accounts Payable.
- Posted/confirmed financial documents are not silently editable. Use cancellation/reversal/credit-note patterns already present.
- Services must not create physical stock movements.
- Preserve currency, FX, decimal precision, tax, shipping, line discount, and document discount semantics.
- Preserve public IDs, document numbering, audit fields, creator identity, timestamps, and status history.

## Database rules
- Schema changes only through CodeIgniter migrations with safe `up()` and `down()` behavior.
- No manual production SQL and no runtime “create missing table/column” logic.
- Use models/query builder and parameter binding. Never concatenate user input into SQL.
- Multi-record business operations must be transactional and rollback fully on failure.
- Before changing a column/status/enum, search all reads and writes of it.
- Do not assume documentation is current when code or schema contradicts it; report the contradiction.

## Security and access control
- Preserve authentication, RBAC, ownership/data-scope checks, CSRF, validation, escaping, upload restrictions, and audit logs.
- Never return stack traces, SQL, filesystem paths, secrets, or raw exception details to end users.
- Validate server-side even when the UI validates.
- For uploads: validate extension, MIME, size, storage path, generated filename, and authorization.
- AJAX checks are not authorization. Verify permissions separately.

## UI/UX rules
- UI-only requests must not alter controllers, models, services, routes, queries, accounting, inventory, permissions, calculations, or statuses unless explicitly requested.
- Reuse existing layouts, components, tokens, and compiled asset pipeline.
- Avoid new inline CSS/JS. Do not add visual effects that reduce speed, readability, or accessibility.
- Verify 360px, 390px, 768px, and desktop layouts; keyboard focus; labels; errors; loading/disabled states; empty states; and dark/light/system themes where supported.
- Never hide a business warning or destructive consequence for visual cleanliness.

## QA gates by risk
### Low risk: copy/style/read-only UI
- PHP syntax for touched PHP files.
- Relevant view render/manual route check.
- Browser console check when JavaScript changed.

### Medium risk: CRUD, routes, validation, workflow state
- All low-risk checks.
- Focused PHPUnit tests.
- Route verification and authorization/CSRF negative case.
- DB transaction and rollback case where writes span tables.

### High risk: accounting, stock, invoices, payments, GRN, PO, DO, migrations
- All medium-risk checks.
- Business invariant tests before and after.
- Duplicate submission/idempotency test.
- Partial quantity/payment test.
- Failure rollback test.
- Migration up/down on a disposable database.
- Wider affected-module test suite.

## Completion standard
A task is not complete merely because code compiles. It is complete only when:
- requested behavior works;
- old behavior that should remain still works;
- permission and invalid-input cases are handled;
- multi-table data stays consistent;
- focused tests pass;
- no unrelated files changed;
- remaining uncertainty is stated honestly.
