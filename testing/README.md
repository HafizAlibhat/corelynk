# Testing Quarantine Folder

Purpose:
- Store temporary, debug, migration, and one-off maintenance scripts that must not be publicly accessible.

Rules:
- Create all future test/debug scripts only inside /testing.
- Never place test/debug scripts in project root, /public, /app, /system, or /vendor.
- Prefer CLI commands or authenticated admin tools over direct-exec PHP scripts.
