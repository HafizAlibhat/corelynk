# CoreLynk — Claude Code Project Memory (CLAUDE.md)

## Project
- Name: CoreLynk
- Framework: **CodeIgniter 4** (PHP)
- Database: **MySQL**, database name: **corelynk_db**
- Frontend: Plain CI4 views (PHP + HTML), jQuery/AJAX for dynamic parts — no
  separate JS frontend framework, no RTK/Redux.

## Repo map (CodeIgniter 4 default structure — confirm/adjust if customized)
```
app/
  Controllers/<Module>.php        # handles requests, calls Models, loads Views
  Models/<Module>Model.php        # DB queries (Query Builder / raw SQL)
  Views/<module>/                 # PHP view templates
  Config/Routes.php               # route definitions
  Config/Database.php             # DB connection config
  Database/Migrations/            # schema migrations
  Database/Seeds/                 # seed data (if used)
  Helpers/
  Libraries/
public/
  assets/js/<module>.js           # jQuery/AJAX scripts per module (if separated)
  index.php
writable/
  logs/
```
⟨Adjust the above if your project renamed folders or uses HMVC/modules
structure instead of the default flat structure — confirm actual layout
before relying on these paths.⟩

## Module list (audit / work order)
1. Customer
2. Sales
3. Purchases
4. Vendors
5. Inventory
6. Production
7. Warehouse
8. Finance
9. HR

## Data flow contract (what "correct" looks like)
Browser (view + jQuery/AJAX call) → `Routes.php` → Controller method →
Model method (Query Builder or raw query) → MySQL (`corelynk_db`) →
Model returns data → Controller formats response (view render or JSON) →
browser updates (page render or AJAX success callback updates DOM).

Bugs = any break in this chain: missing/incorrect route, controller not
validating input, model queries not matching table schema, missing
`$this->db->transStart()/transComplete()` on multi-table writes, N+1 query
patterns, unescaped input (SQL injection risk) if raw queries are used
instead of Query Builder/parameter binding, AJAX response shape not matching
what the JS expects, silent PHP errors not shown/logged, CSRF token issues
on AJAX POSTs, orphaned foreign keys, missing indexes on filtered columns.

---

## Low-Token Operating Rules

For every task in this repository:

1. If a Graphify knowledge-graph tool is connected in this session, use it
   first to locate relevant architecture, files, functions, and relationships.
   **If Graphify is not available/connected, skip this step silently and fall
   back to the Repo Map above — do not repeatedly try to invoke a
   disconnected tool.**
2. Do not scan the entire repository unless absolutely necessary. Use the
   Repo Map above to go straight to the relevant module's Controller/Model/
   View/JS file.
3. Open only the minimum files required for the current task.
4. Reuse information already available from Graphify (or earlier in this
   session) instead of repeating searches.
5. Keep shell/command output compact — avoid dumping large raw output from:
   - `git diff`
   - `git status`
   - logs
   - grep/search results
   - test runs
   - directory trees

   Summarize instead of pasting full output; show only the lines relevant to
   the task.
6. Follow **minimal-change principles**:
   - make the smallest correct change
   - avoid unnecessary refactoring
   - avoid speculative abstractions
   - reuse existing CodeIgniter patterns already in the project
   - do not add extra code unless needed
7. Keep tool usage targeted — no redundant file reads, no re-opening a file
   already read this session unless it changed.
8. Never modify unrelated modules.
9. **Before editing:**
   - identify exact files involved (Controller, Model, View, JS)
   - identify exact methods/functions involved
   - explain the intended change briefly
10. **After editing:**
    - run only the smallest relevant verification (e.g. hit the specific
      route/AJAX endpoint, or the smallest relevant test)
    - avoid broad test suites unless required
11. Keep the final response concise:
    - changed files
    - what changed
    - verification
    - remaining issues

## CoreLynk Safety Rules

Do not change these unless the task explicitly requires it:

- controllers unrelated to the task
- models unrelated to the task
- `Config/Routes.php` entries unrelated to the task
- RBAC / permissions / filters
- authentication/security logic
- accounting logic
- inventory logic
- database schema
- migrations
- status workflows
- calculations
- production/`.env` configuration

Never make direct database edits when a migration is required — always write
a CI4 migration (`php spark make:migration ...`) for `corelynk_db` schema
changes.

Prefer existing CoreLynk/CodeIgniter architecture and coding patterns
(Query Builder style, existing Controller/Model conventions) over
introducing new frameworks, ORMs, or patterns.

## Audit-specific rule (when doing bug audits, not feature work)
When the task is a **bug audit** (not a fix), do not edit any files. Only
read, trace data flow, and append findings to `audit/BUG_REPORT.md` — one
module section per run, per the module list above.