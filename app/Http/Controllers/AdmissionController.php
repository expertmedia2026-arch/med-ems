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
use App\Services\AdmissionCareChartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

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

    public function sugarCheck(Request $request, Admission $admission, AdmissionCareChartService $charts)
    {
        $data = $request->validate([
            'reading_type' => ['required', Rule::in(['FBS', 'RBS'])],
            'result' => ['required', 'numeric', 'min:0', 'max:1000'],
            'unit' => ['required', Rule::in(['mmol/L', 'mg/dL'])],
            'checked_at' => ['required', 'date', 'before_or_equal:now'],
        ]);
        $check = $charts->recordSugar($admission, $data, $request->user());

        $message = 'Blood sugar check recorded in the admission chart.';
        if ($check->invoiceItem()->exists()) {
            $message .= ' The check charge was added to the patient invoice.';
        }

        return back()->with('success', $message);
    }

    public function fluidEntry(Request $request, Admission $admission, AdmissionCareChartService $charts)
    {
        $data = $request->validate([
            'flow' => ['required', Rule::in(['intake', 'output'])],
            'route_or_output' => ['required', Rule::in(['oral', 'subcutaneous', 'gastric', 'drip', 'rectal', 'IV', 'IP', 'urine', 'aspiration', 'vomit', 'NG', 'bowels', 'other'])],
            'fluid_type' => ['nullable', 'string', 'max:120'],
            'quantity_ml' => ['required', 'integer', 'min:1', 'max:100000'],
            'drops_per_minute' => ['nullable', 'integer', 'min:1', 'max:300'],
            'recorded_at' => ['required', 'date', 'before_or_equal:now'],
        ]);
        if ($data['flow'] === 'intake' && ! in_array($data['route_or_output'], ['oral', 'subcutaneous', 'gastric', 'drip', 'rectal', 'IV', 'IP'], true)) {
            return back()->withInput()->withErrors(['route_or_output' => 'Choose an intake route for an intake entry.']);
        }
        if ($data['flow'] === 'output' && ! in_array($data['route_or_output'], ['urine', 'aspiration', 'vomit', 'NG', 'bowels', 'other'], true)) {
            return back()->withInput()->withErrors(['route_or_output' => 'Choose an output type for an output entry.']);
        }
        $charts->recordFluid($admission, $data, $request->user());

        return back()->with('success', 'Fluid balance entry recorded.');
    }

    public function oxygen(Request $request, Admission $admission, AdmissionCareChartService $charts)
    {
        $data = $request->validate([
            'concentrator' => ['required', Rule::in(['yes', 'no'])],
            'flow_rate' => ['nullable', 'string', 'max:40'],
            'indication' => ['nullable', 'string', 'max:1000'],
            'administered_at' => ['required', 'date', 'before_or_equal:now'],
        ]);
        $charts->recordOxygen($admission, $data, $request->user());

        return back()->with('success', 'Oxygen administration recorded and the per-administration charge added to billing where configured.');
    }

    public function nebulizer(Request $request, Admission $admission, AdmissionCareChartService $charts)
    {
        $data = $request->validate(['used_at' => ['required', 'date', 'before_or_equal:now']]);
        $use = $charts->recordNebulizerUse($admission, $data, $request->user());

        return back()->with('success', 'Nebulizer use #'.$use->usage_number.' recorded'.($use->billable_block ? '; a new three-use machine-fee block was added to billing.' : '.'));
    }

    public function dama(Request $request, Admission $admission, AdmissionCareChartService $charts)
    {
        $data = $request->validate([
            'patient_or_representative' => ['required', 'string', 'max:150'],
            'relationship' => ['nullable', 'string', 'max:100'],
            'reason' => ['required', 'string', 'max:5000'],
            'risks_explained' => ['required', 'string', 'max:5000'],
            'follow_up_advice' => ['required', 'string', 'max:5000'],
            'additional_notes' => ['nullable', 'string', 'max:5000'],
            'witnesses' => ['required', 'array', 'min:1', 'max:3'],
            'witnesses.0.name' => ['required', 'string', 'max:150'],
            'witnesses.0.image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'witnesses.*.name' => ['sometimes', 'required_with:witnesses.*.image', 'string', 'max:150'],
            'witnesses.*.image' => ['sometimes', 'required_with:witnesses.*.name', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $witnesses = collect($data['witnesses'])->filter(fn ($witness) => filled($witness['name'] ?? null) && ($witness['image'] ?? null))
            ->map(fn ($witness) => ['name' => $witness['name'], 'image' => $witness['image']])->values()->all();
        $record = $charts->dischargeDama($admission, $data, $witnesses, $request->user());

        return redirect()->route('admissions.dama.print', [$admission, $record])->with('success', 'DAMA discharge recorded and the inpatient admission closed.');
    }

    public function printDama(Request $request, Admission $admission)
    {
        app(AdmissionService::class)->showData($admission, $request->user());
        $record = $admission->damaRecord()->with('witnesses', 'clinician')->firstOrFail();

        return view('admissions.dama-print', compact('admission', 'record'));
    }

    public function damaWitness(Request $request, Admission $admission, int $witness)
    {
        app(AdmissionService::class)->showData($admission, $request->user());
        $image = $admission->damaRecord()->firstOrFail()->witnesses()->findOrFail($witness);
        abort_unless(Storage::disk('local')->exists($image->image_path), 404);

        return Storage::disk('local')->response($image->image_path);
    }
}
