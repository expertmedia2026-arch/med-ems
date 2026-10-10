<?php

namespace App\Http\Controllers;

use App\Http\Requests\RequestLabTestsRequest;
use App\Models\LabRequest;
use App\Models\Visit;
use App\Services\LabWorkflowService;
use Illuminate\Http\Request;

class LabRequestController extends Controller
{
    public function store(RequestLabTestsRequest $request, Visit $visit, LabWorkflowService $lab)
    {
        abort_unless($visit->isOpenFor($request->user()), 403);

        $count = $lab->requestTests($visit, $request->validated('lab_test_ids'), $request->user()->id);

        return back()->with('success', $count.' lab test(s) requested. The patient must pay at the cashier before results can be entered.');
    }

    public function destroy(Request $request, Visit $visit, LabRequest $labRequest, LabWorkflowService $lab)
    {
        abort_unless($visit->isOpenFor($request->user()), 403);
        if (! $lab->removeRequest($labRequest, $request->user())) {
            return back()->with('error', 'This test has already been paid for and cannot be removed.');
        }

        return back()->with('success', 'Lab request removed.');
    }
}
