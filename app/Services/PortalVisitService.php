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
        $visits = $patient->visits()
            ->with(['doctor.department', 'department', 'consultationType.department', 'labRequests.test', 'admission'])
            ->withCount(['labRequests', 'prescriptions'])
            ->latest('checked_in_at')
            ->paginate(10);

        $visits->getCollection()->each(function (Visit $visit) {
            $visit->setAttribute('patient_details_visible', $this->detailsVisible($visit));
            $labResultsVisible = $this->labResultsVisible($visit);
            $visit->setAttribute('patient_lab_results_visible', $labResultsVisible);
            if ($labResultsVisible) {
                $visit->loadMissing('labRequests.attachments');
            }
        });

        return $visits;
    }

    public function showForPatient(Visit $visit, int $patientId): array
    {
        $this->authorizePatientVisit($visit, $patientId);
        $relations = [
            'doctor.department', 'department', 'consultationType.department', 'vitals', 'prescriptions.drug', 'labRequests.test',
            'invoice.items',
            'admission',
        ];
        if ($this->labResultsVisible($visit)) {
            $relations[] = 'labRequests.attachments';
        }
        $visit->load($relations);
        $visit->setAttribute('patient_lab_results_visible', $this->labResultsVisible($visit));

        return ['visit' => $visit, 'inProgress' => ! $this->detailsVisible($visit)];
    }

    /** @return array{visit: Visit, requests: Collection} */
    public function labReportData(Visit $visit, int $patientId): array
    {
        $this->authorizePatientVisit($visit, $patientId);
        abort_unless($this->labResultsVisible($visit), 404);

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
            && $this->labResultsVisible($visit)
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

    private function labResultsVisible(Visit $visit): bool
    {
        return $visit->isLabOnly() && ! $visit->email_lab_results;
    }
}
