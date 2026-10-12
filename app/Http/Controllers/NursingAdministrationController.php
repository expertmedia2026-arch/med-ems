<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMedicationAdministrationRequest;
use App\Http\Requests\WaiveMedicationAdministrationRequest;
use App\Models\Prescription;
use App\Models\Visit;
use App\Services\MedicationAdministrationService;
use Illuminate\Http\Request;

class NursingAdministrationController extends Controller
{
    public function index(\Illuminate\Http\Request $request, MedicationAdministrationService $administrations)
    {
        return view('nursing.administrations.index', ['visits' => $administrations->queue($request->user())]);
    }

    public function show(Visit $visit, MedicationAdministrationService $administrations)
    {
        return view('nursing.administrations.show', ['visit' => $administrations->visitDetails($visit, request()->user())]);
    }

    public function store(StoreMedicationAdministrationRequest $request, Visit $visit, Prescription $prescription, MedicationAdministrationService $administrations)
    {
        $record = $administrations->record($visit, $prescription, $request->validated(), $request->user());

        return back()->with('success', $record->status === 'administered'
            ? 'Medication administration recorded.'
            : 'Medication was not administered. The reason has been recorded; this visit remains open for follow-up.');
    }

    public function waive(WaiveMedicationAdministrationRequest $request, Visit $visit, Prescription $prescription, MedicationAdministrationService $administrations)
    {
        $administrations->waive($visit, $prescription, $request->validated('reason'), $request->user());

        return back()->with('success', 'Administration requirement waived with a recorded reason.');
    }

    public function discharge(Request $request, Visit $visit, MedicationAdministrationService $administrations)
    {
        $administrations->discharge($visit, $request->user());

        return redirect()->route('nursing.administrations.index')->with('success', 'Visit discharged. Nursing care and required injections are documented.');
    }
}
