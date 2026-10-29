<?php

namespace App\Services;

use App\Enums\AdmissionStatus;
use App\Enums\Role;
use App\Models\Admission;
use App\Models\MortuaryCase;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;

/**
 * Issues certificates for confirmed in-hospital deaths and records their audit events.
 *
 * Both workflows lock the relevant admission or visit and mortuary case inside a database
 * transaction, and rely on the model's tenant scope in addition to the explicit issuer checks.
 */
class HospitalDeathCertificateService
{
    /**
     * Issue a certificate for a deceased inpatient admission.
     *
     * Only the attending doctor or a doctor assigned to the admission's ward may issue it. A
     * successful transaction stores certificate details and an audit event; duplicate issuance
     * and records not declared as in-hospital deaths are rejected.
     *
     * @param  array{registration_number: string, certified_cause: string, other_conditions?: string|null}  $data
     */
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

    /**
     * Issue a certificate for an emergency visit confirmed as a brought-in-dead arrival.
     *
     * The issuer must be an emergency-access doctor, and the locked visit must already be
     * completed with deceased disposition. Certificate data and the corresponding audit event
     * are committed together.
     *
     * @param  array{registration_number: string, certified_cause: string, other_conditions?: string|null}  $data
     */
    public function issueForBroughtInDead(Visit $visit, array $data, User $issuer): MortuaryCase
    {
        abort_unless($issuer->hasRole(Role::Doctor) && $issuer->canAccessEmergency(), 403, 'Only an A&E doctor can issue a certificate for a BID arrival.');

        return DB::transaction(function () use ($visit, $data, $issuer) {
            $visit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            abort_unless($visit->is_emergency && $visit->is_brought_in_dead
                && $visit->status->value === 'completed' && $visit->disposition === 'deceased',
                422, 'Death must be confirmed for this BID arrival before a certificate can be issued.');

            $case = MortuaryCase::query()->lockForUpdate()->where('visit_id', $visit->id)->firstOrFail();
            abort_unless($case->source === 'hospital' && $case->declared_at, 422, 'This is not a confirmed in-hospital death.');
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
