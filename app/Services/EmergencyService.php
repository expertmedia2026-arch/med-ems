<?php

namespace App\Services;

use App\Enums\VisitStatus;
use App\Models\Department;
use App\Models\MortuaryCase;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;

/**
 * Coordinates emergency visit intake, queue data, and emergency-linked mortuary handoffs.
 */
class EmergencyService
{
    /** Inject billing integration used to add the configured A&E attendance charge. */
    public function __construct(private readonly BillingService $billing) {}

    /** Return the active emergency queue with triage, care, and nursing-order details. */
    public function queue()
    {
        return Visit::query()
            ->with([
                'patient', 'doctor', 'vitals', 'admission',
                'emergencyObservations.recorder', 'emergencyProgressNotes.author',
            ])
            ->withCount(['prescriptions as nursing_orders_count' => fn ($query) => $query->where('requires_in_facility_administration', true)])
            ->where('is_emergency', true)
            ->whereIn('status', [
                VisitStatus::WaitingTriage->value,
                VisitStatus::WaitingDoctor->value,
                VisitStatus::InConsultation->value,
                VisitStatus::Inpatient->value,
            ])
            ->orderByRaw("CASE WHEN is_brought_in_dead THEN 0 ELSE 1 END, CASE triage_priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'moderate' THEN 2 WHEN 'low' THEN 3 ELSE 4 END")
            ->oldest('checked_in_at')
            ->paginate(20)
            ->withQueryString();
    }

    /** List emergency deaths awaiting an approved transfer to the mortuary. */
    public function mortuaryHandoffs()
    {
        return MortuaryCase::query()
            ->with(['patient', 'visit.department', 'mortuaryTransferApprovedBy', 'declaredBy'])
            ->where('status', 'awaiting_transfer')
            ->where('source', 'hospital')
            ->whereNotNull('mortuary_transfer_approved_by')
            ->whereHas('visit', fn ($query) => $query->where('is_emergency', true))
            ->oldest('deceased_at')
            ->get();
    }

    /** Return recent brought-in-dead emergency cases for the confirmation workflow. */
    public function broughtInDeadCases()
    {
        return MortuaryCase::query()
            ->with(['patient', 'visit.department', 'deathCertificateIssuedBy'])
            ->where('source', 'hospital')
            ->whereNotNull('declared_at')
            ->whereHas('visit', fn ($query) => $query->where('is_emergency', true)->where('is_brought_in_dead', true))
            ->latest('declared_at')
            ->limit(20)
            ->get();
    }

    /**
     * Register an identified patient for emergency care unless they already have an active visit/admission.
     *
     * Visit creation and the optional configured attendance invoice are transactional. Returns null when
     * the patient already has an active admission or current visit.
     *
     * @param array<string, mixed> $data Validated emergency complaint, arrival mode, and optional BID flag.
     */
    public function checkIn(Patient $patient, array $data, User $staff): ?Visit
    {
        abort_unless($patient->identity_status !== 'external_mortuary', 422, 'Walk-in mortuary records cannot be checked in for emergency care.');
        abort_unless(! ($data['brought_in_dead'] ?? false) || $patient->identity_status !== 'unidentified', 422, 'Complete the patient identity before opening a BID case.');

        return DB::transaction(function () use ($patient, $data, $staff) {
            $patient = Patient::query()->lockForUpdate()->findOrFail($patient->id);
            if ($patient->activeAdmission()->exists() || $patient->currentVisit()) {
                return null;
            }

            $visit = $this->createVisit($patient, $data, $staff);
            $this->billing->addEmergencyAttendanceFee($visit);

            return $visit;
        });
    }

    /**
     * Create an unidentified emergency patient and open their emergency visit atomically.
     *
     * The request cannot mark an unidentified arrival as brought-in-dead; any configured attendance
     * fee is added within the same transaction.
     *
     * @param array<string, mixed> $data Validated emergency intake details and optional estimated age/next of kin.
     */
    public function checkInUnidentified(array $data, User $staff): Visit
    {
        unset($data['brought_in_dead']);

        return DB::transaction(function () use ($data, $staff) {
            $estimatedAge = $data['estimated_age'] ?? null;
            $patient = Patient::create([
                'first_name' => 'Unknown',
                'last_name' => 'A&E Arrival',
                'gender' => $data['gender'],
                'date_of_birth' => $estimatedAge === null ? now()->toDateString() : now()->subYears($estimatedAge)->toDateString(),
                'dob_estimated' => true,
                'identity_status' => 'unidentified',
                'next_of_kin_name' => $data['next_of_kin_name'] ?? null,
                'next_of_kin_phone' => $data['next_of_kin_phone'] ?? null,
            ]);

            $visit = $this->createVisit($patient, $data, $staff);
            $this->billing->addEmergencyAttendanceFee($visit);

            return $visit;
        });
    }

    /**
     * Create the emergency visit and ensure its A&E department exists.
     *
     * @param array<string, mixed> $data Validated complaint, arrival mode, and optional BID flag.
     */
    private function createVisit(Patient $patient, array $data, User $staff): Visit
    {
        $broughtInDead = (bool) ($data['brought_in_dead'] ?? false);
        $department = Department::firstOrCreate(
            ['name' => 'Accident & Emergency'],
            ['description' => 'Emergency assessment and urgent care.']
        );

        return $patient->visits()->create([
            'type' => 'consultation',
            'is_emergency' => true,
            'arrival_mode' => $data['arrival_mode'],
            'is_brought_in_dead' => $broughtInDead,
            'registered_by' => $staff->id,
            'status' => $broughtInDead ? VisitStatus::WaitingDoctor : VisitStatus::WaitingTriage,
            'checked_in_at' => now(),
            'department_id' => $department->id,
            'complaint' => $data['complaint'],
        ]);
    }
}
