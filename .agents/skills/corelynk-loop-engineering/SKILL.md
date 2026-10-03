---
name: corelynk-loop-engineering
description: Run a bounded plan-implement-test-review loop for complex CoreLynk changes until acceptance criteria pass or a clearly reported blocker remains.
---
# CoreLynk Loop Engineering
Use bounded iterations, never an endless autonomous loop.

For each iteration:
1. PLAN: pick one smallest unmet acceptance criterion and define one verification command.
2. IMPLEMENT: make only the changes needed for that criterion.
3. TEST: run the narrowest relevant automated check.
4. REVIEW: inspect the diff and test output for regressions/invariants.
5. DECIDE:
   - PASS → move to next criterion.
   - FAIL → diagnose from evidence and repeat with a new hypothesis.
   - BLOCKED → stop and report exact missing dependency/data/permission.

Limits:
- Maximum 5 implementation iterations per task before stopping for a consolidated diagnosis.
- Never weaken tests, permissions, validation, accounting, or inventory rules to make a test pass.
- Never modify unrelated files or existing user changes.
- Run the final risk-based QA gate only after all acceptance criteria pass individually.
