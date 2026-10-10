<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteConsultationRequest;
use App\Http\Requests\ConsultationHistoryRequest;
use App\Http\Requests\ConsultationNotesRequest;
use App\Models\Visit;
use App\Services\ConsultationService;
use Illuminate\Http\Request;

class ConsultationController extends Controller
{
    public function index(Request $request, ConsultationService $consultations)
    {
        $visits = $consultations->queueFor($request->user());

        return view('consultations.index', compact('visits'));
    }

    public function start(Request $request, Visit $visit, ConsultationService $consultations)
    {
        $consultations->start($visit, $request->user());

        return redirect()->route('consultations.show', $visit);
    }

    public function show(Request $request, Visit $visit, ConsultationService $consultations)
    {
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

    public function history(ConsultationHistoryRequest $request, Visit $visit, ConsultationService $consultations)
    {
        $visit = $consultations->historyData($visit);

        // "Back" returns to the consultation the doctor came from
        $backUrl = $request->validated('from')
            ? route('consultations.show', (int) $request->validated('from'))
            : route('consultations.index');

        return view('consultations.history', compact('visit', 'backUrl'));
    }
}
