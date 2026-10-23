<?php

namespace App\Services;

use App\Enums\AdmissionStatus;
use App\Enums\Role;
use App\Models\Admission;
use App\Models\MortuaryCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class HospitalDeathCertificateService
{
    public function issue(Admission $admission, array $data, User $issuer): MortuaryCase
    {
        return DB::transaction(function () use ($admission, $data, $issuer) {
            $admission = Admission::query()->lockForUpdate()->findOrFail($admission->id);
            abort_unless($admission->status === AdmissionStatus::Deceased, 422, 'A hospital death certificate can only be issued for a deceased inpatient.');
            abort_unless($issuer->hasRole(Role::Doctor)
                && ($admission->attending_doctor_id === $issuer->id
                    || ($issuer->department_id && (int) $issuer->department_id === (int) $admission->department_id)),
                403, 'Only a doctor assigned to this ward can issue the hospital death certificate.');

            $case = MortuaryCase::query()->lockForUpdate()->where('visit_id', $admission->visit_id)->firstOrFail();
            abort_unless($case->source === 'hospital' && $case->declared_at, 422, 'This record is not a declared in-hospital death.');
            abort_if($case->death_certificate_issued_at, 422, 'A hospital death certificate has already been issued for this death.');

            $issuedAt = now();
            $case->update([
                'death_certificate_number' => 'HDC-'.$issuedAt->format('Ymd').'-'.str_pad((string) $case->id, 6, '0', STR_PAD_LEFT),
                'death_certificate_issued_at' => $issuedAt,
                'death_certificate_issued_by' => $issuer->id,
                'death_certificate_issuer_name' => $issuer->name,
                'death_certificate_registration_number' => $data['registration_number'],
                'death_certificate_cause' => $data['certified_cause'],
                'death_certificate_other_conditions' => $data['other_conditions'] ?? null,
            ]);

            $case->events()->create([
                'performed_by' => $issuer->id,
                'event_type' => 'death_certificate_issued',
                'occurred_at' => $issuedAt,
                'details' => ['certificate_number' => $case->death_certificate_number],
            ]);

            return $case->refresh();
        });
    }
}
