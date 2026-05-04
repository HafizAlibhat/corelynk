# Customers Module — Implementation Notes

This document describes the minimal, developer-ready Customers module implemented as a CodeIgniter 4 MVC feature.

Files added:

- `app/Models/CustomerModel.php` — primary model (generate/peek codes, simple create wrapper).
- `app/Models/CustomerContactModel.php` — contact records.
- `app/Models/CustomerAddressModel.php` — address records.
- `app/Controllers/Customers.php` — controller present in repo (implements index/create/edit/show/delete and helpers).
- `app/Views/customers/index.php`, `form.php`, `show.php` — minimal views matching Corelynk layout.
- `database/migrations/20251130_create_customers_table.sql` — SQL to create tables.

Routes:
- The following group should exist in `app/Config/Routes.php` (already added):
  - `customers/` (index)
  - `customers/create` (GET/POST)
  - `customers/api-create` (POST, debug)
  - `customers/{id}`, `customers/{id}/edit`, `customers/{id}/delete` etc.

Validation & fields:
- Server-side minimal validation: `name` required. Additional validation rules should be added per business rules (email format, max lengths, etc.).

Image uploads:
- Avatars are stored under `FCPATH . 'uploads/customers/'` and the DB stores a relative `avatar_path`.
- Ensure `writable/uploads/customers` is writable by the webserver.

How to create the DB tables:
- Import `database/migrations/20251130_create_customers_table.sql` into `corelynk_db` or run it via your migration tooling.

Next steps (suggested):
- Add unit tests for model and controller (phpunit).
- Harden validation, sanitization, and file upload limits.
- Add permission checks to controller actions.
- Add pagination filters and search on index view.
