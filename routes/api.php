<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AdmissionController;
use App\Http\Controllers\Api\V1\AppointmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillingOperationsController;
use App\Http\Controllers\Api\V1\ClinicalController;
use App\Http\Controllers\Api\V1\CsvTransferController;
use App\Http\Controllers\Api\V1\DashboardApiController;
use App\Http\Controllers\Api\V1\EmergencyController;
use App\Http\Controllers\Api\V1\GlobalAdminApiController;
use App\Http\Controllers\Api\V1\LabOperationsController;
use App\Http\Controllers\Api\V1\MaternityController;
use App\Http\Controllers\Api\V1\MortuaryController;
use App\Http\Controllers\Api\V1\NursingAdministrationController;
use App\Http\Controllers\Api\V1\PatientController;
use App\Http\Controllers\Api\V1\PharmacyOperationsController;
use App\Http\Controllers\Api\V1\ReferenceDataController;
use App\Http\Controllers\Api\V1\StaffRosterController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    // Public identity endpoints: login, password reset, and staff/patient invitation acceptance.
    Route::post('auth/login', [AuthController::class, 'store'])->middleware('throttle:api-login')->name('auth.login');
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:api-login')->name('auth.forgot-password');
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:api-login')->name('auth.reset-password');
    Route::post('auth/staff-invitations/{token}/accept', [AuthController::class, 'acceptStaffInvitation'])->name('auth.staff-invitations.accept');
    Route::post('auth/patient-invitations/{token}/accept', [AuthController::class, 'acceptPatientInvitation'])->name('auth.patient-invitations.accept');

    // Identity actions do not need tenant context and must remain usable by platform admins.
    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'show'])->name('auth.me');
        Route::delete('auth/logout', [AuthController::class, 'destroy'])->name('auth.logout');
    });

    // Platform administration deliberately skips hospital context and is isolated
    // behind Sanctum authentication plus the global-administrator role check.
    Route::middleware(['auth:sanctum', 'active', 'global.admin'])
        ->prefix('platform')->name('platform.')->group(function () {
            Route::get('dashboard', [GlobalAdminApiController::class, 'dashboard'])->name('dashboard');
            Route::get('modules', [GlobalAdminApiController::class, 'modules'])->name('modules.index');
            Route::get('hospitals', [GlobalAdminApiController::class, 'hospitals'])->name('hospitals.index');
            Route::post('hospitals', [GlobalAdminApiController::class, 'createHospital'])->name('hospitals.store');
            Route::get('hospitals/{hospital}', [GlobalAdminApiController::class, 'showHospital'])
                ->whereNumber('hospital')->name('hospitals.show');
            Route::put('hospitals/{hospital}/configuration', [GlobalAdminApiController::class, 'updateHospitalConfiguration'])
                ->whereNumber('hospital')->name('hospitals.configuration.update');
            Route::patch('hospitals/{hospital}/status', [GlobalAdminApiController::class, 'setHospitalStatus'])
                ->whereNumber('hospital')->name('hospitals.status.update');

            Route::get('admins', [GlobalAdminApiController::class, 'platformAdmins'])->name('admins.index');
            Route::post('admins', [GlobalAdminApiController::class, 'createPlatformAdmin'])->name('admins.store');
            Route::patch('admins/{user}/status', [GlobalAdminApiController::class, 'setPlatformAdminStatus'])
                ->whereNumber('user')->name('admins.status.update');

            Route::get('notifications', [GlobalAdminApiController::class, 'notifications'])->name('notifications.index');
            Route::patch('notifications/read-all', [GlobalAdminApiController::class, 'markAllNotificationsRead'])->name('notifications.read-all');
            Route::patch('notifications/{notification}/read', [GlobalAdminApiController::class, 'markNotificationRead'])->name('notifications.read');
        });

    Route::middleware(['auth:sanctum', 'active', 'hospital'])->group(function () {
        // CSV import/export for authorized hospital catalogs and records.
        Route::get('data/{dataset}/export', [CsvTransferController::class, 'export'])->name('data.export');
        Route::post('data/{dataset}/import', [CsvTransferController::class, 'import'])->name('data.import');

        Route::get('dashboard', DashboardApiController::class)->middleware('staff')->name('dashboard');
        Route::get('profile', [AccountController::class, 'show'])->name('profile.show');
        Route::patch('profile', [AccountController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [AccountController::class, 'updatePassword'])->name('profile.password');
        Route::delete('profile', [AccountController::class, 'destroy'])->name('profile.destroy');

        // Patient-owned appointment booking and cancellation; staff use the /staff/appointments endpoints below.
        Route::get('appointments', [AppointmentController::class, 'index'])->middleware(['module:patient_portal', 'module:appointments'])->name('appointments.index');
        Route::get('appointments/lab-options', [AppointmentController::class, 'labOptions'])->middleware(['module:patient_portal', 'module:appointments'])->name('appointments.lab-options');
        Route::post('appointments', [AppointmentController::class, 'store'])->middleware(['module:patient_portal', 'module:appointments'])->name('appointments.store');
        Route::delete('appointments/{appointment}', [AppointmentController::class, 'cancel'])
            ->middleware(['module:patient_portal', 'module:appointments'])
            ->whereNumber('appointment')
            ->name('appointments.cancel');

        // Patient portal reads are scoped to the authenticated patient's own record and files.
        Route::get('patient/profile', [PatientController::class, 'profile'])->middleware('module:patient_portal')->name('patient.profile');
        Route::get('patient/visits', [PatientController::class, 'visits'])->middleware('module:patient_portal')->name('patient.visits.index');
        Route::get('patient/visits/{visit}', [PatientController::class, 'showVisit'])
            ->middleware('module:patient_portal')
            ->whereNumber('visit')
            ->name('patient.visits.show');
        Route::get('patient/attachments/{attachment}', [PatientController::class, 'attachment'])
            ->middleware('module:patient_portal')
            ->whereNumber('attachment')
            ->name('patient.attachments.show');

        // Shared lookup data used by registration, appointment booking, and clinical workflows.
        Route::get('departments', [ReferenceDataController::class, 'departments'])->name('departments.index');
        Route::get('departments/{department}/consultation-types', [ReferenceDataController::class, 'consultationTypes'])
            ->whereNumber('department')
            ->name('departments.consultation-types.index');
        Route::get('doctors', [ReferenceDataController::class, 'doctors'])->name('doctors.index');
        Route::get('doctors/{doctor}/availability', [ReferenceDataController::class, 'availability'])
            ->middleware('module:appointments')
            ->whereNumber('doctor')
            ->name('doctors.availability.index');

        // Doctor-owned availability calendar management.
        Route::middleware(['role:doctor', 'module:appointments'])->prefix('doctor')->name('doctor.')->group(function () {
            Route::get('availability', [ReferenceDataController::class, 'myAvailability'])->name('availability.index');
            Route::post('availability', [ReferenceDataController::class, 'addAvailability'])->name('availability.store');
            Route::delete('availability/{doctorAvailability}', [ReferenceDataController::class, 'removeAvailability'])->whereNumber('doctorAvailability')->name('availability.destroy');
        });

        // Staff patient directory: lookup/detail access for admin, reception, nursing, and doctors.
        Route::middleware('role:admin,receptionist,nurse,doctor')->prefix('staff')->name('staff.')->group(function () {
            Route::get('patients', [PatientController::class, 'index'])->name('patients.index');
            Route::get('patients/{patient}', [PatientController::class, 'staffShow'])->whereNumber('patient')->name('patients.show');
        });

        // Reception and admin mutations: registration, walk-ins, patient cards, and appointment check-in.
        Route::middleware('role:admin,receptionist')->prefix('staff')->name('staff.')->group(function () {
            Route::post('patients', [PatientController::class, 'store'])->name('patients.store');
            Route::put('patients/{patient}', [PatientController::class, 'update'])->whereNumber('patient')->name('patients.update');
            Route::post('patients/{patient}/walk-ins', [PatientController::class, 'walkIn'])->whereNumber('patient')->name('patients.walk-ins.store');
            Route::post('patients/{patient}/portal-invitation', [PatientController::class, 'invite'])->middleware('module:patient_portal')->whereNumber('patient')->name('patients.invite');
            Route::get('patients/{patient}/card', [PatientController::class, 'card'])->middleware('module:patient_id_card')->whereNumber('patient')->name('patients.card');
            Route::post('appointments', [AppointmentController::class, 'staffStore'])->middleware('module:appointments')->name('appointments.store');
            Route::patch('appointments/{appointment}/doctor', [AppointmentController::class, 'reassignDoctor'])->middleware('module:appointments')->whereNumber('appointment')->name('appointments.reassign-doctor');
            Route::patch('appointments/{appointment}/cancel', [AppointmentController::class, 'staffCancel'])->middleware('module:appointments')->whereNumber('appointment')->name('appointments.cancel');
            Route::post('appointments/{appointment}/check-in', [AppointmentController::class, 'checkIn'])->middleware('module:appointments')->whereNumber('appointment')->name('appointments.check-in');
        });
        Route::middleware(['role:admin,receptionist,doctor', 'module:appointments'])->prefix('staff')->name('staff.')->group(function () {
            Route::get('appointments', [AppointmentController::class, 'staffIndex'])->name('appointments.index');
        });
        Route::middleware(['role:doctor', 'module:appointments'])->get('staff/appointments/{appointment}', [AppointmentController::class, 'staffShow'])
            ->whereNumber('appointment')->name('staff.appointments.show');

        // Admin configuration: departments, consultation prices, staff accounts/invitations, and hospital settings.
        Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
            Route::get('departments', [AdminController::class, 'departments'])->name('departments.index');
            Route::post('departments', [AdminController::class, 'createDepartment'])->name('departments.store');
            Route::put('departments/{department}', [AdminController::class, 'updateDepartment'])->whereNumber('department')->name('departments.update');
            Route::delete('departments/{department}', [AdminController::class, 'deleteDepartment'])->whereNumber('department')->name('departments.destroy');
            Route::get('consultation-types', [AdminController::class, 'consultationTypes'])->name('consultation-types.index');
            Route::post('consultation-types', [AdminController::class, 'createConsultationType'])->name('consultation-types.store');
            Route::put('consultation-types/{consultation_type}', [AdminController::class, 'updateConsultationType'])->whereNumber('consultation_type')->name('consultation-types.update');
            Route::get('staff', [AdminController::class, 'staff'])->name('staff.index');
            Route::post('staff', [AdminController::class, 'createStaff'])->name('staff.store');
            Route::put('staff/{staff}', [AdminController::class, 'updateStaff'])->whereNumber('staff')->name('staff.update');
            Route::patch('staff/{staff}/toggle', [AdminController::class, 'toggleStaff'])->whereNumber('staff')->name('staff.toggle');
            Route::post('staff-invitations', [AdminController::class, 'inviteStaff'])->name('staff-invitations.store');
            Route::post('staff-invitations/{invitation}/resend', [AdminController::class, 'resendStaffInvitation'])->whereNumber('invitation')->name('staff-invitations.resend');
            Route::delete('staff-invitations/{invitation}', [AdminController::class, 'revokeStaffInvitation'])->whereNumber('invitation')->name('staff-invitations.destroy');
            Route::get('hospital-settings', [AdminController::class, 'hospitalSettings'])->name('hospital-settings.show');
            Route::put('hospital-settings', [AdminController::class, 'updateHospitalSettings'])->name('hospital-settings.update');
        });

        // Triage queue and vitals capture; service rules lock vitals after consultation begins.
        Route::middleware(['role:admin,nurse'])->prefix('triage')->name('triage.')->group(function () {
            Route::get('queue', [ClinicalController::class, 'triageIndex'])->name('queue');
            Route::get('{visit}', [ClinicalController::class, 'triageShow'])->whereNumber('visit')->name('show');
            Route::put('{visit}/vitals', [ClinicalController::class, 'triageStore'])->whereNumber('visit')->name('vitals.update');
        });

        // A&E arrivals, emergency queue, brought-in-dead decisions, and progress notes.
        Route::middleware(['role:admin,receptionist,nurse,doctor', 'module:emergency'])
            ->prefix('emergency')->name('emergency.')->group(function () {
                Route::get('/', [EmergencyController::class, 'index'])->name('index');
                Route::post('patients/{patient}', [EmergencyController::class, 'store'])
                    ->middleware('role:admin,receptionist,nurse')->whereNumber('patient')->name('store');
                Route::post('unidentified', [EmergencyController::class, 'storeUnidentified'])
                    ->middleware('role:admin,receptionist,nurse')->name('unidentified.store');
                Route::post('visits/{visit}/brought-in-dead/confirm', [EmergencyController::class, 'confirmBroughtInDead'])
                    ->middleware('role:doctor')->whereNumber('visit')->name('brought-in-dead.confirm');
                Route::post('visits/{visit}/brought-in-dead/death-certificate', [EmergencyController::class, 'issueBroughtInDeadCertificate'])
                    ->middleware('role:doctor')->whereNumber('visit')->name('brought-in-dead.certificate.issue');
                Route::get('visits/{visit}/brought-in-dead/death-certificate', [EmergencyController::class, 'downloadBroughtInDeadCertificate'])
                    ->middleware('role:admin,doctor')->whereNumber('visit')->name('brought-in-dead.certificate.download');
                Route::post('visits/{visit}/notes', [EmergencyController::class, 'addProgressNote'])
                    ->middleware('role:doctor,nurse')->whereNumber('visit')->name('notes.store');
                Route::post('mortuary/{mortuaryCase}/receive', [EmergencyController::class, 'receiveMortuaryCase'])
                    ->middleware(['role:admin,nurse', 'module:mortuary'])->whereNumber('mortuaryCase')->name('mortuary.receive');
            });

        // Mortuary case intake, custody, storage movement, and release.
        Route::middleware(['role:admin,mortuary_attendant', 'module:mortuary'])
            ->prefix('mortuary')->name('mortuary.')->group(function () {
                Route::get('/', [MortuaryController::class, 'index'])->name('index');
                Route::post('walk-in', [MortuaryController::class, 'storeWalkIn'])->name('walk-in.store');
                Route::get('{mortuaryCase}', [MortuaryController::class, 'show'])->whereNumber('mortuaryCase')->name('show');
                Route::post('{mortuaryCase}/receive', [MortuaryController::class, 'receive'])->whereNumber('mortuaryCase')->name('receive');
                Route::post('{mortuaryCase}/move', [MortuaryController::class, 'move'])->whereNumber('mortuaryCase')->name('move');
                Route::post('{mortuaryCase}/release', [MortuaryController::class, 'release'])->whereNumber('mortuaryCase')->name('release');
            });

        // Staff roster viewing and schedule assignment share the web workflow's module and role boundary.
        Route::middleware([
            'role:admin,receptionist,nurse,chief_nurse,doctor,lab,pharmacist,chief_pharmacist,accountant,chief_accountant,mortuary_attendant',
            'module:staff_roster',
        ])->prefix('nursing/roster')->name('nursing.roster.')->group(function () {
            Route::get('/', [StaffRosterController::class, 'index'])->name('index');
            Route::put('{staff}', [StaffRosterController::class, 'update'])->whereNumber('staff')->name('update');
        });

        Route::middleware(['role:admin,nurse', 'module:medication_administration'])->prefix('nursing/medication-administrations')->name('nursing.administrations.')->group(function () {
            Route::get('/', [NursingAdministrationController::class, 'index'])->name('index');
            Route::get('visits/{visit}', [NursingAdministrationController::class, 'show'])->whereNumber('visit')->name('show');
            Route::post('visits/{visit}/prescriptions/{prescription}', [NursingAdministrationController::class, 'store'])
                ->whereNumber('visit')->whereNumber('prescription')->name('store');
            Route::post('visits/{visit}/discharge', [NursingAdministrationController::class, 'discharge'])->whereNumber('visit')->name('discharge');
        });
        Route::middleware(['role:admin,doctor', 'module:medication_administration'])->post('nursing/medication-administrations/visits/{visit}/prescriptions/{prescription}/waiver', [NursingAdministrationController::class, 'waive'])
            ->whereNumber('visit')->whereNumber('prescription')->name('nursing.administrations.waive');

        // Doctor consultation workspace, including prescriptions and linked lab requests.
        Route::middleware('role:doctor')->prefix('consultations')->name('consultations.')->scopeBindings()->group(function () {
            Route::get('queue', [ClinicalController::class, 'consultationIndex'])->name('queue');
            Route::get('options', [ClinicalController::class, 'options'])->name('options');
            Route::get('history', [ClinicalController::class, 'consultationHistoryIndex'])->name('history.index');
            Route::get('history/{visit}', [ClinicalController::class, 'consultationHistory'])->whereNumber('visit')->name('history');
            Route::get('{visit}', [ClinicalController::class, 'consultationShow'])->whereNumber('visit')->name('show');
            Route::post('{visit}/start', [ClinicalController::class, 'consultationStart'])->whereNumber('visit')->name('start');
            Route::put('{visit}', [ClinicalController::class, 'consultationUpdate'])->whereNumber('visit')->name('update');
            Route::put('{visit}/complete', [ClinicalController::class, 'consultationComplete'])->whereNumber('visit')->name('complete');
            Route::post('{visit}/lab-requests', [ClinicalController::class, 'requestLabTests'])->middleware('module:laboratory')->whereNumber('visit')->name('lab-requests.store');
            Route::delete('{visit}/lab-requests/{labRequest}', [ClinicalController::class, 'removeLabRequest'])->middleware('module:laboratory')->whereNumber('visit')->whereNumber('labRequest')->name('lab-requests.destroy');
            Route::post('{visit}/prescriptions', [ClinicalController::class, 'prescribe'])->whereNumber('visit')->name('prescriptions.store');
            Route::delete('{visit}/prescriptions/{prescription}', [ClinicalController::class, 'removePrescription'])->whereNumber('visit')->whereNumber('prescription')->name('prescriptions.destroy');
        });

        Route::middleware(['role:doctor', 'module:inpatient'])->post('consultations/{visit}/admit', [AdmissionController::class, 'admit'])
            ->whereNumber('visit')->name('consultations.admit');

        Route::middleware('module:inpatient')->prefix('admissions')->name('admissions.')->group(function () {
            Route::get('/', [AdmissionController::class, 'index'])
                ->middleware('role:admin,doctor,nurse')->name('index');
            Route::get('{admission}', [AdmissionController::class, 'show'])
                ->middleware('role:admin,doctor,nurse')->whereNumber('admission')->name('show');
            Route::post('{admission}/discharge', [AdmissionController::class, 'discharge'])
                ->middleware('role:admin,doctor')->whereNumber('admission')->name('discharge');
            Route::post('{admission}/declare-deceased', [AdmissionController::class, 'declareDeceased'])
                ->middleware('role:admin,doctor')->whereNumber('admission')->name('declare-deceased');
            Route::post('{admission}/death-certificate', [AdmissionController::class, 'issueDeathCertificate'])
                ->middleware('role:doctor')->whereNumber('admission')->name('death-certificate.issue');
            Route::get('{admission}/death-certificate', [AdmissionController::class, 'downloadDeathCertificate'])
                ->middleware('role:admin,doctor')->whereNumber('admission')->name('death-certificate.download');
            Route::post('{admission}/lab-results/{labRequest}/review', [AdmissionController::class, 'reviewLabResult'])
                ->middleware('role:admin,doctor')->whereNumber('admission')->whereNumber('labRequest')->name('lab-results.review');
            Route::post('{admission}/notes', [AdmissionController::class, 'addProgressNote'])
                ->middleware('role:doctor,nurse')->whereNumber('admission')->name('notes.store');
            Route::post('{admission}/vitals', [AdmissionController::class, 'recordVitals'])
                ->middleware('role:admin,nurse')->whereNumber('admission')->name('vitals.store');
            Route::post('{admission}/prescriptions', [AdmissionController::class, 'prescribe'])
                ->middleware('role:doctor')->whereNumber('admission')->name('prescriptions.store');
            Route::post('{admission}/sugar-checks', [AdmissionController::class, 'sugarCheck'])
                ->middleware(['role:admin,nurse,doctor', 'workflow.section:sugar_checks'])->whereNumber('admission')->name('sugar-checks.store');
            Route::post('{admission}/fluid-entries', [AdmissionController::class, 'fluidEntry'])
                ->middleware(['role:admin,nurse,doctor', 'workflow.section:fluid_balance'])->whereNumber('admission')->name('fluid-entries.store');
            Route::post('{admission}/oxygen', [AdmissionController::class, 'oxygen'])
                ->middleware(['role:admin,nurse,doctor', 'workflow.section:oxygen'])->whereNumber('admission')->name('oxygen.store');
            Route::post('{admission}/nebulizer-uses', [AdmissionController::class, 'nebulizer'])
                ->middleware(['role:admin,nurse,doctor', 'workflow.section:nebulizer'])->whereNumber('admission')->name('nebulizer-uses.store');
            Route::post('{admission}/insulin-machine-blocks', [AdmissionController::class, 'startInsulinMachineBlock'])
                ->middleware(['role:admin,nurse,doctor', 'workflow.section:insulin_machine'])->whereNumber('admission')->name('insulin-machine-blocks.store');
            Route::post('{admission}/insulin-machine-blocks/uses', [AdmissionController::class, 'recordInsulinMachineUse'])
                ->middleware(['role:admin,nurse,doctor', 'workflow.section:insulin_machine'])->whereNumber('admission')->name('insulin-machine-blocks.uses.store');
            Route::post('{admission}/dama', [AdmissionController::class, 'dama'])
                ->middleware('role:admin,doctor')->whereNumber('admission')->name('dama.store');
            Route::get('{admission}/dama', [AdmissionController::class, 'damaRecord'])
                ->middleware('role:admin,doctor,nurse')->whereNumber('admission')->name('dama.show');
            Route::get('{admission}/dama/witnesses/{witness}', [AdmissionController::class, 'damaWitness'])
                ->middleware('role:admin,doctor,nurse')->whereNumber('admission')->whereNumber('witness')->name('dama.witnesses.show');
        });

        // Keep API access aligned with web access: permitted clinical roles, inpatient, and Maternity enabled.
        Route::middleware(['role:admin,doctor,nurse', 'module:inpatient', 'module:maternity'])
            ->prefix('maternity')->name('maternity.')->group(function () {
                Route::get('fees', [MaternityController::class, 'fees'])->name('fees');
                Route::get('episodes', [MaternityController::class, 'index'])->name('episodes.index');
                Route::post('admissions/{admission}/episodes', [MaternityController::class, 'start'])
                    ->whereNumber('admission')->name('episodes.start');
                Route::get('episodes/{maternityEpisode}', [MaternityController::class, 'show'])
                    ->whereNumber('maternityEpisode')->name('episodes.show');
                Route::post('episodes/{maternityEpisode}/delivery', [MaternityController::class, 'deliver'])
                    ->whereNumber('maternityEpisode')->name('episodes.delivery');
                Route::put('episodes/{maternityEpisode}/postpartum', [MaternityController::class, 'postpartum'])
                    ->whereNumber('maternityEpisode')->name('episodes.postpartum');
            });

        // Lab reads and result reporting; doctors may read only results they are allowed to see.
        Route::middleware(['role:admin,lab,doctor', 'module:laboratory'])->prefix('lab')->name('lab.')->group(function () {
            Route::get('requests', [LabOperationsController::class, 'index'])->name('requests.index');
            Route::get('requests/{labRequest}', [LabOperationsController::class, 'show'])->whereNumber('labRequest')->name('requests.show');
            Route::get('visits/{visit}/report', [LabOperationsController::class, 'report'])->whereNumber('visit')->name('visits.report');
            Route::get('attachments/{attachment}', [LabOperationsController::class, 'showAttachment'])->whereNumber('attachment')->name('attachments.show');
            Route::get('tests', [LabOperationsController::class, 'tests'])->name('tests.index');
        });
        // Lab processing and catalogue mutations are restricted to lab staff and admins.
        Route::middleware(['role:admin,lab', 'module:laboratory'])->prefix('lab')->name('lab.')->group(function () {
            Route::put('requests/{labRequest}', [LabOperationsController::class, 'update'])->whereNumber('labRequest')->name('requests.update');
            Route::post('requests/{labRequest}/result', [LabOperationsController::class, 'update'])->whereNumber('labRequest')->name('requests.result');
            Route::delete('attachments/{attachment}', [LabOperationsController::class, 'deleteAttachment'])->whereNumber('attachment')->name('attachments.destroy');
            Route::post('tests', [LabOperationsController::class, 'createTest'])->name('tests.store');
            Route::put('tests/{labTest}', [LabOperationsController::class, 'updateTest'])->whereNumber('labTest')->name('tests.update');
            Route::patch('tests/{labTest}/toggle', [LabOperationsController::class, 'toggleTest'])->whereNumber('labTest')->name('tests.toggle');
        });
        // Standalone external/walk-in lab orders: existing or new patient, without a consultation.
        Route::middleware(['role:admin,receptionist,lab', 'module:laboratory', 'module:external_lab'])->prefix('lab/referrals')->name('lab.referrals.')->group(function () {
            Route::get('form-options', [LabOperationsController::class, 'createReferralForm'])->name('options');
            Route::post('/', [LabOperationsController::class, 'createReferral'])->name('store');
            Route::delete('{visit}', [LabOperationsController::class, 'cancelReferral'])->whereNumber('visit')->name('destroy');
        });

        // Pharmacy dispensing, inventory, restocking, and drug catalogue operations.
        Route::middleware(['role:admin,pharmacist,chief_pharmacist', 'main.pharmacy', 'module:pharmacy'])->prefix('pharmacy')->name('pharmacy.')->group(function () {
            Route::get('dashboard', [PharmacyOperationsController::class, 'dashboard'])->name('dashboard');
            Route::get('inventory', [PharmacyOperationsController::class, 'inventory'])->name('inventory');
            Route::post('inventory/{drug}/restock', [PharmacyOperationsController::class, 'restock'])->whereNumber('drug')->name('restock');
            Route::get('visits/{visit}', [PharmacyOperationsController::class, 'show'])->whereNumber('visit')->name('visits.show');
            Route::post('visits/{visit}/dispense', [PharmacyOperationsController::class, 'dispense'])->whereNumber('visit')->name('visits.dispense');
            Route::post('drugs', [PharmacyOperationsController::class, 'createDrug'])->name('drugs.store');
            Route::put('drugs/{drug}', [PharmacyOperationsController::class, 'updateDrug'])->whereNumber('drug')->name('drugs.update');
            Route::patch('drugs/{drug}/toggle', [PharmacyOperationsController::class, 'toggleDrug'])->whereNumber('drug')->name('drugs.toggle');
        });

        // Reception and admin invoice browsing, payment collection, and receipts.
        Route::middleware('role:admin,accountant,receptionist')->prefix('billing')->name('billing.')->group(function () {
            Route::get('invoices', [BillingOperationsController::class, 'index'])->name('invoices.index');
            Route::get('invoices/{invoice}', [BillingOperationsController::class, 'show'])->whereNumber('invoice')->name('invoices.show');
            Route::post('invoices/{invoice}/payments', [BillingOperationsController::class, 'pay'])->whereNumber('invoice')->name('invoices.pay');
            Route::get('payments/{payment}/receipt', [BillingOperationsController::class, 'receipt'])->whereNumber('payment')->name('payments.receipt');
        });

        // Patient portal dashboard and own invoices, online payment confirmation, and receipts.
        Route::middleware(['role:patient', 'module:patient_portal'])->prefix('patient')->name('patient.')->group(function () {
            Route::get('dashboard', [PatientController::class, 'dashboard'])->name('dashboard');
            Route::get('invoices', [BillingOperationsController::class, 'patientInvoices'])->name('invoices.index');
            Route::get('invoices/{invoice}', [BillingOperationsController::class, 'patientInvoice'])->whereNumber('invoice')->name('invoices.show');
            Route::post('invoices/{invoice}/pay', [BillingOperationsController::class, 'startOnlinePayment'])->middleware('module:online_payments')->whereNumber('invoice')->name('invoices.pay');
            Route::get('payments/confirm', [BillingOperationsController::class, 'confirmOnlinePayment'])->middleware('module:online_payments')->name('payments.confirm');
            Route::get('payments/{payment}/receipt', [BillingOperationsController::class, 'patientReceipt'])->middleware('module:online_payments')->whereNumber('payment')->name('payments.receipt');
        });
    });
});
