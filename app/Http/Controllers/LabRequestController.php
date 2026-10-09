<?php

namespace App\Http\Controllers;

use App\Enums\WorkStatus;
use App\Models\LabRequest;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Services\BillingService;


class LabRequestController extends Controller
{
    public function store(Request $request, Visit $visit)
    {
        abort_unless($visit->isOpenFor($request->user()), 403);

        $data = $request->validate([
            'lab_test_ids'   => ['required', 'array', 'min:1'],
           'lab_test_ids.*' => [Rule::exists('lab_tests', 'id')->where('is_active', true)],
        ], ['lab_test_ids.required' => 'Select at least one test.']);

        $already = $visit->labRequests()->pluck('lab_test_id');
        $new     = collect($data['lab_test_ids'])->diff($already);

        foreach ($new as $testId) {
            $visit->labRequests()->create([
                'lab_test_id'  => $testId,
                'requested_by' => $request->user()->id,
            ]);
        }

        return back()->with('success', $new->count() . ' lab test(s) requested. The patient must pay at the cashier before results can be entered.');
    }

    public function destroy(Request $request, Visit $visit, LabRequest $labRequest)
    {
        abort_unless($visit->isOpenFor($request->user()), 403);
        abort_unless($labRequest->status === WorkStatus::Pending, 422);
        
        if (! app(BillingService::class)->release($labRequest)) {
            return back()->with('error', 'This test has already been paid for and cannot be removed.');
        }

        $labRequest->delete();

        return back()->with('success', 'Lab request removed.');
    }
}