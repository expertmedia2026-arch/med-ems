<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\VisitStatus;
use App\Enums\WorkStatus;
use App\Models\Drug;
use App\Models\LabTest;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConsultationService
{
    public function __construct(private readonly BillingService $billing) {}

    public function queueFor(User $doctor)
    {
        return Visit::with(['patient', 'vitals'])
            ->withStatus(VisitStatus::WaitingDoctor, VisitStatus::InConsultation)
            ->where(fn ($query) => $query->whereNull('doctor_id')->orWhere('doctor_id', $doctor->id))
            ->oldest('checked_in_at')->get();
    }

    public function showData(Visit $visit, User $doctor): array
    {
        $this->assertAssignedToDoctor($visit, $doctor);
        abort_if($visit->status === VisitStatus::WaitingTriage, 404);

        $visit->load([
            'patient', 'vitals', 'prescriptions.drug', 'prescriptions.invoiceItem',
            'labRequests.test', 'labRequests.attachments', 'labRequests.invoiceItem',
        ]);

        return [
            'visit' => $visit,
            'editable' => $visit->isOpenFor($doctor),
            'history' => $visit->patient->visits()->with('doctor')->where('id', '!=', $visit->id)
                ->withStatus(VisitStatus::Completed)->latest('checked_in_at')->take(5)->get(),
            'drugs' => Drug::active()->where('stock_quantity', '>', 0)->orderBy('name')->get(),
            'tests' => LabTest::active()->orderBy('name')->get(),
        ];
    }

    public function historyData(Visit $visit): Visit
    {
        abort_unless($visit->status === VisitStatus::Completed, 404);

        return $visit->load([
            'patient', 'doctor', 'vitals', 'prescriptions.drug', 'prescriptions.dispenser',
            'labRequests.test', 'labRequests.processor', 'labRequests.attachments',
        ]);
    }

    public function start(Visit $visit, User $doctor): void
    {
        DB::transaction(function () use ($visit, $doctor) {
            $visit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            $this->assertAssignedToDoctor($visit, $doctor);
            abort_unless($visit->status === VisitStatus::WaitingDoctor, 422, 'This patient is not waiting for a doctor.');

            $visit->update([
                'doctor_id' => $doctor->id,
                'status' => VisitStatus::InConsultation,
                'consulted_at' => now(),
            ]);
        });
    }

    public function saveNotes(Visit $visit, array $notes, User $doctor): void
    {
        abort_unless($visit->isOpenFor($doctor), 403, 'This consultation is not open for editing.');
        $visit->update($notes);
    }

    public function complete(Visit $visit, array $notes, User $doctor): void
    {
        abort_unless($visit->isOpenFor($doctor), 403, 'This consultation is not open for editing.');

        DB::transaction(function () use ($visit, $notes, $doctor) {
            $visit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            abort_unless($visit->isOpenFor($doctor), 403, 'This consultation is not open for editing.');

            $outstandingLabs = $visit->labRequests()
                ->where('status', '!=', WorkStatus::Completed->value)
                ->with('test:id,name')
                ->lockForUpdate()
                ->get();

            if ($outstandingLabs->isNotEmpty()) {
                $testNames = $outstandingLabs->map(fn ($request) => $request->test?->name ?? 'Requested test')->join(', ');

                throw ValidationException::withMessages([
                    'lab_requests' => "This consultation cannot be completed until results are entered or uploaded for: {$testNames}.",
                ]);
            }

            $visit->update($notes + ['status' => VisitStatus::Completed, 'closed_at' => now()]);
            $visit->appointment?->update(['status' => AppointmentStatus::Completed]);
            $this->billing->syncFor($visit);
        });
    }

    public function assertAssignedToDoctor(Visit $visit, User $doctor): void
    {
        abort_unless($visit->doctor_id === null || $visit->doctor_id === $doctor->id, 403, 'This patient is assigned to another doctor.');
    }
}
