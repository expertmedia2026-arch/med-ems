# HMS API v1

Base path: `/api/v1`. Existing browser routes are unchanged.

For role-based Postman requests covering every registered API endpoint, import the files in [docs/postman](postman/README.md).

## Authentication and response format

Sign in with `POST /auth/login` using `email`, `password`, and optional `device_name`. The API returns a Sanctum bearer token. Send it on protected requests as:

```http
Authorization: Bearer <token>
Accept: application/json
```

`device_name` is a token label only. If omitted, it defaults to `mobile-app`. All JSON responses use this envelope:

```json
{
  "success": true,
  "message": "Request completed successfully.",
  "data": {},
  "errors": null
}
```

On errors, `success` is `false` and `data` is `null`; validation details are returned in `errors` by field. HTTP status codes remain meaningful (for example, 401 unauthenticated, 403 forbidden, 404 not found, and 422 validation/business-rule errors). Successful deletes return HTTP 200 with the envelope. Paginated endpoints include their items and pagination links/metadata under `data`; page sizes remain 10 or the existing feature pagination size. Attachment endpoints return the requested file directly.

## Public authentication and account access

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/auth/login` | Issue an access token |
| POST | `/auth/forgot-password` | Send a password reset link |
| POST | `/auth/reset-password` | Reset password with `token`, `email`, `password`, `password_confirmation` |
| POST | `/auth/staff-invitations/{token}/accept` | Accept staff invitation with `name` and confirmed password |
| POST | `/auth/patient-invitations/{token}/accept` | Accept patient invitation with confirmed password |

## Authenticated account and reference data

| Method | Endpoint | Access |
|---|---|---|
| GET | `/auth/me` | Any active user |
| DELETE | `/auth/logout` | Any active user; revokes current token |
| GET, PATCH | `/profile` | Any active user |
| PUT | `/profile/password` | Any active user; revokes all tokens after update |
| DELETE | `/profile` | Any active user; requires current password |
| GET | `/dashboard` | Staff |
| GET | `/departments` | Authenticated |
| GET | `/departments/{department}/consultation-types` | Authenticated |
| GET | `/doctors?department_id={id}` | Authenticated |
| GET | `/doctors/{doctor}/availability` | Authenticated |
| GET, POST | `/doctor/availability` | Doctor |
| DELETE | `/doctor/availability/{doctorAvailability}` | Owning doctor |

## Patient portal

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/patient/dashboard` | Patient summary, next appointment, last visit, balance |
| GET | `/patient/profile` | Own profile and health details |
| GET | `/patient/visits` | Own paginated visit history |
| GET | `/patient/visits/{visit}` | Own visit detail and released results |
| GET | `/patient/attachments/{attachment}` | Download own completed lab attachment |
| GET | `/appointments` | Own appointment list |
| POST | `/appointments` | Book own appointment |
| DELETE | `/appointments/{appointment}` | Cancel own eligible appointment |
| GET | `/appointments/lab-options?date=YYYY-MM-DD` | Active lab tests and available slots |
| GET | `/patient/invoices` | Own invoices and outstanding balance |
| GET | `/patient/invoices/{invoice}` | Own invoice and payable items |
| POST | `/patient/invoices/{invoice}/pay` | Start Paystack checkout with `item_ids` |
| GET | `/patient/payments/confirm?reference=...` | Verify checkout result after returning to the app |
| GET | `/patient/payments/{payment}/receipt` | Own receipt |

Consultation appointment body: `type=consultation`, `department_id`, `doctor_id`, `appointment_date` (`YYYY-MM-DD`), optional `reason`. Lab body: `type=lab`, `slot` (use the exact returned `slot` string), `lab_test_ids[]`, optional `reason`.


## Reception, patient registration, and staff appointments

Access is role-limited to the same roles as the web workflows.

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/staff/patients?q=...` | Search/list patients (admin, receptionist, nurse, doctor) |
| GET | `/staff/patients/{patient}` | Patient overview (admin, receptionist, nurse, doctor) |
| POST | `/staff/patients` | Register patient; accepts the existing patient details fields and returns any registration invoice |
| PUT | `/staff/patients/{patient}` | Update patient details |
| GET | `/staff/patients/{patient}/card` | Card data; registration payment required |
| POST | `/staff/patients/{patient}/walk-ins` | Walk-in check-in with department and consultation type |
| POST | `/staff/patients/{patient}/portal-invitation` | Send patient portal invitation |
| GET | `/staff/appointments?date=YYYY-MM-DD&department_id={id}&doctor_id={id}&hospital_number={number}` | Staff appointment list filtered by date, department, doctor, and patient hospital number |
| POST | `/staff/appointments` | Book for a patient using hospital number, department, doctor, date, reason |
| GET | `/staff/appointments/{appointment}` | Appointment detail (doctor) |
| PATCH | `/staff/appointments/{appointment}/cancel` | Cancel appointment |
| POST | `/staff/appointments/{appointment}/check-in` | Check in a booked consultation or lab visit on its scheduled date |

## Admin configuration

All routes in this section require the admin role.

| Method | Endpoint | Purpose |
|---|---|---|
| GET, POST | `/admin/departments` | List/create departments |
| PUT, DELETE | `/admin/departments/{department}` | Update/delete department |
| GET, POST | `/admin/consultation-types` | List/create department pricing |
| PUT | `/admin/consultation-types/{consultation_type}` | Update consultation type and price |
| GET, POST | `/admin/staff` | Filter/list or create staff |
| PUT | `/admin/staff/{staff}` | Update staff |
| PATCH | `/admin/staff/{staff}/toggle` | Activate/deactivate staff and revoke their tokens when disabled |
| POST | `/admin/staff-invitations` | Invite staff |
| POST, DELETE | `/admin/staff-invitations/{invitation}/resend`, `/admin/staff-invitations/{invitation}` | Resend or revoke staff invitation |
| GET, PUT | `/admin/hospital-settings` | Read/update hospital settings and logo |

## Triage and consultation

| Method | Endpoint | Access | Purpose |
|---|---|---|---|
| GET | `/triage/queue` | Admin, nurse | Waiting triage and waiting doctor lists |
| GET | `/triage/{visit}` | Admin, nurse | Load visit for triage; consultation payment is required |
| PUT | `/triage/{visit}/vitals` | Admin, nurse | Record vitals and send patient to doctor |
| GET | `/consultations/queue` | Doctor | Assigned/unassigned doctor queue |
| GET | `/consultations/options` | Doctor | In-stock drugs and active lab tests |
| GET | `/consultations/{visit}` | Doctor | Consultation workspace |
| GET | `/consultations/history/{visit}` | Doctor | Completed visit history |
| POST | `/consultations/{visit}/start` | Doctor | Start consultation |
| PUT | `/consultations/{visit}` | Doctor | Save complaint, diagnosis, and notes |
| PUT | `/consultations/{visit}/complete` | Doctor | Complete consultation; diagnosis required |
| POST, DELETE | `/consultations/{visit}/lab-requests`, `/consultations/{visit}/lab-requests/{labRequest}` | Doctor | Add/remove lab tests |
| POST, DELETE | `/consultations/{visit}/prescriptions`, `/consultations/{visit}/prescriptions/{prescription}` | Doctor | Add/remove prescriptions |

## Laboratory

| Method | Endpoint | Access | Purpose |
|---|---|---|---|
| GET | `/lab/requests?q=...` | Admin, lab, doctor | Paginated pending/completed requests; doctor sees own requests |
| GET | `/lab/requests/{labRequest}` | Admin, lab, doctor | Request details |
| PUT | `/lab/requests/{labRequest}` | Admin, lab | Save a result |
| POST | `/lab/requests/{labRequest}/result` | Admin, lab | Save result with multipart attachments (`result`, `files[]`) |
| GET | `/lab/visits/{visit}/report` | Admin, lab, doctor | Completed lab results for a visit |
| GET, DELETE | `/lab/attachments/{attachment}` | GET: admin/lab/doctor; DELETE: admin/lab | Download or remove a private attachment |
| GET, POST | `/lab/tests` | GET: admin/lab/doctor; POST: admin/lab | Test catalog and creation |
| PUT, PATCH | `/lab/tests/{labTest}`, `/lab/tests/{labTest}/toggle` | Admin, lab | Update or activate/deactivate test |
| GET | `/lab/referrals/form-options` | Admin, receptionist, lab | Existing patients and active tests |
| POST | `/lab/referrals` | Admin, receptionist, lab | Create external/walk-in lab referral; may return possible patient matches for confirmation |
| DELETE | `/lab/referrals/{visit}` | Admin, receptionist, lab | Cancel unpaid referral without completed results |

## Pharmacy

All routes require admin or pharmacist role.

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/pharmacy/dashboard` | Dispensing queue, low stock, today's dispensing |
| GET | `/pharmacy/inventory?q=...&low=1` | Paginated inventory |
| POST | `/pharmacy/inventory/{drug}/restock` | Restock using `quantity` |
| GET | `/pharmacy/visits/{visit}` | Completed visit prescriptions |
| POST | `/pharmacy/visits/{visit}/dispense` | Dispense selected `prescription_ids[]` |
| POST | `/pharmacy/drugs` | Add a drug and opening stock |
| PUT | `/pharmacy/drugs/{drug}` | Update drug |
| PATCH | `/pharmacy/drugs/{drug}/toggle` | Activate/deactivate drug |

## Cashier billing

All routes require admin or cashier role.

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/billing/invoices?status=unpaid&q=...` | Search/filter invoices and collection summary |
| GET | `/billing/invoices/{invoice}` | Invoice detail |
| POST | `/billing/invoices/{invoice}/payments` | Record payment using `item_ids[]`, `method` (`cash`, `transfer`, `pos`), optional `reference` |
| GET | `/billing/payments/{payment}/receipt` | Receipt details |

## Implementation notes

- API routes are in `routes/api.php`; controllers, request adapters, and resources are under `app/Http/{Controllers,Requests,Resources}/Api/V1`.
- APIs delegate to the current feature services. Existing web routes and page responses remain separate.
- `auth:sanctum`, active-account checks, and role middleware protect the endpoints. Patient visit, invoice, attachment, and appointment operations verify ownership.
- Paystack checkout returns a `checkout_url`; the mobile app opens it and then calls the confirmation endpoint with the gateway reference.
