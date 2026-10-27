# CSV import examples

These files use the exact headers accepted by the hospital CSV importer. Import in this order:

1. `departments.csv`
2. `consultation-prices.csv`
3. `patients.csv`
4. `staff.csv`

The staff importer creates invitations. Replace the example `.test` email addresses with real addresses before importing if you want the invitations delivered. Roles must use the application's role values, such as `doctor`, `nurse`, `receptionist`, or `pharmacist`. Dates use `YYYY-MM-DD`; `is_nhis` and `is_active` use `1` or `0`.
