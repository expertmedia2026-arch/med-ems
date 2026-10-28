<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Enums\WorkStatus;
use App\Models\AdmissionInsulinMachineUse;
use App\Models\MedicationAdministration;
use App\Models\Prescription;
use App\Models\Visit;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MedicationAdministrationService
{
    public function queue(User $staff): Collection
    {
        $departmentId = $this->departmentScope($staff);

        return Visit::query()
            ->where(fn ($query) => $query
                ->whereIn('status', [VisitStatus::Completed->value, VisitStatus::Inpatient->value, VisitStatus::InConsultation->value]))
            ->whereNull('discharged_at')
            ->when($departmentId, fn ($query) => $query->where(function ($scope) use ($departmentId, $staff) {
                $scope->where(fn ($outpatient) => $outpatient->where('status', VisitStatus::Completed->value)->where('department_id', $departmentId))
                    ->orWhere(fn ($inpatient) => $inpatient->where('status', VisitStatus::Inpatient->value)
                        ->whereHas('admission', fn ($admission) => $admission->where('status', 'active')->where('department_id', $departmentId)))
                    ->orWhere(fn ($consultation) => $consultation->where('status', VisitStatus::InConsultation->value)
                        ->where('is_emergency', false)->where('department_id', $departmentId))
                    ->when($staff->canAccessEmergency(), fn ($query) => $query->orWhere(fn ($emergency) => $emergency
                        ->where('is_emergency', true)->where('status', VisitStatus::InConsultation->value)));
            }))
            ->whereHas('prescriptions', fn ($query) => $query->where('requires_in_facility_administration', true))
            ->with(['patient', 'doctor', 'department', 'admission.department', 'admission.insulinMachineUses.block.invoiceItem', 'prescriptions' => fn ($query) => $query
                ->where('requires_in_facility_administration', true)
                ->with(['drug', 'dispenser', 'invoiceItem', 'stockReservation', 'administrations.nurse', 'administrationWaiver'])])
            ->orderByRaw('COALESCE(closed_at, checked_in_at) asc')->get();
    }

    public function visitDetails(Visit $visit, User $staff): Visit
    {
        $this->assertDepartmentAccess($visit, $staff);
        abort_unless($this->isOpenForNursing($visit), 404);
        abort_unless($visit->prescriptions()->where('requires_in_facility_administration', true)->exists(), 404);

        return $visit->load([
            'patient', 'doctor', 'department', 'admission.department', 'admission.insulinMachineUses.block.invoiceItem', 'dischargeActor', 'vitals', 'emergencyObservations.recorder', 'emergencyProgressNotes.author', 'labRequests.test',
            'prescriptions' => fn ($query) => $query->where('requires_in_facility_administration', true)
                ->with(['drug', 'dispenser', 'invoiceItem', 'stockReservation', 'administrations.nurse', 'administrationWaiver']),
        ]);
    }

    public function record(Visit $visit, Prescription $prescription, array $data, User $nurse): MedicationAdministration
    {
        $record = DB::transaction(function () use ($visit, $prescription, $data, $nurse) {
            $visit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            $this->assertDepartmentAccess($visit, $nurse);
            $prescription = Prescription::query()->lockForUpdate()->findOrFail($prescription->id);
            abort_unless($prescription->visit_id === $visit->id, 404);
            abort_unless($this->isOpenForNursing($visit), 422, 'This visit is not open for nursing care.');
            abort_unless($prescription->requires_in_facility_administration, 422, 'This prescription is not marked for administration at the hospital.');
            abort_unless($prescription->is_paid, 422, 'The medication must be paid for before administration.');
            abort_unless($prescription->status === WorkStatus::Completed && $prescription->stockReservation?->status === 'fulfilled', 422, 'Pharmacy must dispense this medication before it can be administered.');
            abort_unless(! $prescription->administration_waived_at, 422, 'This administration requirement was waived by the doctor.');

            $prescription->loadMissing('drug');

            if ($visit->status === VisitStatus::Inpatient && str_contains(strtolower($prescription->drug?->display_name ?? ''), 'insulin')) {
                $admissionId = $visit->admission()->where('status', 'active')->value('id');
                $hasPaidBottle = $admissionId && AdmissionInsulinMachineUse::query()
                    ->where('admission_id', $admissionId)
                    ->where('prescription_id', $prescription->id)
                    ->whereHas('block', fn ($block) => $block->where(function ($paid) {
                        $paid->whereHas('invoiceItem', fn ($item) => $item->whereNotNull('payment_id')->orWhere('total', '<=', 0))
                            ->orWhere('fee', '<=', 0);
                    }))
                    ->exists();

                abort_unless($hasPaidBottle, 422, 'Record a paid insulin machine bottle opening on the admission record before charting this insulin dose.');
            }

            $administered = $prescription->administrations()->where('status', 'administered')->count();
            if ($administered >= $prescription->required_administration_count) {
                throw ValidationException::withMessages(['status' => 'All required administrations for this prescription are already recorded.']);
            }

            return MedicationAdministration::create([
                'visit_id' => $visit->id,
                'prescription_id' => $prescription->id,
                'administered_by' => $nurse->id,
                'status' => $data['status'],
                'dose' => $data['dose'] ?? $prescription->dosage,
                'route' => $data['route'] ?? null,
                'site' => $data['site'] ?? null,
                'notes' => $data['notes'] ?? null,
                'reason' => $data['reason'] ?? null,
                'administered_at' => $data['status'] === 'administered' ? now() : null,
            ]);
        });

        DB::afterCommit(fn () => app(WorkflowNotificationService::class)->medicationOutcome($record));

        return $record;
    }

    public function waive(Visit $visit, Prescription $prescription, string $reason, User $actor): Prescription
    {
        abort_unless($actor->hasRole(Role::Admin, Role::Doctor), 403);
        abort_unless($actor->hasRole(Role::Admin) || $visit->doctor_id === $actor->id, 403, 'Only the attending doctor or an administrator can waive this administration.');

        $prescription = DB::transaction(function () use ($visit, $prescription, $reason, $actor) {
            $visit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            $this->assertDepartmentAccess($visit, $actor);
            $prescription = Prescription::query()->lockForUpdate()->findOrFail($prescription->id);
            abort_unless($prescription->visit_id === $visit->id, 404);
            abort_unless($this->isOpenForNursing($visit), 422, 'This visit is already closed.');
            abort_unless($prescription->requires_in_facility_administration, 422, 'This prescription has no nursing administration requirement.');
            abort_unless($prescription->status === WorkStatus::Completed && $prescription->stockReservation()->where('status', 'fulfilled')->exists(), 422, 'Only a medicine already dispensed by pharmacy can be waived here. Resolve unfilled prescriptions with pharmacy first.');
            abort_if($prescription->administrations()->where('status', 'administered')->count() >= $prescription->required_administration_count, 422, 'All required doses are already recorded as administered.');

            $prescription->update([
                'administration_waived_by' => $actor->id,
                'administration_waived_at' => now(),
                'administration_waiver_reason' => $reason,
            ]);

            return $prescription->refresh();
        });

        DB::afterCommit(fn () => app(WorkflowNotificationService::class)->medicationWaived($visit, $prescription));

        return $prescription;
    }

    public function discharge(Visit $visit, User $actor): Visit
    {
        return DB::transaction(function () use ($visit, $actor) {
            $visit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            $this->assertDepartmentAccess($visit, $actor);
            abort_unless($visit->status === VisitStatus::Completed, 422, 'Inpatient discharge must be completed from the linked admission record.');
            abort_if($visit->discharged_at, 422, 'This visit has already been discharged.');

            $this->assertReadyForDischarge($visit);

            $visit->update(['discharged_by' => $actor->id, 'discharged_at' => now()]);

            return $visit->refresh();
        });
    }

    private function departmentScope(User $staff): ?int
    {
        if ($staff->hasRole(Role::Admin)) {
            return null;
        }

        abort_if(! $staff->department_id, 403, 'Your staff account must be assigned to a department to use nursing care.');

        return (int) $staff->department_id;
    }

    private function assertDepartmentAccess(Visit $visit, User $staff): void
    {
        if ($visit->is_emergency && $staff->canAccessEmergency()) {
            return;
        }

        if ($staff->hasRole(Role::Doctor) && $visit->doctor_id === $staff->id) {
            return;
        }

        $departmentId = $this->departmentScope($staff);
        $careDepartmentId = $visit->status === VisitStatus::Inpatient
            ? ($visit->admission()->where('status', 'active')->value('department_id') ?? $visit->department_id)
            : $visit->department_id;
        abort_unless($departmentId === null || $departmentId === (int) $careDepartmentId, 403,
            'This visit belongs to another department. Only nurses assigned to the visit department can provide care.');
    }

    private function isOpenForNursing(Visit $visit): bool
    {
        return ! $visit->discharged_at && (
            in_array($visit->status, [VisitStatus::Completed, VisitStatus::Inpatient, VisitStatus::InConsultation], true)
        );
    }


    public function assertReadyForDischarge(Visit $visit): void
    {
        $pending = $visit->prescriptions()
            ->where('requires_in_facility_administration', true)
            ->whereNull('administration_waived_at')
            ->with('stockReservation')
            ->withCount(['administrations as completed_administrations_count' => fn ($query) => $query->where('status', 'administered')])
            ->get()
            ->filter(fn (Prescription $prescription) => $prescription->status !== WorkStatus::Completed
                || $prescription->stockReservation?->status !== 'fulfilled'
                || $prescription->completed_administrations_count < $prescription->required_administration_count);

        if ($pending->isNotEmpty()) {
            throw ValidationException::withMessages([
                'discharge' => 'This patient still has medicines awaiting pharmacy dispensing or nursing administration. Resolve these orders before completing discharge.',
            ]);
        }
    }
}
