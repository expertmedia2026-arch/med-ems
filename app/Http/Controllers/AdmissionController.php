<?php

namespace App\Http\Controllers;

use App\Http\Requests\DischargeAdmissionRequest;
use App\Http\Requests\RecordAdmissionVitalsRequest;
use App\Http\Requests\ReviewAdmissionLabResultRequest;
use App\Http\Requests\StorePrescriptionRequest;
use App\Http\Requests\StoreAdmissionNoteRequest;
use App\Models\Admission;
use App\Models\LabRequest;
use App\Services\AdmissionService;
use Illuminate\Http\Request;

class AdmissionController extends Controller
{
    public function index(Request $request, AdmissionService $admissions)
    {
        $validated = $request->validate(['search' => ['nullable', 'string', 'max:100']]);

        return view('admissions.index', $admissions->indexData($request->user(), trim($validated['search'] ?? '')));
    }

    public function show(Request $request, Admission $admission, AdmissionService $admissions)
    {
        return view('admissions.show', $admissions->showData($admission, $request->user()));
    }

    public function discharge(DischargeAdmissionRequest $request, Admission $admission, AdmissionService $admissions)
    {
        $admission = $admissions->discharge($admission, $request->validated(), $request->user());

        return redirect()->route('admissions.show', $admission)
            ->with('success', 'Discharge completed. The linked consultation is now closed and the discharge plan is recorded.');
    }

    public function reviewLabResult(ReviewAdmissionLabResultRequest $request, Admission $admission, LabRequest $labRequest, AdmissionService $admissions)
    {
        $admissions->reviewLabResult($admission, $labRequest, $request->validated(), $request->user());

        return redirect()->route('admissions.show', $admission)->with('success', 'Laboratory result reviewed and added to the admission record.');
    }

    public function addProgressNote(StoreAdmissionNoteRequest $request, Admission $admission, AdmissionService $admissions)
    {
        $admissions->addProgressNote($admission, $request->validated('note'), $request->user());

        return redirect()->route('admissions.show', $admission)->with('success', 'Inpatient progress note added to the clinical record.');
    }

    public function recordVitals(RecordAdmissionVitalsRequest $request, Admission $admission, AdmissionService $admissions)
    {
        $admissions->recordVitals($admission, $request->validated(), $request->user());

        return redirect()->route('admissions.show', $admission)->with('success', 'Inpatient observations recorded in the admission chart.');
    }

    public function prescribe(StorePrescriptionRequest $request, Admission $admission, AdmissionService $admissions)
    {
        $result = $admissions->prescribeMedication($admission, $request->validated(), $request->user());

        if ($result['error']) {
            $field = str_starts_with($result['error'], 'Allergy alert:') ? 'drug_id' : 'quantity';

            return back()->withInput()->withErrors([$field => $result['error']]);
        }

        $message = $result['shortage'] > 0
            ? "{$result['reserved_quantity']} of {$request->validated('quantity')} {$result['drug']->name} unit(s) reserved; {$result['shortage']} unavailable. Review the stock shortage and arrange an alternative or external supply."
            : "{$result['drug']->name} added to the inpatient medication orders and sent to pharmacy.";

        return redirect()->route('admissions.show', $admission)->with('success', $message);
    }
}
