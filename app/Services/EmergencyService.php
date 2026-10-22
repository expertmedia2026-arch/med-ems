<?php

namespace App\Services;

use App\Enums\VisitStatus;
use App\Models\Department;
use App\Models\MortuaryCase;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;

class EmergencyService
{
    public function __construct(private readonly BillingService $billing) {}

    public function queue()
    {
        return Visit::query()
            ->with(['patient', 'doctor', 'vitals', 'admission'])
            ->withCount(['prescriptions as nursing_orders_count' => fn ($query) => $query->where('requires_in_facility_administration', true)])
            ->where('is_emergency', true)
            ->whereIn('status', [
                VisitStatus::WaitingTriage->value,
                VisitStatus::WaitingDoctor->value,
                VisitStatus::InConsultation->value,
                VisitStatus::Inpatient->value,
            ])
            ->orderByRaw("CASE triage_priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'moderate' THEN 2 WHEN 'low' THEN 3 ELSE 4 END")
            ->oldest('checked_in_at')
            ->paginate(20)
            ->withQueryString();
    }

    public function mortuaryHandoffs()
    {
        return MortuaryCase::query()
            ->with(['patient', 'visit'])
            ->where('status', 'awaiting_transfer')
            ->oldest('deceased_at')
            ->get();
    }

    public function checkIn(Patient $patient, array $data, User $staff): ?Visit
    {
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

    public function checkInUnidentified(array $data, User $staff): Visit
    {
        return DB::transaction(function () use ($data, $staff) {
            $estimatedAge = $data['estimated_age'] ?? null;
            $patient = Patient::create([
                'first_name' => 'Unknown',
                'last_name' => 'ER Arrival',
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

    private function createVisit(Patient $patient, array $data, User $staff): Visit
    {
        $department = Department::firstOrCreate(
            ['name' => 'Accident & Emergency'],
            ['description' => 'Emergency assessment and urgent care.']
        );

        return $patient->visits()->create([
            'type' => 'consultation',
            'is_emergency' => true,
            'arrival_mode' => $data['arrival_mode'],
            'registered_by' => $staff->id,
            'status' => VisitStatus::WaitingTriage,
            'checked_in_at' => now(),
            'department_id' => $department->id,
            'complaint' => $data['complaint'],
        ]);
    }
}
