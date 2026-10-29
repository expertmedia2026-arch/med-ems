<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordVitalsRequest;
use App\Http\Requests\TriageQueueRequest;
use App\Models\Visit;
use App\Services\BillingService;
use App\Services\TriageService;
use App\Services\EmergencyPharmacyService;

/**
 * Enforces triage and billing requirements while delegating vital-sign workflows to TriageService.
 */
class TriageController extends Controller
{
    /**
     * Uses validated queue filters and the current user to render the triage queue.
     */
    public function index(TriageQueueRequest $request, TriageService $triage)
    {
        return view('triage.index', $triage->indexData($request->user(), $request->validated()));
    }

    /**
     * Authorizes vital editing, verifies payment, and renders the loaded visit with emergency stock choices.
     */
    public function show(Visit $visit, BillingService $billing, TriageService $triage, EmergencyPharmacyService $emergencyPharmacy)
    {
        $triage->assertVitalsEditable($visit, request()->user());

        if (! $billing->consultationPaid($visit)) {
            return $this->unpaid();
        }

        $visit = $triage->loadVitalsData($visit);

        return view('triage.show', [
            'visit' => $visit,
            'emergencyStockItems' => $visit->is_emergency ? $emergencyPharmacy->useOptions() : collect(),
        ]);
    }

    /**
     * Validates vital readings, checks triage authorization and payment, then records them and returns to the queue.
     */
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

    /**
     * Redirects unpaid visits to the triage queue with the required billing guidance.
     */
    private function unpaid()
    {
        return redirect()
            ->route('triage.index')
            ->with('error', 'The consultation charge has not been paid. Please send the patient to the billing desk before recording vitals.');
    }
}
