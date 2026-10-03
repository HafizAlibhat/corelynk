# Known Risks and Audit Backlog — 2026-08-06 Snapshot

These are audit leads, not automatic permission to change code.

## Critical/High
1. A backup environment file `.env.bak_20251112_125106` is tracked by Git although `.env.*` is ignored now. Assume credentials may be exposed. Remove it from version control, rotate any contained secrets, and preserve a safe `.env.example` separately.
2. The repository snapshot already contains user modifications in `app/Config/Cookie.php`, `app/Models/CustomerModel.php`, `app/Models/VendorModel.php`, and a testing batch file, plus untracked `uploads.ini`. Codex must not overwrite or fold these into unrelated work.
3. Automated test coverage is very small for the number of business-critical controllers/models. Accounting, inventory, GRN, billing, shipment, cancellation, retry/idempotency, and rollback paths need systematic tests.

## Medium
4. `app/Config/Routes.php` is 1,278 lines and contains repeated-looking absolute routes as well as many grouped relative routes. Audit route shadowing/duplication with `php spark routes`; do not rely on text-only duplicate detection.
5. `testing/`, `tools/`, and root contain many historical diagnostic/repair scripts, including potentially destructive data scripts. Keep them outside normal runtime and document safe/unsafe status.
6. Views contain extensive inline styling and scripts (audit observed thousands of inline style attributes and hundreds of script blocks). This increases UI inconsistency, CSP difficulty, and regression risk. Migrate gradually through shared assets; do not perform a mass rewrite.
7. Documentation contains historical claims that may conflict with current code/schema, including database naming and “complete/production-ready” assertions. Treat migrations and live code as stronger evidence; validate behavior with tests.

## Quality backlog
8. Build a workflow test matrix for Quote → SO → RFQ/PO → GRN → Bill → payment/advance → stock → DO → invoice.
9. Add shared calculators/services for repeated monetary and fulfillment calculations where duplication is proven.
10. Add CI gates: PHP syntax, PHPUnit, migration check, secret scan, and changed-file policy.
11. Add route and permission inventory generated from code, not manually maintained.
12. Add a disposable test database profile; never run integration tests against the working/production database.
