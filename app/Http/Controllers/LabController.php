<?php

namespace App\Http\Controllers;

use App\Http\Requests\LabSearchRequest;
use App\Http\Requests\SaveLabResultRequest;
use App\Models\LabRequest;
use App\Models\Visit;
use App\Services\LabWorkflowService;
use Illuminate\Http\Request;

/**
 * Coordinates laboratory queue, result, and report pages with LabWorkflowService.
 */
class LabController extends Controller
{
    /**
     * Validates and trims the search term, then renders the user-scoped laboratory queue.
     */
    public function index(LabSearchRequest $request, LabWorkflowService $lab)
    {
        $term = trim($request->validated('q') ?? '');

        return view('lab.index', $lab->indexData($request->user(), $term));
    }

    /**
     * Retrieves an authorized laboratory request's detail data and renders its result-entry page.
     */
    public function show(Request $request, LabRequest $labRequest, LabWorkflowService $lab)
    {
        return view('lab.show', $lab->detailData($labRequest, $request->user()));
    }

    /**
     * Saves validated results and files, then selects the redirect message from the service's payment and notification outcome.
     */
    public function update(SaveLabResultRequest $request, LabRequest $labRequest, LabWorkflowService $lab)
    {
        $outcome = $lab->saveResult($labRequest, $request->validated(), $request->file('files', []), $request->user());

        if ($outcome === 'locked') {
            return redirect()
                ->route('lab.show', $labRequest)
                ->with('error', 'This result is locked because the visit is completed.');
        }
        if ($outcome === 'unpaid') {
            return redirect()
                ->route('lab.show', $labRequest)
                ->with('error', 'Payment is required before a result can be entered.');
        }
        if ($outcome === 'admission_review') {
            return redirect()->route('lab.index')
                ->with('success', "Result saved for {$labRequest->visit->patient->full_name} and added to the attending doctor’s admission review queue.");
        }
        if ($outcome === 'notification_failed') {
            $inpatient = $labRequest->visit->status->value === 'inpatient';
            return redirect()
                ->route('lab.index')
                ->with('error', "Result saved for {$labRequest->visit->patient->full_name}".($inpatient ? ' and added to the attending doctor’s admission review queue' : '').', but the result email could not be sent. Please contact the patient manually.');
        }
        if ($outcome === 'notification_missing_email') {
            $inpatient = $labRequest->visit->status->value === 'inpatient';
            return redirect()
                ->route('lab.index')
                ->with('error', "Result saved for {$labRequest->visit->patient->full_name}".($inpatient ? ' and added to the attending doctor’s admission review queue' : '').', but no email is on file to notify them.');
        }

        if ($labRequest->visit->status->value === 'inpatient') {
            return redirect()->route('lab.index')
                ->with('success', "Result saved for {$labRequest->visit->patient->full_name} and added to the attending doctor’s admission review queue.");
        }

        return redirect()
            ->route('lab.index')
            ->with('success', "Result saved for {$labRequest->visit->patient->full_name}.");
    }

    /** Printable report of every completed test on a visit. */
    public function report(Request $request, Visit $visit, LabWorkflowService $lab)
    {
        return view('lab.report', $lab->reportData($visit, $request->user()));
    }
}
