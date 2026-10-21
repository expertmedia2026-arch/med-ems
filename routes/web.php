<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AdmissionController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ConsultationController;
use App\Http\Controllers\ConsultationTypeController;
use App\Http\Controllers\CsvTransferController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmergencyController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DoctorAvailabilityController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\DrugController;
use App\Http\Controllers\InvitationAcceptController;
use App\Http\Controllers\LabAttachmentController;
use App\Http\Controllers\LabController;
use App\Http\Controllers\LabReferralController;
use App\Http\Controllers\LabRequestController;
use App\Http\Controllers\LabTestController;
use App\Http\Controllers\NursingAdministrationController;
use App\Http\Controllers\NurseRosterController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PatientMediaController;
use App\Http\Controllers\PatientPortalController;
use App\Http\Controllers\PaystackWebhookController;
use App\Http\Controllers\PharmacyController;
use App\Http\Controllers\PortalAppointmentController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PortalInvitationAcceptController;
use App\Http\Controllers\PortalInvoiceController;
use App\Http\Controllers\PortalVisitController;
use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StaffInvitationController;
use App\Http\Controllers\TriageController;
use App\Http\Controllers\VisitController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('patients/{patient}/photo', [PatientMediaController::class, 'photo'])
    ->middleware(['auth', 'auth.session', 'active'])
    ->name('patients.photo');

// Payment gateway notifications (no login, no CSRF; protected by the gateway's signature)
Route::post('webhooks/paystack', PaystackWebhookController::class)->name('webhooks.paystack');

// Invitation links (opened by people who are not logged in)
Route::middleware('guest')->group(function () {
    Route::prefix('invitations')->name('invitations.')->group(function () {
        Route::get('{token}', [InvitationAcceptController::class, 'show'])->name('accept');
        Route::post('{token}', [InvitationAcceptController::class, 'store'])->name('accept.store');
    });

    Route::prefix('portal/invitations')->name('portal.invitations.')->group(function () {
        Route::get('{token}', [PortalInvitationAcceptController::class, 'show'])->name('accept');
        Route::post('{token}', [PortalInvitationAcceptController::class, 'store'])->name('accept.store');
    });
});

// ---------------- Patient portal ----------------
Route::middleware(['auth', 'auth.session', 'active', 'role:patient'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('/', [PortalController::class, 'home'])->name('home');
    Route::view('settings', 'portal.settings')->name('settings');

    // Visit history
    Route::get('visits', [PortalVisitController::class, 'index'])->name('visits.index');
    Route::get('visits/{visit}', [PortalVisitController::class, 'show'])->whereNumber('visit')->name('visits.show');
    Route::get('visits/{visit}/lab-report', [PortalVisitController::class, 'labReport'])->whereNumber('visit')->name('visits.lab-report');
    Route::get('attachments/{attachment}', [PortalVisitController::class, 'attachment'])->whereNumber('attachment')->name('attachments.show');

    // Appointments
    Route::get('appointments', [PortalAppointmentController::class, 'index'])->name('appointments.index');
    Route::get('appointments/create', [PortalAppointmentController::class, 'create'])->name('appointments.create');
    Route::post('appointments', [PortalAppointmentController::class, 'store'])->name('appointments.store');
    Route::patch('appointments/{appointment}/cancel', [PortalAppointmentController::class, 'cancel'])->whereNumber('appointment')->name('appointments.cancel');

    // Invoices and online payment
    Route::get('invoices', [PortalInvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/{invoice}', [PortalInvoiceController::class, 'show'])->whereNumber('invoice')->name('invoices.show');
    Route::post('invoices/{invoice}/pay', [PortalInvoiceController::class, 'pay'])->whereNumber('invoice')->name('invoices.pay');
    Route::get('payments/callback', [PortalInvoiceController::class, 'callback'])->name('payments.callback');
    Route::get('payments/{payment}/receipt', [PortalInvoiceController::class, 'receipt'])->whereNumber('payment')->name('payments.receipt');
});

// ---------------- Staff ----------------
Route::middleware(['auth', 'auth.session', 'active', 'staff'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('role:admin,receptionist,nurse,doctor')->group(function () {
        Route::get('emergency', [EmergencyController::class, 'index'])->name('emergency.index');
        Route::post('emergency/patients/{patient}', [EmergencyController::class, 'store'])
            ->middleware('role:admin,receptionist,nurse')->name('emergency.store');
        Route::post('emergency/unidentified', [EmergencyController::class, 'storeUnidentified'])
            ->middleware('role:admin,receptionist,nurse')->name('emergency.unidentified.store');
        Route::post('emergency/visits/{visit}/notes', [EmergencyController::class, 'addProgressNote'])
            ->middleware('role:doctor,nurse')->whereNumber('visit')->name('emergency.notes.store');
        Route::post('emergency/mortuary/{mortuaryCase}/receive', [EmergencyController::class, 'receiveMortuaryCase'])
            ->middleware('role:admin,nurse')->whereNumber('mortuaryCase')->name('emergency.mortuary.receive');
    });

    Route::get('data/{dataset}/export', [CsvTransferController::class, 'export'])->name('data.export');
    Route::post('data/{dataset}/import', [CsvTransferController::class, 'import'])->name('data.import');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::middleware('role:doctor')->prefix('doctor')->name('doctor.')->group(function () {
        Route::get('availability', [DoctorAvailabilityController::class, 'index'])->name('availability.index');
        Route::post('availability', [DoctorAvailabilityController::class, 'store'])->name('availability.store');
        Route::delete('availability/{doctorAvailability}', [DoctorAvailabilityController::class, 'destroy'])->name('availability.destroy');
    });

    // Admin and chief accountant may view staff; writes remain admin-only.
    Route::middleware('role:admin,chief_accountant')->get('staff', [StaffController::class, 'index'])->name('staff.index');

    // Admin: staff management and hospital settings
    Route::middleware('role:admin')->group(function () {
        Route::resource('departments', DepartmentController::class)->except('show');
        Route::resource('consultation-types', ConsultationTypeController::class)->except(['show', 'destroy']);

        Route::prefix('staff')->name('staff.')->group(function () {
            Route::get('create', [StaffController::class, 'create'])->name('create');
            Route::post('/', [StaffController::class, 'store'])->name('store');

            // Invitations (before {staff} routes)
            Route::get('invite', [StaffInvitationController::class, 'create'])->name('invite');
            Route::post('invite', [StaffInvitationController::class, 'store'])->name('invite.store');
            Route::post('invitations/{invitation}/resend', [StaffInvitationController::class, 'resend'])->name('invitations.resend');
            Route::delete('invitations/{invitation}', [StaffInvitationController::class, 'destroy'])->name('invitations.destroy');

            Route::get('{staff}/edit', [StaffController::class, 'edit'])->name('edit');
            Route::put('{staff}', [StaffController::class, 'update'])->name('update');
            Route::patch('{staff}/toggle', [StaffController::class, 'toggle'])->name('toggle');
        });

        Route::get('settings/hospital', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings/hospital', [SettingsController::class, 'update'])->name('settings.update');
        Route::get('settings/hospital/{section}', [SettingsController::class, 'edit'])
            ->whereIn('section', ['basic', 'payments', 'branding'])->name('settings.section');
        Route::put('settings/hospital/{section}', [SettingsController::class, 'update'])
            ->whereIn('section', ['basic', 'payments', 'branding'])->name('settings.update.section');
    });

    // Front desk: register, edit, book, check in (defined first so /patients/create isn't read as {patient})
    Route::middleware('role:admin,receptionist')->group(function () {
        Route::get('patients/create', [PatientController::class, 'create'])->name('patients.create');
        Route::post('patients', [PatientController::class, 'store'])->name('patients.store');
        Route::get('patients/{patient}/edit', [PatientController::class, 'edit'])->name('patients.edit');
        Route::put('patients/{patient}', [PatientController::class, 'update'])->name('patients.update');
        Route::get('patients/{patient}/card', [PatientController::class, 'card'])->name('patients.card');
        Route::post('patients/{patient}/check-in', [VisitController::class, 'walkIn'])->name('patients.check-in');
        Route::post('patients/{patient}/portal-invite', [PatientPortalController::class, 'invite'])->name('patients.portal-invite');

        Route::get('appointments/create', [AppointmentController::class, 'create'])->name('appointments.create');
        Route::post('appointments', [AppointmentController::class, 'store'])->name('appointments.store');
        Route::patch('appointments/{appointment}/doctor', [AppointmentController::class, 'reassignDoctor'])->whereNumber('appointment')->name('appointments.reassign-doctor');
        Route::patch('appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])->name('appointments.cancel');
        Route::post('appointments/{appointment}/check-in', [VisitController::class, 'fromAppointment'])->name('appointments.check-in');
    });

    // Read access
    Route::middleware('role:admin,receptionist,nurse,doctor')->group(function () {
        Route::get('patients', [PatientController::class, 'index'])->name('patients.index');
        Route::get('patients/{patient}', [PatientController::class, 'show'])->name('patients.show');
        Route::get('settings/administrator-signature', [PatientMediaController::class, 'administratorSignature'])->name('settings.administrator-signature');
    });

    Route::get('appointments', [AppointmentController::class, 'index'])
        ->middleware('role:admin,receptionist,doctor')
        ->name('appointments.index');

    Route::get('appointments/{appointment}', [AppointmentController::class, 'show'])
        ->middleware('role:admin,doctor')
        ->name('appointments.show');

    // Read-only completed consultation details are available to administrators and the assigned doctor.
    Route::get('consultations/history/{visit}', [ConsultationController::class, 'history'])
        ->middleware('role:admin,doctor')
        ->name('consultations.history');

    Route::middleware('role:admin,doctor,nurse')->prefix('admissions')->name('admissions.')->group(function () {
        Route::get('/', [AdmissionController::class, 'index'])->name('index');
        Route::get('{admission}', [AdmissionController::class, 'show'])->whereNumber('admission')->name('show');
    });
    Route::post('admissions/{admission}/lab-results/{labRequest}/review', [AdmissionController::class, 'reviewLabResult'])
        ->middleware('role:admin,doctor')->whereNumber('admission')->whereNumber('labRequest')->name('admissions.lab-results.review');
    Route::post('admissions/{admission}/discharge', [AdmissionController::class, 'discharge'])
        ->middleware('role:admin,doctor')->whereNumber('admission')->name('admissions.discharge');
    Route::post('admissions/{admission}/notes', [AdmissionController::class, 'addProgressNote'])
        ->middleware('role:doctor,nurse')->whereNumber('admission')->name('admissions.notes.store');
    Route::post('admissions/{admission}/vitals', [AdmissionController::class, 'recordVitals'])
        ->middleware('role:admin,nurse')->whereNumber('admission')->name('admissions.vitals.store');
    Route::post('admissions/{admission}/prescriptions', [AdmissionController::class, 'prescribe'])
        ->middleware('role:doctor')->whereNumber('admission')->name('admissions.prescriptions.store');

    // Nurse: triage
    Route::get('nursing/roster', [NurseRosterController::class, 'index'])
        ->middleware('role:admin,chief_nurse,nurse')->name('nursing.roster.index');
    Route::put('nursing/roster/{nurse}', [NurseRosterController::class, 'update'])
        ->middleware('role:admin,chief_nurse')->whereNumber('nurse')->name('nursing.roster.update');

    Route::middleware('role:admin,nurse')->prefix('triage')->name('triage.')->group(function () {
        Route::get('/', [TriageController::class, 'index'])->name('index');
        Route::get('{visit}', [TriageController::class, 'show'])->name('show');
        Route::post('{visit}', [TriageController::class, 'store'])->name('store');
    });

    Route::middleware('role:admin,nurse')->prefix('nursing/medication-administrations')->name('nursing.administrations.')->group(function () {
        Route::get('/', [NursingAdministrationController::class, 'index'])->name('index');
        Route::get('visits/{visit}', [NursingAdministrationController::class, 'show'])->whereNumber('visit')->name('show');
        Route::post('visits/{visit}/prescriptions/{prescription}', [NursingAdministrationController::class, 'store'])
            ->whereNumber('visit')->whereNumber('prescription')->name('store');
        Route::post('visits/{visit}/discharge', [NursingAdministrationController::class, 'discharge'])->whereNumber('visit')->name('discharge');
    });
    Route::middleware('role:admin,doctor')->post('nursing/medication-administrations/visits/{visit}/prescriptions/{prescription}/waiver', [NursingAdministrationController::class, 'waive'])
        ->whereNumber('visit')->whereNumber('prescription')->name('nursing.administrations.waive');

    // Doctor: consultation workspace
    Route::middleware('role:doctor')->prefix('consultations')->name('consultations.')->scopeBindings()->group(function () {
        Route::get('/', [ConsultationController::class, 'index'])->name('index');
        Route::get('history', [ConsultationController::class, 'historyIndex'])->name('history.index');
        Route::get('{visit}', [ConsultationController::class, 'show'])->name('show');
        Route::post('{visit}/start', [ConsultationController::class, 'start'])->name('start');
        Route::put('{visit}', [ConsultationController::class, 'update'])->name('update');
        Route::put('{visit}/complete', [ConsultationController::class, 'complete'])->name('complete');
        Route::post('{visit}/admit', [ConsultationController::class, 'admit'])->name('admit');

        Route::post('{visit}/prescriptions', [PrescriptionController::class, 'store'])->name('prescriptions.store');
        Route::delete('{visit}/prescriptions/{prescription}', [PrescriptionController::class, 'destroy'])->name('prescriptions.destroy');

        Route::post('{visit}/lab-requests', [LabRequestController::class, 'store'])->name('lab-requests.store');
        Route::delete('{visit}/lab-requests/{labRequest}', [LabRequestController::class, 'destroy'])->name('lab-requests.destroy');
    });

    // ---------------- Laboratory ----------------
    // Order matters: the specific lab/... routes must come BEFORE lab/{labRequest}

    // Test catalog (admin and lab only)
    Route::middleware('role:admin,lab')->prefix('lab/tests')->name('lab.tests.')->group(function () {
        Route::get('/', [LabTestController::class, 'index'])->name('index');
        Route::get('create', [LabTestController::class, 'create'])->name('create');
        Route::post('/', [LabTestController::class, 'store'])->name('store');
        Route::get('{labTest}/edit', [LabTestController::class, 'edit'])->name('edit');
        Route::put('{labTest}', [LabTestController::class, 'update'])->name('update');
        Route::patch('{labTest}/toggle', [LabTestController::class, 'toggle'])->name('toggle');
    });

    // External lab requests (patients referred from other hospitals, or walk-in lab clients)
    Route::middleware('role:admin,receptionist,lab')->prefix('lab/referrals')->name('lab.referrals.')->group(function () {
        Route::get('create', [LabReferralController::class, 'create'])->name('create');
        Route::post('/', [LabReferralController::class, 'store'])->name('store');
        Route::delete('{visit}', [LabReferralController::class, 'destroy'])->name('destroy');
    });

    // Result attachments (private files, served through the controller)
    Route::get('lab/attachments/{attachment}', [LabAttachmentController::class, 'show'])
        ->middleware('role:admin,lab,doctor,nurse')
        ->whereNumber('attachment')
        ->name('lab.attachments.show');

    Route::delete('lab/attachments/{attachment}', [LabAttachmentController::class, 'destroy'])
        ->middleware('role:admin,lab')
        ->whereNumber('attachment')
        ->name('lab.attachments.destroy');

    // Lab requests and printable report: doctors get read-only access to results they requested
    Route::middleware('role:admin,lab,doctor')->prefix('lab')->name('lab.')->group(function () {
        Route::get('/', [LabController::class, 'index'])->name('index');
        Route::get('visits/{visit}/report', [LabController::class, 'report'])->name('report');
        Route::get('{labRequest}', [LabController::class, 'show'])->whereNumber('labRequest')->name('show');
    });

    Route::put('lab/{labRequest}', [LabController::class, 'update'])
        ->middleware('role:admin,lab')
        ->whereNumber('labRequest')
        ->name('lab.update');

    // Pharmacy
    Route::middleware('role:admin,pharmacist,chief_pharmacist')->prefix('pharmacy')->name('pharmacy.')->group(function () {
        Route::get('/', [PharmacyController::class, 'index'])->name('index');
        Route::get('audit', [PharmacyController::class, 'audit'])->middleware('role:admin,chief_pharmacist')->name('audit');
        Route::get('inventory', [PharmacyController::class, 'inventory'])->name('inventory');
        Route::post('inventory/{drug}/restock', [PharmacyController::class, 'restock'])->name('restock');
        Route::get('visits/{visit}', [PharmacyController::class, 'show'])->name('show');
        Route::post('visits/{visit}/dispense', [PharmacyController::class, 'dispense'])->name('dispense');

        Route::get('drugs/create', [DrugController::class, 'create'])->name('drugs.create');
        Route::post('drugs', [DrugController::class, 'store'])->name('drugs.store');
        Route::get('drugs/{drug}/edit', [DrugController::class, 'edit'])->name('drugs.edit');
        Route::put('drugs/{drug}', [DrugController::class, 'update'])->name('drugs.update');
        Route::patch('drugs/{drug}/toggle', [DrugController::class, 'toggle'])->name('drugs.toggle');
    });

    // Finance reporting for administrators and accounting leadership.
    Route::middleware('role:admin,chief_accountant')->prefix('finance')->name('finance.')->group(function () {
        Route::get('/', [FinanceController::class, 'index'])->name('index');
        Route::get('export/pdf', [FinanceController::class, 'exportPdf'])->name('export.pdf');
    });

    // Payment collection and invoice handling.
    Route::middleware('role:admin,accountant,receptionist')->prefix('billing')->name('billing.')->group(function () {
        Route::get('/', [BillingController::class, 'index'])->name('index');
        Route::get('invoices/{invoice}', [BillingController::class, 'show'])->name('show');
        Route::post('invoices/{invoice}/payments', [BillingController::class, 'pay'])->name('pay');
        Route::get('payments/{payment}/receipt', [BillingController::class, 'receipt'])->name('receipt');
    });
});

require __DIR__.'/auth.php';
