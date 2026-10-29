<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Enums\WorkStatus;
use App\Models\Drug;
use App\Models\Department;
use App\Models\LabTest;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns doctor-facing consultation queues, clinical updates, and completion workflow.
 */
class ConsultationService
{
    /** Inject billing, inventory, mortality, medication, and emergency-stock workflows. */
    public function __construct(
        private readonly BillingService $billing,
        private readonly DrugInventoryService $inventory,
        private readonly MortuaryCaseService $mortuary,
        private readonly MedicationAdministrationService $medicationAdministration,
        private readonly EmergencyPharmacyService $emergencyPharmacy,
    ) {}

    /** Return unclaimed or doctor-assigned visits waiting for care in this doctor's department. */
    public function queueFor(User $doctor)
    {
        abort_unless($doctor->department_id, 403, 'Your doctor account must be assigned to a department.');

        return Visit::with(['patient', 'vitals', 'department'])
            ->withStatus(VisitStatus::WaitingDoctor, VisitStatus::InConsultation)
            ->where('is_brought_in_dead', false)
            ->where('department_id', $doctor->department_id)
            ->where(fn ($query) => $query->whereNull('doctor_id')->orWhere('doctor_id', $doctor->id))
            ->orderByDesc('is_emergency')
            ->orderByRaw("CASE triage_priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'moderate' THEN 2 WHEN 'low' THEN 3 ELSE 4 END")
            ->oldest('checked_in_at')->get();
    }

    /** Paginate completed consultations assigned to the doctor, optionally filtered by patient details. */
    public function historyFor(User $doctor, ?string $search = null)
    {
        abort_unless($doctor->department_id, 403, 'Your doctor account must be assigned to a department.');

        return Visit::query()
            ->with(['patient', 'department'])
            ->withStatus(VisitStatus::Completed)
            ->where('doctor_id', $doctor->id)
            ->when($search, fn ($query) => $query->whereHas('patient', fn ($patient) => $patient
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('hospital_number', 'like', "%{$search}%")))
            ->latest('closed_at')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * Load the consultation screen's clinical relations and selectable drugs, tests, and wards.
     *
     * Expires stale inventory reservations before loading and rejects visits outside the doctor's
     * department/assignment or visits that belong to triage/BID workflows.
     *
     * @return array<string, mixed> Visit, editability, recent history, catalogue options, and ward options.
     */
    public function showData(Visit $visit, User $doctor): array
    {
        $this->inventory->expireReservations();
        $this->assertAssignedToDoctor($visit, $doctor);
        abort_if($visit->status === VisitStatus::WaitingTriage, 404);
        abort_if($visit->is_brought_in_dead, 404, 'BID arrivals use the A&E confirmation workflow.');

        $visit->load([
            'patient', 'vitals', 'emergencyObservations.recorder', 'emergencyProgressNotes.author', 'emergencyMedicationUses.drug', 'emergencyMedicationUses.recorder', 'emergencyMedicationUses.invoiceItem', 'emergencyItemUses.item', 'emergencyItemUses.recorder', 'emergencyItemUses.invoiceItem', 'prescriptions.drug', 'prescriptions.invoiceItem', 'prescriptions.stockReservation', 'prescriptions.administrations.nurse', 'prescriptions.administrationWaiver',
            'labRequests.test', 'labRequests.attachments', 'labRequests.invoiceItem',
        ]);

        return [
            'visit' => $visit,
            'editable' => $visit->isOpenFor($doctor),
            'history' => $visit->patient->visits()->with('doctor')->where('id', '!=', $visit->id)
                ->where('doctor_id', $doctor->id)
                ->withStatus(VisitStatus::Completed)->latest('checked_in_at')->take(5)->get(),
            'drugs' => Drug::active()->whereRaw('(stock_quantity - reserved_quantity) > 0')->orderBy('name')->get(),
            'tests' => LabTest::active()->orderBy('name')->get(),
            'wardDepartments' => Department::where('has_ward', true)->orderBy('name')->get(),
            'emergencyStockItems' => $visit->is_emergency ? $this->emergencyPharmacy->useOptions() : collect(),
        ];
    }

    /** Load a completed consultation's history for its assigned doctor or an administrator. */
    public function historyData(Visit $visit, User $user): Visit
    {
        abort_unless(
            $user->hasRole(Role::Admin) || $visit->doctor_id === $user->id,
            403,
            'You can only view consultations assigned to you.'
        );
        abort_unless($visit->status === VisitStatus::Completed, 404);

        return $visit->load([
            'patient', 'doctor', 'department', 'vitals', 'prescriptions.drug', 'prescriptions.dispenser', 'prescriptions.stockReservation', 'prescriptions.administrations.nurse', 'prescriptions.administrationWaiver',
            'labRequests.test', 'labRequests.processor', 'labRequests.attachments',
        ]);
    }

    /** Claim a waiting visit and atomically move it into consultation for its assigned doctor. */
    public function start(Visit $visit, User $doctor): void
    {
        DB::transaction(function () use ($visit, $doctor) {
            $visit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            $this->assertAssignedToDoctor($visit, $doctor);
            abort_unless($visit->status === VisitStatus::WaitingDoctor, 422, 'This patient is not waiting for a doctor.');
            abort_unless(! $visit->is_brought_in_dead, 422, 'Use the brought-in-dead confirmation workflow instead of starting a treatment consultation.');

            $visit->update([
                'doctor_id' => $doctor->id,
                'status' => VisitStatus::InConsultation,
                'consulted_at' => now(),
            ]);
        });
    }

    /** Save validated clinical notes while the visit is still open to the assigned doctor. */
    public function saveNotes(Visit $visit, array $notes, User $doctor): void
    {
        abort_unless($visit->isOpenFor($doctor), 403, 'This consultation is not open for editing.');
        $visit->update($notes);
    }

    /**
     * Close an open consultation after validating outstanding tests and discharge readiness.
     *
     * The visit, pending pharmacy orders, appointment completion, billing synchronization, and
     * emergency death handoff are coordinated inside a transaction.
     *
     * @param array<string, mixed> $notes Validated clinical completion fields, including disposition when applicable.
     */
    public function complete(Visit $visit, array $notes, User $doctor): void
    {
        abort_unless($visit->isOpenFor($doctor), 403, 'This consultation is not open for editing.');
        $deceasedAt = $notes['deceased_at'] ?? null;
        unset($notes['deceased_at']);

        DB::transaction(function () use ($visit, $notes, $doctor, $deceasedAt) {
            $visit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            abort_unless($visit->isOpenFor($doctor), 403, 'This consultation is not open for editing.');

            $outstandingLabs = $visit->labRequests()
                ->where('status', '!=', WorkStatus::Completed->value)
                ->with('test:id,name')
                ->lockForUpdate()
                ->get();

            $deceasedEmergency = $visit->is_emergency && ($notes['disposition'] ?? null) === 'deceased';
            if ($outstandingLabs->isNotEmpty() && ! $deceasedEmergency) {
                $testNames = $outstandingLabs->map(fn ($request) => $request->test?->name ?? 'Requested test')->join(', ');

                throw ValidationException::withMessages([
                    'lab_requests' => "This consultation cannot be completed until results are entered or uploaded for: {$testNames}.",
                ]);
            }

            if ($visit->is_emergency && ($notes['disposition'] ?? null) === 'discharged') {
                $this->medicationAdministration->assertReadyForDischarge($visit);
            }

            $visit->prescriptions()
                ->where('status', WorkStatus::Pending->value)
                ->whereNull('pharmacy_ordered_at')
                ->update(['pharmacy_ordered_at' => now()]);

            $visit->update($notes + ['status' => VisitStatus::Completed, 'closed_at' => now()]);
            $visit->appointment?->update(['status' => AppointmentStatus::Completed]);
            if ($deceasedEmergency) {
                $this->mortuary->openFromEmergency($visit, $doctor, $deceasedAt);
            }
            $this->billing->syncFor($visit);
        });
    }

    /** Enforce department membership and prevent access to a visit assigned to another doctor. */
    public function assertAssignedToDoctor(Visit $visit, User $doctor): void
    {
        abort_unless($doctor->department_id && (int) $visit->department_id === (int) $doctor->department_id, 403,
            'This consultation belongs to another department.');
        abort_unless($visit->doctor_id === null || $visit->doctor_id === $doctor->id, 403, 'This patient is assigned to another doctor.');
    }
}
