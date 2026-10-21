<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmergencyCheckInRequest;
use App\Http\Requests\ReceiveMortuaryCaseRequest;
use App\Http\Requests\StoreEmergencyProgressNoteRequest;
use App\Http\Requests\UnidentifiedEmergencyCheckInRequest;
use App\Models\MortuaryCase;
use App\Models\Patient;
use App\Services\EmergencyService;
use App\Services\EmergencyProgressNoteService;
use App\Services\MortuaryCaseService;
use Illuminate\Http\Request;

class EmergencyController extends Controller
{
    public function index(Request $request, EmergencyService $emergency)
    {
        abort_unless($request->user()->canAccessEmergency(), 403);

        $search = trim($request->string('q')->value());
        $patients = $search === '' ? collect() : Patient::query()
            ->search($search)
            ->orderBy('last_name')
            ->limit(10)
            ->get();

        return view('emergency.index', [
            'visits' => $emergency->queue(),
            'mortuaryHandoffs' => $emergency->mortuaryHandoffs(),
            'patients' => $patients,
            'search' => $search,
        ]);
    }

    public function store(EmergencyCheckInRequest $request, Patient $patient, EmergencyService $emergency)
    {
        $visit = $emergency->checkIn($patient, $request->validated(), $request->user());

        if (! $visit) {
            return back()->with('error', 'This patient already has an active visit or inpatient admission. Review their record before creating another ER visit.');
        }

        return redirect()->route('emergency.index')
            ->with('success', "{$patient->full_name} checked in to Accident & Emergency. Send them for immediate triage.");
    }

    public function storeUnidentified(UnidentifiedEmergencyCheckInRequest $request, EmergencyService $emergency)
    {
        $visit = $emergency->checkInUnidentified($request->validated(), $request->user());

        return redirect()->route('emergency.index')
            ->with('success', "Unidentified arrival recorded as {$visit->patient->hospital_number}. Send them for immediate triage.");
    }

    public function receiveMortuaryCase(ReceiveMortuaryCaseRequest $request, MortuaryCase $mortuaryCase, MortuaryCaseService $mortuary)
    {
        $mortuary->receive($mortuaryCase, $request->validated(), $request->user());

        return redirect()->route('emergency.index')->with('success', 'Mortuary handoff received and storage location recorded.');
    }

    public function addProgressNote(StoreEmergencyProgressNoteRequest $request, \App\Models\Visit $visit, EmergencyProgressNoteService $notes)
    {
        $notes->add($visit, $request->validated('note'), $request->user());

        return back()->with('success', 'ER progress note added to the visit record.');
    }
}
