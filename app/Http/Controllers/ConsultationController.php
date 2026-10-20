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

class ConsultationController extends Controller
{
    public function index(Request $request, ConsultationService $consultations)
    {
        $visits = $consultations->queueFor($request->user());

        return view('consultations.index', compact('visits'));
    }

    public function historyIndex(Request $request, ConsultationService $consultations)
    {
        $visits = $consultations->historyFor($request->user(), $request->string('search')->trim()->value());

        return view('consultations.history-index', compact('visits'));
    }

    public function start(Request $request, Visit $visit, ConsultationService $consultations)
    {
        $consultations->start($visit, $request->user());

        return redirect()->route('consultations.show', $visit);
    }

    public function show(Request $request, Visit $visit, ConsultationService $consultations)
    {
        if ($visit->status === VisitStatus::Inpatient && $visit->admission) {
            return redirect()->route('admissions.show', $visit->admission);
        }

        return view('consultations.show', $consultations->showData($visit, $request->user()));
    }

    public function update(ConsultationNotesRequest $request, Visit $visit, ConsultationService $consultations)
    {
        $consultations->saveNotes($visit, $request->validated(), $request->user());

        return back()->with('success', 'Consultation notes saved.');
    }

    public function complete(CompleteConsultationRequest $request, Visit $visit, ConsultationService $consultations)
    {
        $consultations->complete($visit, $request->validated(), $request->user());

        return redirect()
            ->route('consultations.index')
            ->with('success', "Consultation completed for {$visit->patient->full_name}.");
    }

    public function admit(AdmitPatientRequest $request, Visit $visit, AdmissionService $admissions)
    {
        $admission = $admissions->admit($visit, $request->validated(), $request->user());

        return redirect()->route('admissions.show', $admission)
            ->with('success', 'Patient admitted. The consultation is closed and linked to the active inpatient record.');
    }

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
