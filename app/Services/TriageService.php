<?php

namespace App\Services;

use App\Enums\VisitStatus;
use App\Models\User;
use App\Models\Visit;
use App\Enums\Role;
use App\Models\Department;
use App\Models\EmergencyObservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TriageService
{
    public function __construct(private readonly BillingService $billing) {}

    public function assertVitalsEditable(Visit $visit, User $staff): void
    {
        $this->assertDepartmentAccess($visit, $staff);
        abort_unless(
            in_array($visit->status, $visit->is_emergency
                ? [VisitStatus::WaitingTriage, VisitStatus::WaitingDoctor, VisitStatus::InConsultation]
                : [VisitStatus::WaitingTriage, VisitStatus::WaitingDoctor]),
            403,
            'Vitals can no longer be edited for this visit.'
        );
    }

    public function indexData(User $staff, array $filters = [], bool $paginate = true): array
    {
        $isAdmin = $staff->hasRole(Role::Admin);
        $isCentralNurse = $staff->isCentralVitalsNurse();
        abort_if(! $isAdmin && ! $isCentralNurse && ! $staff->department_id, 403,
            'Assign this nurse to a clinical department, or enable Central Vitals for OPD-wide access.');
        $selectedDepartment = ($isAdmin || $isCentralNurse) ? ($filters['department_id'] ?? null) : null;
        $departmentId = $isAdmin || $isCentralNurse ? $selectedDepartment : $staff->department_id;
        $search = trim((string) ($filters['q'] ?? ''));

        $queue = fn (VisitStatus $status): Builder => Visit::query()
            ->with(['patient', 'doctor', 'department', 'vitals.recorder', 'invoice.items'])
            ->where('status', $status->value)
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('complaint', 'like', "%{$search}%")
                    ->orWhereHas('patient', fn ($patient) => $patient->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('hospital_number', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%"));
            }));

        $waitingQuery = $queue(VisitStatus::WaitingTriage)
            ->orderByDesc('is_emergency')
            ->orderByRaw("CASE triage_priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'moderate' THEN 2 WHEN 'low' THEN 3 ELSE 4 END")
            ->oldest('checked_in_at');
        $doneQuery = $queue(VisitStatus::WaitingDoctor)->today()->latest('updated_at');
        $waiting = $paginate
            ? $waitingQuery->paginate(15, ['*'], 'waiting_page')->withQueryString()
            : $waitingQuery->get();
        $done = $paginate
            ? $doneQuery->paginate(10, ['*'], 'ready_page')->withQueryString()
            : $doneQuery->get();
        $waiting->each(function (Visit $visit) {
            if (! $visit->is_emergency && ! $visit->invoice) {
                $this->billing->invoiceFor($visit);
                $visit->load('invoice.items');
            }
        });

        return [
            'waiting' => $waiting,
            'done' => $done,
            'waitingCount' => (clone $queue(VisitStatus::WaitingTriage))->count(),
            'overdueCount' => (clone $queue(VisitStatus::WaitingTriage))->where('checked_in_at', '<=', now()->subMinutes(30))->count(),
            'readyCount' => (clone $queue(VisitStatus::WaitingDoctor))->today()->count(),
            'departments' => ($isAdmin || $isCentralNurse) ? Department::orderBy('name')->get(['id', 'name']) : collect(),
            'selectedDepartment' => $selectedDepartment,
            'search' => $search,
        ];
    }

    public function loadVitalsData(Visit $visit): Visit
    {
        return $visit->load(['patient', 'doctor', 'department', 'vitals', 'emergencyObservations.recorder', 'emergencyProgressNotes.author', 'emergencyMedicationUses.drug', 'emergencyMedicationUses.recorder', 'emergencyMedicationUses.invoiceItem', 'emergencyItemUses.item', 'emergencyItemUses.recorder', 'emergencyItemUses.invoiceItem']);
    }

    public function recordVitals(Visit $visit, array $data, User $nurse): void
    {
        $complaint = $data['complaint'] ?? null;
        $priority = $data['triage_priority'] ?? null;
        unset($data['complaint'], $data['triage_priority']);

        DB::transaction(function () use ($visit, $data, $complaint, $priority, $nurse) {
            $visit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            $this->assertVitalsEditable($visit, $nurse);

            if ($visit->is_emergency) {
                EmergencyObservation::create($data + [
                    'visit_id' => $visit->id,
                    'recorded_by' => $nurse->id,
                    'recorded_at' => now(),
                ]);
            }

            $visit->vitals()->updateOrCreate(
                ['visit_id' => $visit->id],
                $data + ['recorded_by' => $nurse->id]
            );

            $visit->update(array_filter([
                'status' => $visit->status === VisitStatus::WaitingTriage ? VisitStatus::WaitingDoctor : null,
                'complaint' => $complaint,
                'triage_priority' => $visit->is_emergency && $priority ? $priority : null,
                'triaged_at' => $visit->is_emergency && ! $visit->triaged_at ? now() : null,
            ]));
        });
    }

    private function assertDepartmentAccess(Visit $visit, User $staff): void
    {
        abort_unless($staff->hasRole(Role::Admin) || ($staff->hasRole(Role::Nurse)
            && ($staff->isCentralVitalsNurse() || (int) $staff->department_id === (int) $visit->department_id)), 403,
            'This visit belongs to another department. Only its assigned nurse or a nurse assigned to OPD can record vitals.');
    }
}
