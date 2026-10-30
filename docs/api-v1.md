# HMS API v1

Base path: `/api/v1`. Existing browser routes are unchanged.

For role-grouped Postman examples, including inpatient actions, see the [Postman guide](postman/README.md). The collection is not exhaustive; the endpoint tables in this document are the API reference.

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

## Accident & Emergency

These endpoints require the hospital's `emergency` module. Records are scoped to the current hospital, and access follows the existing A&E role and department checks. Emergency observations and doctor consultations also use the shared `/triage` and `/consultations` endpoints above.

| Method | Endpoint | Access | Purpose |
|---|---|---|---|
| GET | `/emergency?q=...` | Admin, receptionist, nurse, doctor with A&E access | Active emergency queue, mortuary handoffs, brought-in-dead cases, and optional patient search |
| POST | `/emergency/patients/{patient}` | Admin, receptionist, nurse with A&E registration access | Check in an identified patient; rejects an existing active visit or admission |
| POST | `/emergency/unidentified` | Admin, receptionist, nurse with A&E registration access | Create an unidentified patient record and emergency arrival |
| POST | `/emergency/visits/{visit}/notes` | A&E doctor or nurse | Add a medical or nursing progress note to active emergency care |
| POST | `/emergency/visits/{visit}/brought-in-dead/confirm` | A&E doctor | Confirm a brought-in-dead arrival and document mortuary transfer or direct family release |
| POST, GET | `/emergency/visits/{visit}/brought-in-dead/death-certificate` | POST: A&E doctor; GET: admin or A&E doctor | Issue or download the hospital death certificate (PDF) after death confirmation |
| POST | `/emergency/mortuary/{mortuaryCase}/receive` | Admin, nurse with handoff access; `mortuary` module also required | Receive an approved emergency mortuary transfer after the intake fee is paid |

Arrival body: `complaint`, `arrival_mode` (`walk_in`, `ambulance`, `referred`, or `other`), and optional `brought_in_dead`. Unidentified arrivals also require `gender` and accept optional `estimated_age`, `next_of_kin_name`, and `next_of_kin_phone`.

Brought-in-dead confirmation requires `cause_of_death`, `death_clinical_summary`, `disposition`, and `approval_confirmed=true`. Direct family release additionally requires `released_to`, `recipient_relationship`, and `release_authority`. A&E registration, triage priority, and attending-doctor rules are enforced by the same services and request validation used by the web workflow.

## Mortuary

These endpoints require the hospital's `mortuary` module and an admin or mortuary attendant account. Cases are hospital-scoped. The emergency handoff endpoint is also available under `/emergency/mortuary/{mortuaryCase}/receive` to authorized A&E staff.

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/mortuary?q=...&status=awaiting_transfer\|received\|released` | Search cases, accrue due storage fees, and return a paginated case list with the hospital fee schedule |
| POST | `/mortuary/walk-in` | Open an external walk-in case and create its invoice |
| GET | `/mortuary/{mortuaryCase}` | View case, custody event history, release and death-certificate details, and linked invoice |
| POST | `/mortuary/{mortuaryCase}/receive` | Record physical receipt and storage location after the intake fee is paid |
| POST | `/mortuary/{mortuaryCase}/move` | Change storage location and append a custody event |
| POST | `/mortuary/{mortuaryCase}/release` | Release a body after all mortuary charges have been paid |

Walk-in fields: `first_name`, `last_name`, `gender` (`male`, `female`, `unknown`), `deceased_at`, `external_contact_name`, `external_contact_phone`, and optional `estimated_age`, `external_contact_address`, and `handoff_note`. Receipt fields are `storage_location` and optional `body_tag_number` and `handoff_note`. Move fields are `storage_location` and optional `note`. Release requires `released_to`, `recipient_relationship`, and `release_authority`, with optional `recipient_id_reference` and `release_note`. If receipt or release is blocked by outstanding fees, the API returns HTTP 402 and leaves custody status unchanged.

## Staff duty roster

Roster routes require the `staff_roster` module. The same role and department scope as the web roster applies: administrators can manage all staff, chief nurses can manage nurses, and department heads can manage staff in their own department. Other permitted staff can view their own shifts and same-shift coworkers.

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/nursing/roster?week=YYYY-MM-DD&department={id}&search=...` | View the selected Monday-starting week, staff shifts, coverage, departments, and same-shift coworkers |
| PUT | `/nursing/roster/{staff}` | Replace the staff member's assignments for one week and notify them |

Use `week` as a date in the week to view; the response normalizes it to Monday. Assignment requires `week_start` set to a Monday. Non-doctor assignments use `morning_days` and `night_days` arrays containing day numbers 1 through 7; a staff member cannot have both shifts on a day. Doctor assignments use `doctor_day` (1 through 7) for one 24-hour duty. Assignments are saved as the full week, so omitted shift arrays clear those shifts.

## Inpatient admissions

These endpoints require the hospital's `inpatient` module. Admissions are scoped to the current hospital; doctors and nurses are further restricted to their ward/department by the existing admission service.

| Method | Endpoint | Access | Purpose |
|---|---|---|---|
| POST | `/consultations/{visit}/admit` | Attending doctor | Admit an open consultation to a ward |
| GET | `/admissions?search=...` | Admin, doctor, nurse | Paginated active and discharged admissions with queue counts |
| GET | `/admissions/{admission}` | Admin, doctor, nurse | Admission details, clinical visit, notes, observations, care charts, results, and access flags |
| POST | `/admissions/{admission}/notes` | Doctor, nurse | Add an inpatient progress note |
| POST | `/admissions/{admission}/vitals` | Admin, nurse | Record ward observations |
| POST | `/admissions/{admission}/prescriptions` | Doctor | Prescribe inpatient medication |
| POST | `/admissions/{admission}/lab-results/{labRequest}/review` | Admin, attending doctor | Review a completed result belonging to this admission |
| POST | `/admissions/{admission}/discharge` | Admin, authorized doctor | Record a discharge plan and close the inpatient visit |
| POST | `/admissions/{admission}/declare-deceased` | Admin, authorized doctor | Record an inpatient death and its disposition |
| POST, GET | `/admissions/{admission}/death-certificate` | POST: doctor; GET: admin/doctor | Issue and download a hospital death certificate (PDF) |
| POST | `/admissions/{admission}/sugar-checks` | Admin, nurse, doctor; workflow section enabled | Record a blood sugar check |
| POST | `/admissions/{admission}/fluid-entries` | Admin, nurse, doctor; workflow section enabled | Record fluid intake/output |
| POST | `/admissions/{admission}/oxygen` | Admin, nurse, doctor; workflow section enabled | Record oxygen administration |
| POST | `/admissions/{admission}/nebulizer-uses` | Admin, nurse, doctor; workflow section enabled | Record a nebulizer use |
| POST | `/admissions/{admission}/insulin-machine-blocks` | Admin, nurse, doctor; workflow section enabled | Start an insulin-machine block |
| POST | `/admissions/{admission}/insulin-machine-blocks/uses` | Admin, nurse, doctor; workflow section enabled | Record an insulin bottle use |
| POST, GET | `/admissions/{admission}/dama` | POST: admin/doctor; GET: admin/doctor/nurse | Record or view discharge against medical advice |
| GET | `/admissions/{admission}/dama/witnesses/{witness}` | Admin, doctor, nurse | Download a private DAMA witness image |

Admission body: `department_id` (a ward), `admission_diagnosis`, `admission_reason`, `initial_treatment_plan`, and optional `expected_discharge_date` (`YYYY-MM-DD`). The API uses the same service and validation rules as the web admission workflow.

Discharge requires `discharge_diagnosis`, `discharge_summary`, `discharge_medications`, `follow_up_instructions`, and `patient_discharge_instructions`. If any lab result is incomplete or unreviewed, include `pending_results_plan`. Clinical notes, ward vitals, prescriptions, care-chart values, death details, and DAMA fields use the same request fields and constraints as the corresponding web actions. The API returns JSON for data operations and streams certificate/witness downloads as files.

## Maternity

These endpoints require both the `inpatient` and `maternity` modules. Access is limited to hospital administrators, the attending doctor, doctors in the admission's department, and nurses assigned to that department. Cross-hospital records return 404; authenticated clinical staff outside the ward receive 403 before payload validation.

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/maternity/fees` | Current hospital's delivery and supply-pack prices |
| GET | `/maternity/episodes` | Paginated episodes visible to the caller |
| POST | `/maternity/admissions/{admission}/episodes` | Start labor for an active admission |
| GET | `/maternity/episodes/{maternityEpisode}` | Episode, admission, and newborn details |
| POST | `/maternity/episodes/{maternityEpisode}/delivery` | Record delivery, newborns, and applicable billing |
| PUT | `/maternity/episodes/{maternityEpisode}/postpartum` | Save the maternal postpartum summary |

Start-labor body:

```json
{
  "labor_started_at": "2026-10-29T14:00:00+01:00",
  "labor_assessment": "Initial assessment.",
  "patient_brought_delivery_materials": true
}
```

`labor_started_at` is optional and defaults to the current time; it cannot be in the future. The materials answer is required and must be a JSON boolean. Only one maternity episode may be opened for an admission.

Delivery body:

```json
{
  "delivery_at": "2026-10-29T16:20:00+01:00",
  "delivery_mode": "vaginal",
  "delivery_notes": "Uncomplicated delivery.",
  "hospital_supplied_delivery_pack": true,
  "delivery_material_notes": "Pack issued from the ward.",
  "newborns": [
    {
      "outcome": "live_birth",
      "first_name": "Baby",
      "last_name": "Patient",
      "gender": "female",
      "birth_weight_grams": 3250,
      "apgar_score_1_minute": 8,
      "apgar_score_5_minutes": 9
    }
  ]
}
```

`delivery_mode` accepts `vaginal`, `assisted_vaginal`, `cesarean`, or `other`. `delivery_at` is required, cannot be in the future, and cannot precede labor. One to 20 newborn entries are accepted. `outcome` accepts `live_birth` or `stillbirth`; a live birth requires first name, last name, and gender (`male` or `female`) and creates a linked patient record. Birth weight is optional from 1 to 15,000 grams; optional Apgar scores are integers from 0 to 10. A stillbirth remains a maternity birth record without creating a patient account.

The delivery operation is atomic: clinical records, linked live-birth patient records, and invoice items succeed or roll back together. It can run only once. The configured delivery fee is billed, and the delivery-pack fee is billed only when `hospital_supplied_delivery_pack` is `true`. A zero-fee schedule does not create an empty invoice.

Postpartum body:

```json
{
  "postpartum_notes": "Mother and baby transferred for routine postpartum care."
}
```

Postpartum notes are accepted only after delivery and while the linked admission remains active.

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
