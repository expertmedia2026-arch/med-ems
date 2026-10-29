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

## Running and maintaining the project

The scripts and dependency versions are declared in [composer.json](../composer.json) and [package.json](../package.json). `composer run setup` installs PHP and JavaScript dependencies, prepares the environment, runs migrations, and builds assets; review that script before using it against a database with data. `composer run dev` starts the configured local development command. `npm run build` builds frontend assets.

The scheduled maintenance commands are declared in [routes/console.php](../routes/console.php): pharmacy reservation expiry runs every minute, and mortuary storage charges accrue daily. Production must run Laravel's scheduler for these tasks to execute. The relevant services are [DrugInventoryService.php](../app/Services/DrugInventoryService.php) and [MortuaryCaseService.php](../app/Services/MortuaryCaseService.php).

Run the PHPUnit suite with `php artisan test`. The current checked-in tests are in [tests/Feature](../tests/Feature), [tests/Unit](../tests/Unit), and the shared [tests/TestCase.php](../tests/TestCase.php).

## Where to go next

- For API integration: [API v1 guide](./api-v1.md) and [Postman collection guide](./postman/README.md).
- For current tenancy notes (including the implementation mismatch called out above): [multi-tenancy notes](./multi-tenancy.md).
- For route-level behavior: [web routes](../routes/web.php), [API routes](../routes/api.php), and [auth routes](../routes/auth.php).
