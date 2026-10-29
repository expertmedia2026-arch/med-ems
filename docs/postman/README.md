# Postman files

Import `HMS-API-v1.postman_collection.json` into Postman. Set `baseUrl`, `email`, and `password` as collection variables or in a Postman environment. Use the app address for `baseUrl`, usually `http://localhost:8000`, and an account for the role you want to test. Then run **Authentication (public) → Authentication → POST /api/v1/auth/login**. The request test stores the returned bearer token in the collection variable automatically.

The collection has folders for Admin, Doctor, Nurse, Receptionist, Lab, Pharmacist, Cashier, Patient, and Mortuary Attendant, plus a shared **Staff Roster** folder. Routes available to multiple roles appear in each relevant role folder. Shared account and reference-data requests are under **Common (authenticated)**. Public login, reset-password, and invitation acceptance requests are under **Authentication (public)**.

Replace the sample ID variables (`departmentId`, `patientId`, `visitId`, `admissionId`, `mortuaryCaseId`, `witnessId`, and others) with real IDs returned by your database/API. Use an account matching the selected role folder. For booking dates, set `bookingDate` to a future date within the configured booking window. Mutation requests include a starter body; fill in the fields documented for that endpoint before sending it. Inpatient DAMA requires a witness name and an image file; lab result uploads use form-data with files as `files[]`.

Each JSON request checks for the standard response envelope. A 401 means login or the token is missing, a 403 means the logged-in account lacks that role, and a 404/422 commonly means an example ID or required workflow prerequisite needs updating.

JSON API responses use the envelope `{ "success": true, "message": "...", "data": ..., "errors": null }`. Error responses set `success` to `false`, `data` to `null`, and put validation details in `errors`. Binary lab attachments remain file downloads. Successful deletes return the same JSON envelope with HTTP 200.

The collection includes examples for all 20 inpatient operations, the A&E queue and arrival/death workflows, all mortuary and roster routes, and other common API workflows. Mortuary requests are grouped under Admin and Mortuary Attendant; roster examples are in Staff Roster. It is not exhaustive for every registered API route. Use [the API reference](../api-v1.md) and the PHP route file for the authoritative route list and access rules.
