<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\ConsultationType;
use App\Models\Department;
use App\Models\HospitalSetting;
use App\Models\Patient;
use App\Models\User;
use Picqer\Barcode\BarcodeGeneratorSVG;
use Illuminate\Http\UploadedFile;

/**
 * Provides patient list, detail, card, and profile-update data for staff workflows.
 *
 * Record visibility is determined by the application's patient query scopes and
 * the caller's authorization; this service does not add a separate role check.
 */
class PatientService
{
    /**
     * Search and paginate patients, excluding external mortuary identities.
     *
     * @param  string|null  $term  Optional search term passed to the patient search scope.
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator Paginated patients with query parameters retained.
     */
    public function index(?string $term)
    {
        return Patient::where('identity_status', '!=', 'external_mortuary')->search($term)->latest()->paginate(10)->withQueryString();
    }

    /**
     * Load the related data needed to render a patient's staff-facing detail page.
     *
     * Includes recent visits and appointments, current visit/admission, active
     * doctors, departments, consultation types, and portal invitation/account state.
     *
     * @param  Patient  $patient  Patient whose detail-page data is assembled.
     * @return array<string, mixed> View data keyed by patient, visit, admission, and selection lists.
     */
    public function showData(Patient $patient): array
    {
        $patient->load([
            'visits' => fn ($query) => $query->latest('checked_in_at')->with(['doctor', 'consultationType.department', 'admission.department', 'mortuaryCase']),
            'appointments' => fn ($query) => $query->latest('scheduled_at')->with('doctor'),
        ]);

        return [
            'patient' => $patient,
            'activeVisit' => $patient->currentVisit(),
            'activeAdmission' => $patient->activeAdmission()->with(['department', 'attendingDoctor'])->first(),
            'doctors' => User::ofRole(Role::Doctor)->where('is_active', true)->with('department')->orderBy('name')->get(),
            'departments' => Department::orderBy('name')->get(),
            'consultationTypes' => ConsultationType::active()->orderBy('name')->get(),
            'portalUser' => User::where('patient_id', $patient->id)->first(),
            'pendingInvite' => $patient->invitations()->pending()->latest()->first(),
        ];
    }

    /**
     * Prepare patient-card data, including its profile barcode and hospital details.
     *
     * Access is refused until the patient's registration fee is recorded as paid.
     *
     * @param  Patient  $patient  Patient to print on the card.
     * @return array<string, mixed> Card template data including the patient, hospital, area, barcode, and URLs.
     */
    public function cardData(Patient $patient): array
    {
        abort_unless($patient->registration_paid_at, 403, 'The patient card is available after the registration fee has been paid.');

        $addressParts = collect(preg_split('/[,;|]/', (string) $patient->address))
            ->map(fn ($part) => trim($part))
            ->filter()
            ->values();
        $area = $addressParts->count() >= 2
            ? $addressParts->get($addressParts->count() - 2)
            : null;

        $profileUrl = route('patients.show', $patient);
        $barcode = new BarcodeGeneratorSVG;

        return [
            'patient' => $patient,
            'hospital' => HospitalSetting::current(),
            'area' => $area,
            'barcodeSvg' => $barcode->getBarcode($profileUrl, $barcode::TYPE_CODE_128, 2, 36),
            'administratorSignatureUrl' => route('settings.administrator-signature'),
            'profileUrl' => $profileUrl,
        ];
    }

    /**
     * Update patient details and, when supplied, replace the profile photo.
     *
     * Completing an unidentified patient's first and last name changes its identity
     * status to identified unless the first name remains "unknown". The estimated
     * date-of-birth flag is cleared for every update.
     *
     * @param  Patient  $patient  Patient record to update.
     * @param  array<string, mixed>  $attributes  Validated fields and optional photo upload.
     * @return Patient Refreshed patient model after persistence.
     */
    public function update(Patient $patient, array $attributes): Patient
    {
        $photo = $attributes['photo'] ?? null;
        unset($attributes['photo']);
        $identityWasUpdated = $patient->identity_status === 'unidentified'
            && filled($attributes['first_name'] ?? null)
            && filled($attributes['last_name'] ?? null)
            && mb_strtolower($attributes['first_name']) !== 'unknown';
        $patient->update($attributes + ['dob_estimated' => false] + ($identityWasUpdated ? ['identity_status' => 'identified'] : []));

        app(PatientPhotoService::class)->replace($patient, $photo instanceof UploadedFile ? $photo : null);

        return $patient->refresh();
    }
}
