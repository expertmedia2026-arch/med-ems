<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\ConsultationType;
use App\Models\Department;
use App\Models\HospitalSetting;
use App\Models\Patient;
use App\Models\User;

class PatientService
{
    public function index(?string $term)
    {
        return Patient::search($term)->latest()->paginate(10)->withQueryString();
    }

    public function showData(Patient $patient): array
    {
        $patient->load([
            'visits' => fn ($query) => $query->latest('checked_in_at')->with(['doctor', 'consultationType.department']),
            'appointments' => fn ($query) => $query->latest('scheduled_at')->with('doctor'),
        ]);

        return [
            'patient' => $patient,
            'activeVisit' => $patient->currentVisit(),
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

        return ['patient' => $patient, 'hospital' => HospitalSetting::current()];
    }

    public function update(Patient $patient, array $attributes): Patient
    {
        $patient->update($attributes + ['dob_estimated' => false]);

        return $patient->refresh();
    }
}
