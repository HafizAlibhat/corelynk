# Customers Module — QA Checklist

Quick manual QA steps:

1. Ensure DB tables are created (run `database/migrations/20251130_create_customers_table.sql`).
2. Optionally import `database/seed_sample_customer.sql` to get a sample customer.
3. Start dev server: `php spark serve` and open the app.
4. Go to the left menu -> Customers.
5. Create a new customer using the form (add multiple contacts and addresses). Verify success flash message.
6. View the customer details page. Verify avatar image (if uploaded) displays and contacts/addresses show.
7. Edit the customer and change data. Verify the changes persist.
8. Deactivate a customer using Delete. Verify status becomes inactive.

Automated tests (future):
- Add PHPUnit tests to assert model generateCustomerCode() and basic controller flows.
