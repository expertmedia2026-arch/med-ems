# CSV import examples

These six files use the exact headers accepted by the hospital CSV importer. They contain sample data for four departments, 40 patients, a larger department-service catalogue, and a basic hospital staff and pharmacy setup. Replace sample names, contact details, prices, stock counts, and other values with your hospital's data before importing.

Import in this order so department references are available first:

1. `departments.csv`
2. `consultation-prices.csv`
3. `lab-catalog.csv` (department services)
4. `staff.csv`
5. `patients.csv`
6. `pharmacy-catalog.csv`

The 40 sample patient numbers use the requested `MMH` prefix, from `MMH-26-00001` through `MMH-26-00040`. Keep hospital numbers, phone numbers, and emails unique within your hospital. Replace the example patient details before importing if those identifiers already exist.

Consultation prices and department services must use a department name that already exists in `departments.csv`, spelled exactly the same way. The staff list includes two doctors and two nurses for each department, plus reception, finance, pharmacy, laboratory, and administrator roles. The staff importer creates invitations; replace the example `.test` email addresses with real addresses before importing if you want invitations delivered. Roles must use application values such as `doctor`, `nurse`, `receptionist`, `accountant`, `lab`, `pharmacist`, or `admin`.

Dates use `YYYY-MM-DD`; `is_nhis` and `is_active` use `1` or `0`. Pharmacy `stock_quantity` becomes opening stock. The sample `.test` emails are placeholders. Duplicate matching records are skipped, and imports do not overwrite existing records.
