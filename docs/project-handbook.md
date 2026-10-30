# Hospital Management System: Project Handbook

This handbook explains the application as it is wired in the repository today. It links each major workflow to the routes, controllers, services, models, and configuration that implement it.

## At a glance

This is a Laravel application with two user-facing surfaces:

- A server-rendered staff and patient portal built with Blade views.
- A versioned JSON API at `/api/v1`, intended for mobile and other API clients.

Both surfaces use the same application services and shared database schema. Hospital-owned data is separated by a per-request hospital context and Eloquent global scopes; it is not a separate database per hospital.

Start with the [Composer manifest](../composer.json), [web routes](../routes/web.php), [API routes](../routes/api.php), [application bootstrap](../bootstrap/app.php), and [HMS migrations](../database/migrations).

## How a request moves through the system

1. Laravel loads the application's route files and middleware aliases from [bootstrap/app.php](../bootstrap/app.php). The browser routes are in [routes/web.php](../routes/web.php); the versioned API is in [routes/api.php](../routes/api.php); Breeze-style sign-in, registration, and password routes are in [routes/auth.php](../routes/auth.php).
2. The route group applies the relevant authentication, active-account, hospital, role, and feature-module checks. The aliases and API exception handling are registered in [bootstrap/app.php](../bootstrap/app.php); the individual checks live in [RoleMiddleware.php](../app/Http/Middleware/RoleMiddleware.php), [EnsureUserIsActive.php](../app/Http/Middleware/EnsureUserIsActive.php), [SetHospitalContext.php](../app/Http/Middleware/SetHospitalContext.php), and [EnsureHospitalModule.php](../app/Http/Middleware/EnsureHospitalModule.php).
3. Controllers adapt the HTTP request to the use case. Form Request classes validate and authorize input; for example, the shared request classes are under [app/Http/Requests](../app/Http/Requests), with API-specific requests under [app/Http/Requests/Api/V1](../app/Http/Requests/Api/V1).
4. Controllers delegate business operations to services under [app/Services](../app/Services). Services coordinate transactions, domain rules, billing, notifications, and model updates.
5. Eloquent models in [app/Models](../app/Models) represent persisted records and relations. Browser controllers return Blade templates in [resources/views](../resources/views); API controllers return JSON and API Resources under [app/Http/Resources/Api/V1](../app/Http/Resources/Api/V1).

The browser UI is not a JavaScript single-page application. Vite compiles the CSS and JavaScript entry points in [vite.config.js](../vite.config.js); the small Alpine entry point is [resources/js/app.js](../resources/js/app.js).

## Authentication, roles, and hospital isolation

### Browser and API authentication

- Browser sign-in, registration, password reset, and logout routes are declared in [routes/auth.php](../routes/auth.php). The default registration handler is [RegisteredUserController.php](../app/Http/Controllers/Auth/RegisteredUserController.php).
- API login issues Laravel Sanctum tokens. Protected API groups use `auth:sanctum`; the API authentication and account flows are in [AuthController.php](../app/Http/Controllers/Api/V1/AuthController.php), and the complete endpoint guide is [docs/api-v1.md](./api-v1.md).
- Staff and patient invitations are separate account-onboarding flows. See [StaffInvitationService.php](../app/Services/StaffInvitationService.php), [PortalInvitationService.php](../app/Services/PortalInvitationService.php), [PatientInvitationAcceptanceService.php](../app/Services/PatientInvitationAcceptanceService.php), and [routes/web.php](../routes/web.php).
- Disabled accounts are rejected by [EnsureUserIsActive.php](../app/Http/Middleware/EnsureUserIsActive.php); for API requests this also revokes the current Sanctum token.

### Role and department access

The available role values and role helpers are defined in [Role.php](../app/Enums/Role.php) and [User.php](../app/Models/User.php). Route groups specify allowed roles, but services also enforce domain-specific rules such as department ownership and whether the current doctor is assigned to a visit. See [RoleMiddleware.php](../app/Http/Middleware/RoleMiddleware.php), [ConsultationService.php](../app/Services/ConsultationService.php), and [TriageService.php](../app/Services/TriageService.php).

### Hospital context and data scoping

`HospitalContext` is a scoped service, registered by [AppServiceProvider.php](../app/Providers/AppServiceProvider.php). For authenticated browser and API requests, [SetHospitalContext.php](../app/Http/Middleware/SetHospitalContext.php) obtains the hospital from the signed-in user's `hospital_id`, rejects accounts without an active hospital, and sets the request context.

The provider adds a hospital global scope to the models in [app/Models](../app/Models): reads are constrained to the current hospital, and creating a hospital-owned record stamps its `hospital_id`. When a web request has no hospital context, tenant-owned model reads fail closed. `Hospital` itself is not tenant-scoped. The context implementation is [HospitalContext.php](../app/Support/HospitalContext.php), and the hospital/module relations are in [Hospital.php](../app/Models/Hospital.php), [HospitalModule.php](../app/Models/HospitalModule.php), and [HospitalSetting.php](../app/Models/HospitalSetting.php).

Feature modules are enabled per hospital and checked by [EnsureHospitalModule.php](../app/Http/Middleware/EnsureHospitalModule.php). The module catalog and provisioning controls are in [HospitalOnboardingController.php](../app/Http/Controllers/GlobalAdmin/HospitalOnboardingController.php); workflow-specific admission sections also use [EnsureWorkflowSection.php](../app/Http/Middleware/EnsureWorkflowSection.php) and [HospitalWorkflowSettingsController.php](../app/Http/Controllers/HospitalWorkflowSettingsController.php).

### Platform administration and an existing documentation mismatch

Platform-level admin access is represented by `is_global_admin` on the normal [User model](../app/Models/User.php), and protected by [EnsureGlobalAdmin.php](../app/Http/Middleware/EnsureGlobalAdmin.php). The [global-admin route group](../routes/web.php) provisions and manages hospital workspaces. An existing user can be promoted with the command declared in [PromoteGlobalAdmin.php](../app/Console/Commands/PromoteGlobalAdmin.php): `php artisan saas:promote-global-admin {email}`.
 
The current implementation does **not** match several statements in [docs/multi-tenancy.md](./multi-tenancy.md): that guide describes host/subdomain-based tenant resolution, a separate `platform_users` table, and public `/register` creating a hospital. In the current route wiring, `/register` uses [RegisteredUserController.php](../app/Http/Controllers/Auth/RegisteredUserController.php), while hospital workspace provisioning is handled by [HospitalOnboardingController.php](../app/Http/Controllers/GlobalAdmin/HospitalOnboardingController.php); platform status is stored on `users`. Treat the tenancy guide's conflicting deployment instructions as unverified until it is reconciled with the code.

## Core workflows

### Patient registration and front desk

Staff use the patient routes in [routes/web.php](../routes/web.php) and [PatientController.php](../app/Http/Controllers/PatientController.php). The registration use case is implemented in [PatientRegistrationService.php](../app/Services/PatientRegistrationService.php): it creates a patient, stores an optional photo through [PatientPhotoService.php](../app/Services/PatientPhotoService.php), and creates a registration invoice when the hospital has configured a registration fee. Patient records and their account/portal association are represented by [Patient.php](../app/Models/Patient.php) and [User.php](../app/Models/User.php).

Front-desk walk-ins and appointment check-ins create visits through [VisitCheckInService.php](../app/Services/VisitCheckInService.php). These actions are transactional, prevent an additional active visit or admission for the patient, create or update the linked appointment as appropriate, and connect the visit to billing and the downstream clinical queues.

### Appointment booking

Staff, doctors, and patients use different route groups and permissions in [routes/web.php](../routes/web.php) and [routes/api.php](../routes/api.php). Shared booking rules live in [AppointmentBookingService.php](../app/Services/AppointmentBookingService.php); doctor availability is managed by [DoctorAvailabilityService.php](../app/Services/DoctorAvailabilityService.php). Booking limits, hours, slot length, lead time, and lab capacity are in [config/hms.php](../config/hms.php). The records and states are represented by [Appointment.php](../app/Models/Appointment.php), [DoctorAvailability.php](../app/Models/DoctorAvailability.php), and [AppointmentStatus.php](../app/Enums/AppointmentStatus.php).

### Billing and payments

[BillingService.php](../app/Services/BillingService.php) creates a visit invoice and synchronizes consultation, lab, drug, and registration lines without adding the same billable item twice. Front-desk and cashier operations are handled by [BillingController.php](../app/Http/Controllers/BillingController.php) and [BillingOperationsService.php](../app/Services/BillingOperationsService.php); patient invoice behavior is handled by [PortalInvoiceService.php](../app/Services/PortalInvoiceService.php). The main records are [Invoice.php](../app/Models/Invoice.php), [InvoiceItem.php](../app/Models/InvoiceItem.php), and [Payment.php](../app/Models/Payment.php).

For online payments, [OnlinePaymentService.php](../app/Services/OnlinePaymentService.php) creates an attempt, initializes checkout through [PaystackGateway.php](../app/Services/PaystackGateway.php), and verifies the gateway result before applying payment to invoice items. The browser callback and signed gateway webhook are wired in [routes/web.php](../routes/web.php), [PortalInvoiceController.php](../app/Http/Controllers/PortalInvoiceController.php), and [PaystackWebhookController.php](../app/Http/Controllers/PaystackWebhookController.php). Confirmation is safe to retry; amount/currency mismatches or items paid elsewhere are flagged for review rather than silently reconciled.

### Triage, consultation, and laboratory

The main outpatient path is:

1. A visit enters `waiting_triage`.
2. Nursing triage records vitals and moves it to `waiting_doctor`.
3. A doctor starts the visit, records consultation notes and diagnosis, and may add prescriptions or lab requests.
4. The doctor completes the visit, or starts an inpatient admission. Consultation completion is blocked while requested lab work remains incomplete, except for the emergency deceased disposition.

The state names are in [VisitStatus.php](../app/Enums/VisitStatus.php), and the visit's relations and open-consultation rule are in [Visit.php](../app/Models/Visit.php). Triage queues and permissions are implemented by [TriageService.php](../app/Services/TriageService.php); consultation queue, assignment, and completion rules are in [ConsultationService.php](../app/Services/ConsultationService.php). The corresponding web and API controllers are [TriageController.php](../app/Http/Controllers/TriageController.php), [ConsultationController.php](../app/Http/Controllers/ConsultationController.php), and [ClinicalController.php](../app/Http/Controllers/Api/V1/ClinicalController.php).

Doctors create lab requests during an active consultation. [LabWorkflowService.php](../app/Services/LabWorkflowService.php) owns request, result, and report behavior; [LabReferralService.php](../app/Services/LabReferralService.php) handles standalone referrals and walk-in lab clients. Results and attachments are represented by [LabRequest.php](../app/Models/LabRequest.php) and [LabAttachment.php](../app/Models/LabAttachment.php), with private attachment handling in [LabAttachmentService.php](../app/Services/LabAttachmentService.php). Result processing requires payment for non-emergency requests; lab-only referral visits close when their pending requests are complete.

### Prescriptions, pharmacy, and medication administration

Doctors record prescriptions through [PrescriptionService.php](../app/Services/PrescriptionService.php). Pharmacy staff manage the catalogue, stock, and dispensing through [PharmacyService.php](../app/Services/PharmacyService.php) and [DrugCatalogService.php](../app/Services/DrugCatalogService.php). Stock locking, reservation, fulfillment, release, and expiry are centralized in [DrugInventoryService.php](../app/Services/DrugInventoryService.php); related records include [Drug.php](../app/Models/Drug.php), [Prescription.php](../app/Models/Prescription.php), and [PrescriptionStockReservation.php](../app/Models/PrescriptionStockReservation.php).

Where a prescription requires in-facility administration, nurses record doses through [MedicationAdministrationService.php](../app/Services/MedicationAdministrationService.php). That service checks that the medication is paid, dispensed, and within the visit/department workflow before recording administration. Administration and waiver records are represented by [MedicationAdministration.php](../app/Models/MedicationAdministration.php).

### Emergency care, inpatient admissions, and mortuary

The A&E workflow has its own check-in, observation, progress-note, and emergency-pharmacy paths. See [EmergencyService.php](../app/Services/EmergencyService.php), [EmergencyProgressNoteService.php](../app/Services/EmergencyProgressNoteService.php), [EmergencyPharmacyService.php](../app/Services/EmergencyPharmacyService.php), and the [web EmergencyController.php](../app/Http/Controllers/EmergencyController.php). The API counterpart is [Api EmergencyController.php](../app/Http/Controllers/Api/V1/EmergencyController.php), routed from [routes/api.php](../routes/api.php). Emergency visits can follow the same clinical path with emergency prioritization, or take the brought-in-dead workflow; the API exposes arrival registration, the paginated emergency queue, progress notes, death confirmation, certificate downloads, and mortuary handoff receipt. Emergency-pharmacy inventory and item-use endpoints remain a separate API-parity task.

An attending doctor can create an inpatient admission from an open consultation. [AdmissionService.php](../app/Services/AdmissionService.php) enforces the active-admission and ward rules, updates the visit, and provides discharge, care, and result-review operations. Admission chart behavior is split into [AdmissionCareChartService.php](../app/Services/AdmissionCareChartService.php); representative records include [Admission.php](../app/Models/Admission.php), [AdmissionVital.php](../app/Models/AdmissionVital.php), [AdmissionNote.php](../app/Models/AdmissionNote.php), and [AdmissionFluidEntry.php](../app/Models/AdmissionFluidEntry.php).

Emergency or inpatient deaths can create a mortuary case. [MortuaryCaseService.php](../app/Services/MortuaryCaseService.php) handles receipt, movement, release, and storage charges; [MortuaryCase.php](../app/Models/MortuaryCase.php) and [MortuaryCaseEvent.php](../app/Models/MortuaryCaseEvent.php) hold the case and its event history. Death certificate generation is handled by [HospitalDeathCertificateService.php](../app/Services/HospitalDeathCertificateService.php). The API exposes walk-in intake, case search/details, receipt, movement, and release through [Api MortuaryController.php](../app/Http/Controllers/Api/V1/MortuaryController.php), using the [MortuaryCaseResource.php](../app/Http/Resources/Api/V1/MortuaryCaseResource.php) response shape.

Staff assignments are managed for Monday-to-Sunday weeks by [NurseRosterService.php](../app/Services/NurseRosterService.php) and stored in [StaffDuty.php](../app/Models/StaffDuty.php). It applies department and manager scope, supports 12-hour morning/night shifts and one weekly 24-hour doctor duty, and notifies the assigned staff member. The browser controller is [NurseRosterController.php](../app/Http/Controllers/NurseRosterController.php); the API interface is [StaffRosterController.php](../app/Http/Controllers/Api/V1/StaffRosterController.php).

### Maternity: antenatal history, delivery, newborns, and billing

Maternity is an extension of the inpatient workflow, not a separate registration or medication system. The hospital must enable both the `inpatient` and `maternity` modules. A patient first has an active [Admission.php](../app/Models/Admission.php) in an eligible ward; authorized maternity staff then open one [MaternityEpisode.php](../app/Models/MaternityEpisode.php) for that admission.

The workflow is:

1. `MaternityService::startLabor()` locks the admission, verifies it is active, checks ward/doctor access, prevents a duplicate episode, and records the labor assessment and whether the patient brought delivery materials.
2. Medication, injections, IV fluids, observations, and other maternal care remain on the linked admission. Do not duplicate those records inside maternity.
3. `MaternityService::recordDelivery()` locks the episode and records the delivery time, mode, notes, hospital-supplied pack, and one or more newborn outcomes in a transaction.
4. Every live birth creates a separate patient record and hospital number, then links it through [MaternityNewborn.php](../app/Models/MaternityNewborn.php). Non-live outcomes remain clinical newborn records and do not create patient identities.
5. The service adds the hospital-configured delivery-mode fee and optional supply-pack fee to the mother's visit invoice through [BillingService.php](../app/Services/BillingService.php). Stable source keys make the billing operation idempotent.
6. Postpartum notes can be updated while the episode remains linked to the inpatient admission and its access rules.

The browser entry points are the maternity section in [routes/web.php](../routes/web.php), [MaternityController.php](../app/Http/Controllers/MaternityController.php), and [resources/views/maternity](../resources/views/maternity). The equivalent mobile endpoints are grouped in [routes/api.php](../routes/api.php), handled by [Api/V1/MaternityController.php](../app/Http/Controllers/Api/V1/MaternityController.php), and serialized by [MaternityEpisodeResource.php](../app/Http/Resources/Api/V1/MaternityEpisodeResource.php). Both surfaces use the same requests and service, so validation, authorization, state transitions, and billing must remain identical.

Hospital-specific maternity prices are stored on [HospitalSetting.php](../app/Models/HospitalSetting.php), edited through hospital settings, and exposed to API clients by the maternity fees endpoint. The `maternity:seed-demo` command creates ten synthetic journeys for an explicitly selected hospital; see the command documentation under “Development and test data” below.

### Settings, notifications, reports, and data transfer

- Hospital branding, fees, payment settings, and protected uploaded assets are managed by [HospitalSettingsService.php](../app/Services/HospitalSettingsService.php), [SettingsController.php](../app/Http/Controllers/SettingsController.php), and [HospitalSetting.php](../app/Models/HospitalSetting.php).
- Notifications are routed through [WorkflowNotificationService.php](../app/Services/WorkflowNotificationService.php) and [HospitalActionNotification.php](../app/Notifications/HospitalActionNotification.php); the browser notification routes are in [routes/web.php](../routes/web.php).
- Finance summaries and PDF export are handled by [FinanceReportService.php](../app/Services/FinanceReportService.php) and [FinanceController.php](../app/Http/Controllers/FinanceController.php).
- CSV import/export is shared between the browser and API through [CsvTransferService.php](../app/Services/CsvTransferService.php), [CsvTransferController.php](../app/Http/Controllers/CsvTransferController.php), and [Api/V1/CsvTransferController.php](../app/Http/Controllers/Api/V1/CsvTransferController.php).
- Nurse and staff duty rosters are backed by [NurseRosterService.php](../app/Services/NurseRosterService.php), [NurseRosterController.php](../app/Http/Controllers/NurseRosterController.php), and [StaffDuty.php](../app/Models/StaffDuty.php).

## API shape

All mobile API endpoints are registered under `/api/v1` in [routes/api.php](../routes/api.php). The API uses Sanctum bearer tokens and has separate controller and resource layers under [app/Http/Controllers/Api/V1](../app/Http/Controllers/Api/V1) and [app/Http/Resources/Api/V1](../app/Http/Resources/Api/V1). API authentication, active-account checks, hospital scoping, and role/module gates are applied at route-group level. Inpatient admission endpoints are also available through [AdmissionController.php](../app/Http/Controllers/Api/V1/AdmissionController.php) and [AdmissionResource.php](../app/Http/Resources/Api/V1/AdmissionResource.php), using the same [AdmissionService.php](../app/Services/AdmissionService.php) and [AdmissionCareChartService.php](../app/Services/AdmissionCareChartService.php) as the web routes.

[ApiResponseEnvelope.php](../app/Http/Middleware/ApiResponseEnvelope.php) wraps JSON responses in a consistent `success`, `message`, `data`, and `errors` structure while leaving streamed file responses intact. API exception mapping is configured in [bootstrap/app.php](../bootstrap/app.php). Consult [docs/api-v1.md](./api-v1.md) for the actual endpoint list, request bodies, role access, pagination, and Postman instructions.

## Data model and schema evolution

The application uses migrations as the schema source of truth. The initial HMS schema and subsequent feature migrations are in [database/migrations](../database/migrations); the hospital/tenant and module configuration migration is [2026_10_27_090000_create_hospital_tenants_and_configuration.php](../database/migrations/2026_10_27_090000_create_hospital_tenants_and_configuration.php).

Core records connect as follows:

- A `Patient` has `Visit` records and may have appointments, invoices, and a patient-portal `User`.
- An `Appointment` can become a `Visit` at check-in.
- A `Visit` connects clinical notes/vitals, lab requests, prescriptions, an invoice, and optionally an `Admission` or `MortuaryCase`.
- An `Invoice` contains individually payable `InvoiceItem` rows; payments settle items.
- Hospital configuration and enabled modules belong to a `Hospital`.

The primary models to trace these relations are [Patient.php](../app/Models/Patient.php), [Appointment.php](../app/Models/Appointment.php), [Visit.php](../app/Models/Visit.php), [Invoice.php](../app/Models/Invoice.php), [InvoiceItem.php](../app/Models/InvoiceItem.php), [Admission.php](../app/Models/Admission.php), and [Hospital.php](../app/Models/Hospital.php).

## Developer editing guide

### Source directory map

| Location | Responsibility | Edit here when |
| --- | --- | --- |
| `app/Enums` | Canonical workflow states and roles | Adding a persisted status or a role understood across layers |
| `app/Http/Controllers` | Browser request/response adapters | Adding or changing a Blade page or browser action |
| `app/Http/Controllers/Api/V1` | JSON request/response adapters | Adding or changing a version 1 mobile endpoint |
| `app/Http/Requests` | Authorization and validation | Changing accepted input or who may submit an operation |
| `app/Http/Resources/Api/V1` | Stable JSON representation | Changing an API response field; treat removals/renames as breaking changes |
| `app/Http/Middleware` | Cross-cutting request gates and envelopes | Changing authentication, tenant/module access, roles, or response wrapping |
| `app/Models` | Persistence, casts, relationships, query scopes | Changing how a database record is represented or related |
| `app/Services` | Shared business rules and transactions | Changing a workflow used by web and API |
| `app/Mail` and `app/Notifications` | Outbound user communication | Changing email or in-app notification payloads and templates |
| `app/Providers` | Application bootstrapping | Changing global model behavior, rate limits, shared view data, or container bindings |
| `app/Support` | Small cross-cutting state holders | Changing request-scoped infrastructure such as the hospital context |
| `app/View/Components` | Blade layout/component adapters | Changing which shared layout a component renders |
| `config` | Environment-independent defaults and declarative menus | Changing defaults; secrets must stay in `.env` |
| `database/migrations` | Versioned schema history | Adding columns, tables, constraints, and indexes through a new migration |
| `database/factories` | Test record defaults and states | Making tests create valid domain records consistently |
| `database/seeders` | Intentional bootstrap/demo datasets | Adding reference data or repeatable development scenarios |
| `resources/views` | Blade presentation | Changing browser UI, forms, printable reports, or email HTML |
| `resources/js` and `resources/css` | Shared browser behavior and styling | Changing Alpine components, polling, or global/Tailwind styles |
| `resources/csv-templates` | Import contracts and example data | Changing supported CSV columns together with `CsvTransferService` |
| `routes/web.php` | Staff, patient portal, invitation, webhook routes | Wiring a browser action and its middleware/route name |
| `routes/api.php` | Versioned JSON routes | Wiring a mobile action with matching auth, tenant, role, and module gates |
| `routes/auth.php` | Browser identity routes | Changing login, registration, password, or email-verification routing |
| `routes/console.php` | Scheduled and closure commands | Changing tenant-aware maintenance jobs or their frequency |

### The safe change path

For a workflow available on both web and API, make changes in this order:

1. Start with the migration and model when persisted data changes. Add a new migration; never rewrite a migration that may already have run outside your machine.
2. Put authorization and validation in a shared Form Request when both surfaces accept the same operation. Route middleware is the outer gate; the request/service remains the final protection.
3. Implement the state transition in a service. Use a transaction when the operation updates multiple clinical, stock, billing, or identity records. Lock rows when two submissions could create duplicates or overspend stock.
4. Keep the web controller limited to validated input, service invocation, redirects, and flash messages. Keep the API controller limited to the same service invocation, resources, and HTTP status codes.
5. Update the Blade view and API Resource independently; they are different presentations of the same domain result.
6. Wire both route surfaces with equivalent role, module, active-account, and hospital middleware.
7. Add web feature tests and API feature tests for success, invalid data, unauthorized roles, cross-hospital access, duplicate submission, and billing side effects where relevant.
8. Update [docs/api-v1.md](./api-v1.md), the Postman collection when the contract changes, and the applicable workflow section in this handbook.

Avoid putting database queries in Blade, putting domain transitions directly in controllers, or implementing a mobile-only copy of a service rule. Those patterns cause web/API behavior to drift.

### Web surface

Browser routes use session authentication and generally apply `auth`, `auth.session`, `active`, and `hospital` before role/module gates. Blade forms use CSRF protection and named routes. Validation failures redirect back with old input; successful mutations redirect to a stable detail or list page with a flash message.

The staff navigation is driven by [config/menu.php](../config/menu.php), and patient navigation by [config/portal_menu.php](../config/portal_menu.php). Menu visibility improves usability but is never authorization: a hidden link does not protect a route. When adding a page, check the route group, controller action, Form Request, service rule, menu visibility, Blade error handling, and responsive behavior.

### API surface

API v1 routes use Sanctum bearer tokens. Protected hospital endpoints apply `auth:sanctum`, `active`, and `hospital`; role and module gates then narrow access. Platform-admin identity and hospital-management endpoints are deliberately outside tenant middleware. Do not move them into a hospital group.

Return models through an API Resource rather than exposing raw Eloquent serialization. Preserve the response envelope supplied by [ApiResponseEnvelope.php](../app/Http/Middleware/ApiResponseEnvelope.php), pagination metadata, and established HTTP status codes. Adding an optional field is usually backward compatible; renaming/removing a field or changing its type requires a new API version or a coordinated client migration.

Use [docs/api-v1.md](./api-v1.md) as the endpoint contract. The collection in [docs/postman](./postman) is an executable aid, not the source of truth; routes, requests, services, resources, and tests define actual behavior.

### Configuration ownership

- [config/hms.php](../config/hms.php) contains scheduling and pharmacy reservation defaults used by shared services.
- [config/menu.php](../config/menu.php) and [config/portal_menu.php](../config/portal_menu.php) declare navigation visibility.
- [config/services.php](../config/services.php) maps third-party environment credentials, currently including Paystack. Never commit real keys.
- Laravel's standard configuration files (`app`, `auth`, `cache`, `database`, `filesystems`, `logging`, `mail`, `queue`, and `session`) control framework infrastructure. Prefer environment variables for deployment differences and call `config()`, never `env()`, from application code.
- Hospital-specific operational settings belong in `hospital_settings`, not global config, because two hospitals may require different branding, charges, or workflow choices.

After changing environment-backed configuration in a deployed environment, rebuild the Laravel configuration cache. Configuration cache behavior means editing `.env` alone may not affect a running cached application.

### Database and tenant safety

Most models are hospital-owned and receive a global scope from [AppServiceProvider.php](../app/Providers/AppServiceProvider.php). HTTP reads fail closed when no hospital is resolved, and creates receive the current `hospital_id`. `Hospital` is the main exception; global administrators also have no hospital ID. User and invitation lookups have narrow bootstrapping exceptions documented in the provider.

This protection does not remove the need to reason about tenant boundaries. Console commands have no authenticated user and must set [HospitalContext.php](../app/Support/HospitalContext.php) explicitly for every hospital they process. Cross-hospital tests should create two hospitals and prove a user from one cannot read or mutate records from the other.

Migration files are historical source code. Create a follow-up migration for schema changes, use explicit table names rather than application models, and make `down()` reverse only what `up()` added. Add indexes for foreign keys and frequent tenant/status/date filters. Do not hand-edit `database/database.sqlite`; rebuild or migrate it through Artisan.

### Resources and import templates

Blade comments use `{{-- --}}` so maintenance notes are not sent to the browser. JavaScript and CSS comments should explain lifecycle, browser, accessibility, or layout constraints rather than repeat selectors and assignments. CSV templates cannot contain comment rows: they are parsed as data. Document new columns in [resources/csv-templates/README.md](../resources/csv-templates/README.md) and update validation/import/export behavior together in [CsvTransferService.php](../app/Services/CsvTransferService.php).

### Commenting standard

Comments in this repository should preserve decisions that names and types cannot show: tenant exceptions, authorization assumptions, transaction boundaries, idempotency keys, concurrency locks, external gateway behavior, clinical state invariants, and why web/API paths share a service. Class docblocks describe responsibility and important boundaries; method PHPDoc adds array shapes or non-obvious contracts. Do not add comments that merely narrate the next line, and remove or update a comment in the same change that invalidates it.

### Development and test data

Factories are preferred for isolated automated tests. Seeders are for repeatable reference or demonstration datasets. The maternity demo command is hospital-scoped and intentionally refuses production unless explicitly forced:

```bash
php artisan maternity:seed-demo --hospital=1
php artisan maternity:seed-demo --hospital=1 --refresh
```

The first command leaves an existing marked demo cohort untouched. `--refresh` deletes and rebuilds only marked maternity demo records for the selected hospital; it must not delete genuine patients. Use `--force` in production only when synthetic clinical and billing records are genuinely intended.

### Verification checklist

Before handing over a change:

- Run the narrowest relevant feature tests, including the corresponding web and API test files when both surfaces changed.
- Run `vendor/bin/pint --dirty --format agent` after PHP edits.
- Run `npm run build` when CSS, JavaScript, or Vite-managed assets changed.
- Inspect `php artisan route:list --except-vendor` after route or middleware changes.
- Confirm a second hospital cannot see the changed records.
- Confirm duplicate form submissions or API retries do not duplicate bills, stock movements, payments, or clinical episodes.
- Confirm secrets, patient data, generated files, and local databases are not included in the commit.

## Running and maintaining the project

The scripts and dependency versions are declared in [composer.json](../composer.json) and [package.json](../package.json). `composer run setup` installs PHP and JavaScript dependencies, prepares the environment, runs migrations, and builds assets; review that script before using it against a database with data. `composer run dev` starts the configured local development command. `npm run build` builds frontend assets.

The scheduled maintenance commands are declared in [routes/console.php](../routes/console.php): pharmacy reservation expiry runs every minute, and mortuary storage charges accrue daily. Production must run Laravel's scheduler for these tasks to execute. The relevant services are [DrugInventoryService.php](../app/Services/DrugInventoryService.php) and [MortuaryCaseService.php](../app/Services/MortuaryCaseService.php).

Run the PHPUnit suite with `php artisan test`. The current checked-in tests are in [tests/Feature](../tests/Feature), [tests/Unit](../tests/Unit), and the shared [tests/TestCase.php](../tests/TestCase.php).

## Where to go next

- For API integration: [API v1 guide](./api-v1.md) and [Postman collection guide](./postman/README.md).
- For current tenancy notes (including the implementation mismatch called out above): [multi-tenancy notes](./multi-tenancy.md).
- For route-level behavior: [web routes](../routes/web.php), [API routes](../routes/api.php), and [auth routes](../routes/auth.php).
