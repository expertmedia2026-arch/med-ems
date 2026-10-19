<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Patient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class PatientRegistrationService
{
    public function __construct(private readonly BillingService $billing) {}

    /**
     * Register a patient and create the registration invoice as one operation.
     *
     * @return array{patient: Patient, invoice: ?Invoice}
     */
    public function register(array $attributes): array
    {
        $photo = $attributes['photo'] ?? null;
        unset($attributes['photo']);

        return DB::transaction(function () use ($attributes, $photo) {
            $patient = Patient::create($attributes);

            app(PatientPhotoService::class)->replace($patient, $photo instanceof UploadedFile ? $photo : null);
            $invoice = $this->billing->registrationInvoiceFor($patient);

            return compact('patient', 'invoice');
        });
    }
}
