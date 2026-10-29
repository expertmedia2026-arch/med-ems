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

/**
 * Builds nurse triage queues and records vitals and emergency observations.
 *
 * Department access is checked before viewing or editing a visit. Vitals changes
 * are committed under a visit row lock, with doctor notifications dispatched only
 * after the transaction commits.
 */
class TriageService
{
    /** @param BillingService $billing Creates any missing consultation invoice shown in the triage queue. */
    public function __construct(private readonly BillingService $billing) {}

    /**
     * Ensure staff may edit vitals for this visit at its current workflow stage.
     *
     * Requires admin or appropriate nurse department access; only waiting visits
     * remain editable, with emergency visits also editable during consultation.
     *
     * @param  Visit  $visit  Visit whose vitals are to be changed.
     * @param  User  $staff  Staff member attempting the edit.
     */
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

    /**
     * Assemble role- and department-scoped waiting and triaged visit queues.
     *
     * Administrators and central-vitals nurses may select a department; other nurses
     * are limited to their assigned department. Non-emergency waiting visits without
     * invoices have one created as part of preparing the queue.
     *
     * @param  User  $staff  Staff member viewing the triage queue.
     * @param  array<string, mixed>  $filters  Optional department_id and q filters.
     * @param  bool  $paginate  Whether to paginate waiting and ready queues.
     * @return array<string, mixed> Queue results, counts, available departments, and selected filter state.
     */
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

    /**
     * Load the patient, workflow, vitals, and emergency-care data needed for triage.
     *
     * @param  Visit  $visit  Visit being opened for triage.
     * @return Visit Visit with its triage and emergency relations loaded.
     */
    public function loadVitalsData(Visit $visit): Visit
    {
        return $visit->load(['patient', 'doctor', 'department', 'vitals', 'emergencyObservations.recorder', 'emergencyProgressNotes.author', 'emergencyMedicationUses.drug', 'emergencyMedicationUses.recorder', 'emergencyMedicationUses.invoiceItem', 'emergencyItemUses.item', 'emergencyItemUses.recorder', 'emergencyItemUses.invoiceItem']);
    }

    /**
     * Record vitals and move an eligible visit from triage to the doctor queue.
     *
     * The visit is reloaded under a row lock, then department, role, and workflow
     * status are checked in the transaction. Emergency visits also receive an
     * observation record. Doctors are notified after commit.
     *
     * @param  Visit  $visit  Visit receiving the recorded vitals.
     * @param  array<string, mixed>  $data  Validated vitals plus optional complaint and triage priority.
     * @param  User  $nurse  Nurse recording the observations.
     */
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

        DB::afterCommit(function () use ($visit) {
            $visit->loadMissing('patient');
            app(WorkflowNotificationService::class)->doctorsFor(
                $visit,
                'Patient ready for consultation',
                "{$visit->patient->full_name} ({$visit->patient->hospital_number}) has completed triage and is waiting for a doctor.",
            );
        });
    }

    /**
     * Require admin or nurse access to the visit's assigned department or central OPD.
     *
     * @param  Visit  $visit  Visit whose department determines access.
     * @param  User  $staff  Staff member requesting access.
     */
    private function assertDepartmentAccess(Visit $visit, User $staff): void
    {
        abort_unless($staff->hasRole(Role::Admin) || ($staff->hasRole(Role::Nurse)
            && ($staff->isCentralVitalsNurse() || (int) $staff->department_id === (int) $visit->department_id)), 403,
            'This visit belongs to another department. Only its assigned nurse or a nurse assigned to OPD can record vitals.');
    }
}
