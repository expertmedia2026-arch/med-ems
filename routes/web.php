<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ConsultationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DrugController;
use App\Http\Controllers\InvitationAcceptController;
use App\Http\Controllers\LabController;
use App\Http\Controllers\LabRequestController;
use App\Http\Controllers\LabTestController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PharmacyController;
use App\Http\Controllers\LabAttachmentController;
use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StaffInvitationController;
use App\Http\Controllers\TriageController;
use App\Http\Controllers\VisitController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

// Staff invitation links (opened by people who are not logged in)
Route::middleware('guest')->prefix('invitations')->name('invitations.')->group(function () {
    Route::get('{token}', [InvitationAcceptController::class, 'show'])->name('accept');
    Route::post('{token}', [InvitationAcceptController::class, 'store'])->name('accept.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Admin: staff management and hospital settings
    Route::middleware('role:admin')->group(function () {
        Route::prefix('staff')->name('staff.')->group(function () {
            Route::get('/', [StaffController::class, 'index'])->name('index');
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
    });

    // Front desk: register, edit, book, check in (defined first so /patients/create isn't read as {patient})
    Route::middleware('role:admin,receptionist')->group(function () {
        Route::get('patients/create', [PatientController::class, 'create'])->name('patients.create');
        Route::post('patients', [PatientController::class, 'store'])->name('patients.store');
        Route::get('patients/{patient}/edit', [PatientController::class, 'edit'])->name('patients.edit');
        Route::put('patients/{patient}', [PatientController::class, 'update'])->name('patients.update');
        Route::post('patients/{patient}/check-in', [VisitController::class, 'walkIn'])->name('patients.check-in');

        Route::get('appointments/create', [AppointmentController::class, 'create'])->name('appointments.create');
        Route::post('appointments', [AppointmentController::class, 'store'])->name('appointments.store');
        Route::patch('appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])->name('appointments.cancel');
        Route::post('appointments/{appointment}/check-in', [VisitController::class, 'fromAppointment'])->name('appointments.check-in');
    });

    // Read access
    Route::middleware('role:admin,receptionist,nurse,doctor')->group(function () {
        Route::get('patients', [PatientController::class, 'index'])->name('patients.index');
        Route::get('patients/{patient}', [PatientController::class, 'show'])->name('patients.show');
    });

    Route::get('appointments', [AppointmentController::class, 'index'])
        ->middleware('role:admin,receptionist,doctor')
        ->name('appointments.index');

    // Nurse: triage
    Route::middleware('role:admin,nurse')->prefix('triage')->name('triage.')->group(function () {
        Route::get('/', [TriageController::class, 'index'])->name('index');
        Route::get('{visit}', [TriageController::class, 'show'])->name('show');
        Route::post('{visit}', [TriageController::class, 'store'])->name('store');
    });

    // Doctor: consultation workspace
    Route::middleware('role:doctor')->prefix('consultations')->name('consultations.')->scopeBindings()->group(function () {
        Route::get('/', [ConsultationController::class, 'index'])->name('index');
        Route::get('history/{visit}', [ConsultationController::class, 'history'])->name('history');
        Route::get('{visit}', [ConsultationController::class, 'show'])->name('show');
        Route::post('{visit}/start', [ConsultationController::class, 'start'])->name('start');
        Route::put('{visit}', [ConsultationController::class, 'update'])->name('update');
        Route::put('{visit}/complete', [ConsultationController::class, 'complete'])->name('complete');

        Route::post('{visit}/prescriptions', [PrescriptionController::class, 'store'])->name('prescriptions.store');
        Route::delete('{visit}/prescriptions/{prescription}', [PrescriptionController::class, 'destroy'])->name('prescriptions.destroy');

        Route::post('{visit}/lab-requests', [LabRequestController::class, 'store'])->name('lab-requests.store');
        Route::delete('{visit}/lab-requests/{labRequest}', [LabRequestController::class, 'destroy'])->name('lab-requests.destroy');
    });

    // Lab test catalog (admin and lab only). Must come BEFORE lab/{labRequest}
    Route::middleware('role:admin,lab')->prefix('lab/tests')->name('lab.tests.')->group(function () {
        Route::get('/', [LabTestController::class, 'index'])->name('index');
        Route::get('create', [LabTestController::class, 'create'])->name('create');
        Route::post('/', [LabTestController::class, 'store'])->name('store');
        Route::get('{labTest}/edit', [LabTestController::class, 'edit'])->name('edit');
        Route::put('{labTest}', [LabTestController::class, 'update'])->name('update');
        Route::patch('{labTest}/toggle', [LabTestController::class, 'toggle'])->name('toggle');
    });

    // Lab requests: doctors get read-only access to results they requested
    Route::middleware('role:admin,lab,doctor')->prefix('lab')->name('lab.')->group(function () {
        Route::get('/', [LabController::class, 'index'])->name('index');
        Route::get('{labRequest}', [LabController::class, 'show'])->whereNumber('labRequest')->name('show');
    });

    // Lab result attachments (private files, served through the controller)
    Route::get('lab/attachments/{attachment}', [LabAttachmentController::class, 'show'])
        ->middleware('role:admin,lab,doctor')
        ->whereNumber('attachment')
        ->name('lab.attachments.show');

    Route::delete('lab/attachments/{attachment}', [LabAttachmentController::class, 'destroy'])
        ->middleware('role:admin,lab')
        ->whereNumber('attachment')
        ->name('lab.attachments.destroy');
        
    Route::put('lab/{labRequest}', [LabController::class, 'update'])
        ->middleware('role:admin,lab')
        ->whereNumber('labRequest')
        ->name('lab.update');

    // Pharmacy
    Route::middleware('role:admin,pharmacist')->prefix('pharmacy')->name('pharmacy.')->group(function () {
        Route::get('/', [PharmacyController::class, 'index'])->name('index');
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

   // Cashier
    Route::middleware('role:admin,cashier')->prefix('billing')->name('billing.')->group(function () {
        Route::get('/', [BillingController::class, 'index'])->name('index');
        Route::get('invoices/{invoice}', [BillingController::class, 'show'])->name('show');
        Route::post('invoices/{invoice}/payments', [BillingController::class, 'pay'])->name('pay');
        Route::get('payments/{payment}/receipt', [BillingController::class, 'receipt'])->name('receipt');
    });

    
});

require __DIR__.'/auth.php';