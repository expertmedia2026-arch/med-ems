<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Patient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Creates a patient record, optional photo, and registration invoice.
 *
 * Patient creation and invoice generation run in a database transaction. A billing
 * workflow notification is sent after that transaction succeeds.
 */
class PatientRegistrationService
{
    /** @param BillingService $billing Creates any configured registration charge. */
    public function __construct(private readonly BillingService $billing) {}

    /**
     * Register a patient, store an optional photo, and create its registration invoice.
     *
     * A configured charge can produce an invoice; when no amount is due the invoice
     * is null. A billing notification describing the outcome is sent after database
     * work completes.
     *
     * @param  array<string, mixed>  $attributes  Validated patient fields and optional UploadedFile under "photo".
     * @return array{patient: Patient, invoice: ?Invoice}
     */
    public function register(array $attributes): array
    {
        $photo = $attributes['photo'] ?? null;
        unset($attributes['photo']);

        $registration = DB::transaction(function () use ($attributes, $photo) {
            $patient = Patient::create($attributes);

            app(PatientPhotoService::class)->replace($patient, $photo instanceof UploadedFile ? $photo : null);
            $invoice = $this->billing->registrationInvoiceFor($patient);

            return compact('patient', 'invoice');
        });

        $patient = $registration['patient'];
        $message = "{$patient->full_name} ({$patient->hospital_number}) was registered.";
        $message .= $registration['invoice']
            ? ' Registration charge of ₦'.number_format((float) $registration['invoice']->total, 2).' is ready for payment.'
            : ' No registration charge is outstanding.';

        app(WorkflowNotificationService::class)->billing(
            (int) $registration['patient']->hospital_id,
            'New patient registered',
            $message,
        );

        return $registration;
    }
}
