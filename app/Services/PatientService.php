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

class PatientService
{
    public function index(?string $term)
    {
        return Patient::search($term)->latest()->paginate(10)->withQueryString();
    }

    public function showData(Patient $patient): array
    {
        $patient->load([
            'visits' => fn ($query) => $query->latest('checked_in_at')->with(['doctor', 'consultationType.department', 'admission.department']),
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

    public function update(Patient $patient, array $attributes): Patient
    {
        $photo = $attributes['photo'] ?? null;
        unset($attributes['photo']);
        $patient->update($attributes + ['dob_estimated' => false]);

        app(PatientPhotoService::class)->replace($patient, $photo instanceof UploadedFile ? $photo : null);

        return $patient->refresh();
    }
}
