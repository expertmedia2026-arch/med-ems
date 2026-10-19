<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordVitalsRequest;
use App\Http\Requests\TriageQueueRequest;
use App\Models\Visit;
use App\Services\BillingService;
use App\Services\TriageService;

class TriageController extends Controller
{
    public function index(TriageQueueRequest $request, TriageService $triage)
    {
        return view('triage.index', $triage->indexData($request->user(), $request->validated()));
    }

    public function show(Visit $visit, BillingService $billing, TriageService $triage)
    {
        $triage->assertVitalsEditable($visit, request()->user());

        if (! $billing->consultationPaid($visit)) {
            return $this->unpaid();
        }

        $visit = $triage->loadVitalsData($visit);

        return view('triage.show', compact('visit'));
    }

    public function store(RecordVitalsRequest $request, Visit $visit, BillingService $billing, TriageService $triage)
    {
        $triage->assertVitalsEditable($visit, $request->user());

        if (! $billing->consultationPaid($visit)) {
            return $this->unpaid();
        }

        $triage->recordVitals($visit, $request->validated(), $request->user());

        return redirect()
            ->route('triage.index')
            ->with('success', "Vitals saved. {$visit->patient->full_name} is now waiting for a doctor.");
    }

    private function unpaid()
    {
        return redirect()
            ->route('triage.index')
            ->with('error', 'The consultation charge has not been paid. Please send the patient to the cashier before recording vitals.');
    }
}
