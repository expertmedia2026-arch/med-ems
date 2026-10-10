<?php

namespace App\Services;

use App\Enums\VisitStatus;
use App\Models\User;
use App\Models\Visit;

class TriageService
{
    public function __construct(private readonly BillingService $billing) {}

    public function assertVitalsEditable(Visit $visit): void
    {
        abort_unless(
            in_array($visit->status, [VisitStatus::WaitingTriage, VisitStatus::WaitingDoctor]),
            403,
            'Vitals can no longer be edited for this visit.'
        );
    }

    public function indexData(): array
    {
        $waiting = Visit::with(['patient', 'invoice.items'])->withStatus(VisitStatus::WaitingTriage)->oldest('checked_in_at')->get();
        $waiting->each(function (Visit $visit) {
            if (! $visit->invoice) {
                $this->billing->invoiceFor($visit);
                $visit->load('invoice.items');
            }
        });

        return [
            'waiting' => $waiting,
            'done' => Visit::with(['patient', 'vitals'])->withStatus(VisitStatus::WaitingDoctor)
                ->today()->latest('updated_at')->get(),
        ];
    }

    public function loadVitalsData(Visit $visit): Visit
    {
        return $visit->load(['patient', 'vitals']);
    }

    public function recordVitals(Visit $visit, array $data, User $nurse): void
    {
        $complaint = $data['complaint'] ?? null;
        unset($data['complaint']);

        $visit->vitals()->updateOrCreate(
            ['visit_id' => $visit->id],
            $data + ['recorded_by' => $nurse->id]
        );

        $visit->update(array_filter([
            'status' => VisitStatus::WaitingDoctor,
            'complaint' => $complaint,
        ]));
    }
}
