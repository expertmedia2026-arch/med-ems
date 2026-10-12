<?php

namespace App\Services;

use App\Enums\VisitStatus;
use App\Models\User;
use App\Models\Visit;
use App\Enums\Role;
use Illuminate\Support\Facades\DB;

class TriageService
{
    public function __construct(private readonly BillingService $billing) {}

    public function assertVitalsEditable(Visit $visit, User $staff): void
    {
        $this->assertDepartmentAccess($visit, $staff);
        abort_unless(
            in_array($visit->status, [VisitStatus::WaitingTriage, VisitStatus::WaitingDoctor]),
            403,
            'Vitals can no longer be edited for this visit.'
        );
    }

    public function indexData(User $staff): array
    {
        $departmentId = $staff->hasRole(Role::Admin) ? null : $staff->department_id;
        abort_if(! $staff->hasRole(Role::Admin) && ! $departmentId, 403, 'Your staff account must be assigned to a department to use triage.');

        $waiting = Visit::with(['patient', 'doctor', 'department', 'invoice.items'])->withStatus(VisitStatus::WaitingTriage)
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->oldest('checked_in_at')->get();
        $waiting->each(function (Visit $visit) {
            if (! $visit->invoice) {
                $this->billing->invoiceFor($visit);
                $visit->load('invoice.items');
            }
        });

        return [
            'waiting' => $waiting,
            'done' => Visit::with(['patient', 'doctor', 'department', 'vitals'])->withStatus(VisitStatus::WaitingDoctor)
                ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
                ->today()->latest('updated_at')->get(),
        ];
    }

    public function loadVitalsData(Visit $visit): Visit
    {
        return $visit->load(['patient', 'doctor', 'department', 'vitals']);
    }

    public function recordVitals(Visit $visit, array $data, User $nurse): void
    {
        $complaint = $data['complaint'] ?? null;
        unset($data['complaint']);

        DB::transaction(function () use ($visit, $data, $complaint, $nurse) {
            $visit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            $this->assertVitalsEditable($visit, $nurse);

            $visit->vitals()->updateOrCreate(
                ['visit_id' => $visit->id],
                $data + ['recorded_by' => $nurse->id]
            );

            $visit->update(array_filter([
                'status' => VisitStatus::WaitingDoctor,
                'complaint' => $complaint,
            ]));
        });
    }

    private function assertDepartmentAccess(Visit $visit, User $staff): void
    {
        abort_unless($staff->hasRole(Role::Admin) || ($staff->hasRole(Role::Nurse)
            && $staff->department_id !== null
            && (int) $staff->department_id === (int) $visit->department_id), 403,
            'This visit belongs to another department. Only nurses assigned to the visit department can provide triage.');
    }
}
