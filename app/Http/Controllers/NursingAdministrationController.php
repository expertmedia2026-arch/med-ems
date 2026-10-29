<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMedicationAdministrationRequest;
use App\Http\Requests\WaiveMedicationAdministrationRequest;
use App\Models\Prescription;
use App\Models\Visit;
use App\Services\MedicationAdministrationService;
use Illuminate\Http\Request;

/**
 * Coordinates nursing queue, medication administration, and discharge actions with MedicationAdministrationService.
 */
class NursingAdministrationController extends Controller
{
    /**
     * Retrieves the signed-in user's medication-administration queue and renders it.
     */
    public function index(\Illuminate\Http\Request $request, MedicationAdministrationService $administrations)
    {
        return view('nursing.administrations.index', ['visits' => $administrations->queue($request->user())]);
    }

    /**
     * Loads authorized medication details for the selected visit and renders its administration chart.
     */
    public function show(Visit $visit, MedicationAdministrationService $administrations)
    {
        return view('nursing.administrations.show', ['visit' => $administrations->visitDetails($visit, request()->user())]);
    }

    /**
     * Records a validated administration outcome and returns with the outcome-specific confirmation.
     */
    public function store(StoreMedicationAdministrationRequest $request, Visit $visit, Prescription $prescription, MedicationAdministrationService $administrations)
    {
        $record = $administrations->record($visit, $prescription, $request->validated(), $request->user());

        return back()->with('success', $record->status === 'administered'
            ? 'Medication administration recorded.'
            : 'Medication was not administered. The reason has been recorded; this visit remains open for follow-up.');
    }

    /**
     * Records a validated reason for waiving administration and returns to the medication chart.
     */
    public function waive(WaiveMedicationAdministrationRequest $request, Visit $visit, Prescription $prescription, MedicationAdministrationService $administrations)
    {
        $administrations->waive($visit, $prescription, $request->validated('reason'), $request->user());

        return back()->with('success', 'Administration requirement waived with a recorded reason.');
    }

    /**
     * Delegates nursing discharge checks and redirects to the updated administration queue.
     */
    public function discharge(Request $request, Visit $visit, MedicationAdministrationService $administrations)
    {
        $administrations->discharge($visit, $request->user());

        return redirect()->route('nursing.administrations.index')->with('success', 'Visit discharged. Nursing care and required injections are documented.');
    }
}
