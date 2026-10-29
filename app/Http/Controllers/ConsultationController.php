<?php

namespace App\Http\Controllers;

use App\Enums\VisitStatus;
use App\Http\Requests\CompleteConsultationRequest;
use App\Http\Requests\AdmitPatientRequest;
use App\Http\Requests\ConsultationHistoryRequest;
use App\Http\Requests\ConsultationNotesRequest;
use App\Models\Visit;
use App\Services\ConsultationService;
use App\Services\AdmissionService;
use Illuminate\Http\Request;

/**
 * Coordinates consultation queues and clinical actions with consultation and admission services.
 */
class ConsultationController extends Controller
{
    /**
     * Retrieves the signed-in clinician's consultation queue and renders the active consultation list.
     */
    public function index(Request $request, ConsultationService $consultations)
    {
        $visits = $consultations->queueFor($request->user());

        return view('consultations.index', compact('visits'));
    }

    /**
     * Uses the optional search term to retrieve the user's consultation history and renders the results.
     */
    public function historyIndex(Request $request, ConsultationService $consultations)
    {
        $visits = $consultations->historyFor($request->user(), $request->string('search')->trim()->value());

        return view('consultations.history-index', compact('visits'));
    }

    /**
     * Asks the consultation service to start the route-bound visit for the current user, then redirects to its workspace.
     */
    public function start(Request $request, Visit $visit, ConsultationService $consultations)
    {
        $consultations->start($visit, $request->user());

        return redirect()->route('consultations.show', $visit);
    }

    /**
     * Loads authorized consultation data, redirecting admitted visits to inpatient care or rendering the consultation workspace.
     */
    public function show(Request $request, Visit $visit, ConsultationService $consultations)
    {
        if ($visit->status === VisitStatus::Inpatient && $visit->admission) {
            return redirect()->route('admissions.show', $visit->admission);
        }

        return view('consultations.show', $consultations->showData($visit, $request->user()));
    }

    /**
     * Saves validated consultation notes for the current user and returns to the consultation workspace.
     */
    public function update(ConsultationNotesRequest $request, Visit $visit, ConsultationService $consultations)
    {
        $consultations->saveNotes($visit, $request->validated(), $request->user());

        return back()->with('success', 'Consultation notes saved.');
    }

    /**
     * Completes the visit using validated clinical input and redirects to the queue with a patient-specific confirmation.
     */
    public function complete(CompleteConsultationRequest $request, Visit $visit, ConsultationService $consultations)
    {
        $consultations->complete($visit, $request->validated(), $request->user());

        return redirect()
            ->route('consultations.index')
            ->with('success', "Consultation completed for {$visit->patient->full_name}.");
    }

    /**
     * Admits the patient using validated admission details and redirects to the new inpatient record.
     */
    public function admit(AdmitPatientRequest $request, Visit $visit, AdmissionService $admissions)
    {
        $admission = $admissions->admit($visit, $request->validated(), $request->user());

        return redirect()->route('admissions.show', $admission)
            ->with('success', 'Patient admitted. The consultation is closed and linked to the active inpatient record.');
    }

    /**
     * Retrieves authorized visit history and renders it with a return destination selected for the user's role and origin.
     */
    public function history(ConsultationHistoryRequest $request, Visit $visit, ConsultationService $consultations)
    {
        $visit = $consultations->historyData($visit, $request->user());

        // Return administrators to the patient record; doctors return to their consultation workspace.
        $backUrl = $request->user()->hasRole('admin')
            ? route('patients.show', $visit->patient)
            : ($request->validated('from')
                ? route('consultations.show', (int) $request->validated('from'))
                : route('consultations.index'));

        return view('consultations.history', compact('visit', 'backUrl'));
    }
}
