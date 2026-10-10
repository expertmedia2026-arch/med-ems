<?php

namespace App\Http\Controllers;

use App\Http\Requests\LabSearchRequest;
use App\Http\Requests\SaveLabResultRequest;
use App\Models\LabRequest;
use App\Models\Visit;
use App\Services\LabWorkflowService;
use Illuminate\Http\Request;

class LabController extends Controller
{
    public function index(LabSearchRequest $request, LabWorkflowService $lab)
    {
        $term = trim($request->validated('q') ?? '');

        return view('lab.index', $lab->indexData($request->user(), $term));
    }

    public function show(Request $request, LabRequest $labRequest, LabWorkflowService $lab)
    {
        return view('lab.show', $lab->detailData($labRequest, $request->user()));
    }

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
