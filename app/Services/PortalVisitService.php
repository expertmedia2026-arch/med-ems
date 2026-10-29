<?php

namespace App\Services;

use App\Enums\VisitStatus;
use App\Enums\WorkStatus;
use App\Models\LabAttachment;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Support\Collection;

/**
 * Presents visit details and laboratory results to the patient portal.
 *
 * Visit ownership is checked against the patient ID before showing visit details,
 * reports, or attachments. Clinical details and lab results are exposed according
 * to the visit type, status, and lab-result email setting.
 */
class PortalVisitService
{
    /**
     * Paginate a patient's visits and annotate which details/results they may view.
     *
     * Relations containing lab attachments are loaded only for visits whose results
     * are portal-visible.
     *
     * @param  Patient  $patient  Patient whose visit history is displayed.
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator Paginated visit history with portal visibility attributes.
     */
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

    /**
     * Load an authorized visit and its portal-visible clinical and billing details.
     *
     * @param  Visit  $visit  Visit requested by the patient.
     * @param  int  $patientId  Authenticated patient's ID, used to enforce ownership.
     * @return array{visit: Visit, inProgress: bool} Visit and whether normal details are still in progress.
     */
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

    /**
     * Prepare completed laboratory requests and their attachments for a patient report.
     *
     * The visit must belong to the patient and be eligible for portal lab-result access;
     * the method returns not found when no completed results exist.
     *
     * @param  Visit  $visit  Visit whose lab report is requested.
     * @param  int  $patientId  Authenticated patient's ID, used to enforce ownership.
     * @return array{visit: Visit, requests: Collection<int, \App\Models\LabRequest>} Report data with patient and doctor loaded.
     */
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

    /**
     * Check whether a lab attachment belongs to a completed result visible to a patient.
     *
     * The attachment's lab request must resolve to a visit owned by the patient, with
     * portal-visible results and completed request status.
     *
     * @param  LabAttachment  $attachment  File requested for download.
     * @param  int  $patientId  Authenticated patient's ID.
     * @return bool Whether the patient may access the attachment.
     */
    public function attachmentVisibleToPatient(LabAttachment $attachment, int $patientId): bool
    {
        $labRequest = $attachment->labRequest()->with('visit')->first();
        $visit = $labRequest?->visit;

        return (bool) ($visit
            && $visit->patient_id === $patientId
            && $this->labResultsVisible($visit)
            && $labRequest->status === WorkStatus::Completed);
    }

    /**
     * Require that a visit belongs to the authenticated patient.
     *
     * Ownership mismatches are returned as not found to avoid disclosing other visits.
     *
     * @param  Visit  $visit  Visit being accessed.
     * @param  int  $patientId  Authenticated patient's ID.
     */
    public function authorizePatientVisit(Visit $visit, int $patientId): void
    {
        abort_unless($visit->patient_id === $patientId, 404);
    }

    /**
     * Decide whether a patient may see ordinary visit details.
     *
     * Lab-only visits are immediately visible; other visits become visible after completion.
     *
     * @param  Visit  $visit  Visit whose detail visibility is evaluated.
     * @return bool Whether normal visit details may be shown.
     */
    private function detailsVisible(Visit $visit): bool
    {
        return $visit->isLabOnly() || $visit->status === VisitStatus::Completed;
    }

    /**
     * Decide whether lab results may be exposed in the patient portal.
     *
     * Results are visible only for lab-only visits when results have not been emailed.
     *
     * @param  Visit  $visit  Visit whose lab-result visibility is evaluated.
     * @return bool Whether portal lab results may be shown.
     */
    private function labResultsVisible(Visit $visit): bool
    {
        return $visit->isLabOnly() && ! $visit->email_lab_results;
    }
}
