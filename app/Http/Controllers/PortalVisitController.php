<?php

namespace App\Http\Controllers;

use App\Enums\VisitStatus;
use App\Enums\WorkStatus;
use App\Models\LabAttachment;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;


class PortalVisitController extends Controller
{
    public function index(Request $request)
    {
        $patient = $request->user()->patient;
        abort_unless($patient, 403);

        $visits = $patient->visits()
            ->with('doctor')
            ->withCount(['labRequests', 'prescriptions'])
            ->latest('checked_in_at')
            ->paginate(10);

        return view('portal.visits.index', compact('visits'));
    }

    public function show(Request $request, Visit $visit)
    {
        $this->authorizeVisit($request, $visit);

        $visit->load([
            'doctor',
            'vitals',
            'prescriptions.drug',
            'labRequests.test',
            'labRequests.attachments',
            'labRequests.processor',
            'invoice.items',
        ]);

        return view('portal.visits.show', [
            'visit'      => $visit,
            'inProgress' => ! $this->detailsVisible($visit),
        ]);
    }

    /** The same printable report the lab prints. */
    public function labReport(Request $request, Visit $visit)
    {
        $this->authorizeVisit($request, $visit);
        abort_unless($this->detailsVisible($visit), 404);

        $requests = $visit->labRequests()
            ->completed()
            ->with(['test', 'attachments', 'processor', 'requester'])
            ->orderBy('id')
            ->get();

        abort_if($requests->isEmpty(), 404, 'There are no completed results to print for this visit.');

        $visit->load(['patient', 'doctor']);

        return view('lab.report', compact('visit', 'requests'));
    }

    /** Lab images and documents, only for the patient they belong to. */
    public function attachment(Request $request, LabAttachment $attachment)
    {
        $labRequest = $attachment->labRequest()->with('visit')->first();
        $visit      = $labRequest->visit;

        abort_unless(
            $visit->patient_id === $request->user()->patient_id
                && $this->detailsVisible($visit)
                && $labRequest->status === WorkStatus::Completed,
            404
        );

        $disk = Storage::disk('local');
        abort_unless($disk->exists($attachment->path), 404);

        return $disk->response($attachment->path, $attachment->original_name, [
            'Content-Type'           => $attachment->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, max-age=3600',
        ]);
    }

    private function authorizeVisit(Request $request, Visit $visit): void
    {
        abort_unless($request->user()->patient_id && $visit->patient_id === $request->user()->patient_id, 404);
    }

    /** Lab-only visits show results as they arrive; consultations only once the doctor has completed them. */
    private function detailsVisible(Visit $visit): bool
    {
        return $visit->isLabOnly() || $visit->status === VisitStatus::Completed;
    }
}