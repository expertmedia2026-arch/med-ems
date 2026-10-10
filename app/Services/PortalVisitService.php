<?php

namespace App\Services;

use App\Enums\VisitStatus;
use App\Enums\WorkStatus;
use App\Models\LabAttachment;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Support\Collection;

class PortalVisitService
{
    public function index(Patient $patient)
    {
        return $patient->visits()
            ->with('doctor')
            ->withCount(['labRequests', 'prescriptions'])
            ->latest('checked_in_at')
            ->paginate(10);
    }

    public function showForPatient(Visit $visit, int $patientId): array
    {
        $this->authorizePatientVisit($visit, $patientId);
        $visit->load([
            'doctor', 'vitals', 'prescriptions.drug', 'labRequests.test',
            'labRequests.attachments', 'labRequests.processor', 'invoice.items',
        ]);

        return ['visit' => $visit, 'inProgress' => ! $this->detailsVisible($visit)];
    }

    /** @return array{visit: Visit, requests: Collection} */
    public function labReportData(Visit $visit, int $patientId): array
    {
        $this->authorizePatientVisit($visit, $patientId);
        abort_unless($this->detailsVisible($visit), 404);

        $requests = $visit->labRequests()
            ->completed()
            ->with(['test', 'attachments', 'processor', 'requester'])
            ->orderBy('id')
            ->get();

        abort_if($requests->isEmpty(), 404, 'There are no completed results to print for this visit.');
        $visit->load(['patient', 'doctor']);

        return compact('visit', 'requests');
    }

    public function attachmentVisibleToPatient(LabAttachment $attachment, int $patientId): bool
    {
        $labRequest = $attachment->labRequest()->with('visit')->first();
        $visit = $labRequest?->visit;

        return (bool) ($visit
            && $visit->patient_id === $patientId
            && $this->detailsVisible($visit)
            && $labRequest->status === WorkStatus::Completed);
    }

    public function authorizePatientVisit(Visit $visit, int $patientId): void
    {
        abort_unless($visit->patient_id === $patientId, 404);
    }

    private function detailsVisible(Visit $visit): bool
    {
        return $visit->isLabOnly() || $visit->status === VisitStatus::Completed;
    }
}
