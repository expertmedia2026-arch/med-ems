<?php

namespace App\Http\Controllers;

use App\Http\Requests\DischargeAdmissionRequest;
use App\Http\Requests\RecordAdmissionVitalsRequest;
use App\Http\Requests\ReviewAdmissionLabResultRequest;
use App\Http\Requests\StorePrescriptionRequest;
use App\Http\Requests\StoreAdmissionNoteRequest;
use App\Http\Requests\StoreAdmissionDeathRequest;
use App\Models\Admission;
use App\Models\HospitalSetting;
use App\Models\HospitalWorkflow;
use App\Models\LabRequest;
use App\Services\AdmissionService;
use App\Services\AdmissionCareChartService;
use App\Services\HospitalDeathCertificateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Coordinates inpatient views and clinical record updates with admission and care-chart services.
 */
class AdmissionController extends Controller
{
    /**
     * Validates the optional search term and renders the user-scoped admission list.
     */
    public function index(Request $request, AdmissionService $admissions)
    {
        $validated = $request->validate(['search' => ['nullable', 'string', 'max:100']]);

        return view('admissions.index', $admissions->indexData($request->user(), trim($validated['search'] ?? '')));
    }

    /**
     * Loads authorized admission details and the active admission workflow for the inpatient view.
     */
    public function show(Request $request, Admission $admission, AdmissionService $admissions)
    {
        return view('admissions.show', array_merge(
            $admissions->showData($admission, $request->user()),
            ['admissionWorkflow' => HospitalWorkflow::activeDefinition('admission')],
        ));
    }

    /**
     * Sends validated discharge details to AdmissionService and redirects to the updated admission.
     */
    public function discharge(DischargeAdmissionRequest $request, Admission $admission, AdmissionService $admissions)
    {
        $admission = $admissions->discharge($admission, $request->validated(), $request->user());

        return redirect()->route('admissions.show', $admission)
            ->with('success', 'Discharge completed. The linked consultation is now closed and the discharge plan is recorded.');
    }

    /**
     * Records the validated inpatient death and redirects with the resulting release or mortuary route.
     */
    public function declareDeceased(StoreAdmissionDeathRequest $request, Admission $admission, AdmissionService $admissions)
    {
        $case = $admissions->declareDeceased($admission, $request->validated(), $request->user());
        $message = $case->release_route === 'direct_family_release'
            ? 'Death was recorded and direct release to the family was documented.'
            : 'Death was recorded and a mortuary transfer case was opened.';

        return redirect()->route('admissions.show', $admission)->with('success', $message);
    }

    /**
     * Validates hospital certificate details, issues the certificate, and redirects to its PDF download.
     */
    public function issueDeathCertificate(Request $request, Admission $admission, HospitalDeathCertificateService $certificates)
    {
        $data = $request->validate([
            'registration_number' => ['required', 'string', 'max:100'],
            'certified_cause' => ['required', 'string', 'min:2', 'max:5000'],
            'other_conditions' => ['nullable', 'string', 'max:5000'],
        ]);
        $case = $certificates->issue($admission, $data, $request->user());

        return redirect()->route('admissions.death-certificate.download', $admission)
            ->with('success', 'Hospital death certificate '.$case->death_certificate_number.' issued and recorded.');
    }

    /**
     * Applies admission access checks, then streams the issued hospital death certificate as a PDF.
     */
    public function downloadDeathCertificate(Request $request, Admission $admission, AdmissionService $admissions)
    {
        $admissions->showData($admission, $request->user());
        $admission->loadMissing(['patient', 'department', 'attendingDoctor', 'visit.mortuaryCase.deathCertificateIssuedBy']);
        $case = $admission->visit->mortuaryCase;
        abort_unless($case?->death_certificate_issued_at, 404, 'No hospital death certificate has been issued for this admission.');

        return Pdf::loadView('admissions.death-certificate-pdf', [
            'admission' => $admission,
            'visit' => $admission->visit,
            'case' => $case,
            'hospital' => HospitalSetting::current(),
        ])->setPaper('a4')->download('death-certificate-'.$case->death_certificate_number.'.pdf');
    }

    /**
     * Records a validated clinician review of a lab result and redirects to the admission detail page.
     */
    public function reviewLabResult(ReviewAdmissionLabResultRequest $request, Admission $admission, LabRequest $labRequest, AdmissionService $admissions)
    {
        $admissions->reviewLabResult($admission, $labRequest, $request->validated(), $request->user());

        return redirect()->route('admissions.show', $admission)->with('success', 'Laboratory result reviewed and added to the admission record.');
    }

    /**
     * Adds the validated progress note to the admission and redirects back to its detail page.
     */
    public function addProgressNote(StoreAdmissionNoteRequest $request, Admission $admission, AdmissionService $admissions)
    {
        $admissions->addProgressNote($admission, $request->validated('note'), $request->user());

        return redirect()->route('admissions.show', $admission)->with('success', 'Inpatient progress note added to the clinical record.');
    }

    /**
     * Records validated inpatient observations through AdmissionService and returns to the admission detail page.
     */
    public function recordVitals(RecordAdmissionVitalsRequest $request, Admission $admission, AdmissionService $admissions)
    {
        $admissions->recordVitals($admission, $request->validated(), $request->user());

        return redirect()->route('admissions.show', $admission)->with('success', 'Inpatient observations recorded in the admission chart.');
    }

    /**
     * Submits a validated medication order, reports service errors or stock shortage, and hands successful orders to pharmacy.
     */
    public function prescribe(StorePrescriptionRequest $request, Admission $admission, AdmissionService $admissions)
    {
        $result = $admissions->prescribeMedication($admission, $request->validated(), $request->user());

        if ($result['error']) {
            $field = str_starts_with($result['error'], 'Allergy alert:') ? 'drug_id' : 'quantity';

            return back()->withInput()->withErrors([$field => $result['error']]);
        }

        $admission->loadMissing('patient', 'visit');
        app(\App\Services\WorkflowNotificationService::class)->pharmacy(
            $admission->visit,
            "An inpatient medication order for {$admission->patient->full_name} ({$admission->patient->hospital_number}) is ready in the pharmacy queue.",
        );

        $message = $result['shortage'] > 0
            ? "{$result['reserved_quantity']} of {$request->validated('quantity')} {$result['drug']->name} unit(s) reserved; {$result['shortage']} unavailable. Review the stock shortage and arrange an alternative or external supply."
            : "{$result['drug']->name} added to the inpatient medication orders and sent to pharmacy.";

        return redirect()->route('admissions.show', $admission)->with('success', $message);
    }

    /**
     * Validates a blood-sugar reading and records it in the care chart, noting any configured invoice charge.
     */
    public function sugarCheck(Request $request, Admission $admission, AdmissionCareChartService $charts)
    {
        $data = $request->validate([
            'reading_type' => ['required', Rule::in(['FBS', 'RBS'])],
            'result' => ['required', 'numeric', 'min:0', 'max:1000'],
            'unit' => ['required', Rule::in(['mmol/L', 'mg/dL'])],
        ]);
        $check = $charts->recordSugar($admission, $data, $request->user());

        $message = 'Blood sugar check recorded in the admission chart.';
        if ($check->invoiceItem()->exists()) {
            $message .= ' The check charge was added to the patient invoice.';
        }

        return back()->with('success', $message);
    }

    /**
     * Validates fluid-flow details, rejects incompatible route/type combinations, and records the care-chart entry.
     */
    public function fluidEntry(Request $request, Admission $admission, AdmissionCareChartService $charts)
    {
        $data = $request->validate([
            'flow' => ['required', Rule::in(['intake', 'output'])],
            'route_or_output' => ['required', Rule::in(['oral', 'subcutaneous', 'gastric', 'drip', 'rectal', 'IV', 'IP', 'urine', 'aspiration', 'vomit', 'NG', 'bowels', 'other'])],
            'prescription_id' => ['required_if:flow,intake', 'nullable', 'integer', 'exists:prescriptions,id'],
            'quantity_ml' => ['required', 'integer', 'min:1', 'max:100000'],
            'drops_per_minute' => ['nullable', 'integer', 'min:1', 'max:300'],
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

    /**
     * Validates oxygen administration details and records the care-chart entry with configured billing where applicable.
     */
    public function oxygen(Request $request, Admission $admission, AdmissionCareChartService $charts)
    {
        $data = $request->validate([
            'concentrator' => ['required', Rule::in(['yes', 'no'])],
            'flow_rate' => ['nullable', 'string', 'max:40'],
            'indication' => ['nullable', 'string', 'max:1000'],
        ]);
        $charts->recordOxygen($admission, $data, $request->user());

        return back()->with('success', 'Oxygen administration recorded and the per-administration charge added to billing where configured.');
    }

    /**
     * Records nebulizer use and reports whether the service created a billable machine-use block.
     */
    public function nebulizer(Request $request, Admission $admission, AdmissionCareChartService $charts)
    {
        $use = $charts->recordNebulizerUse($admission, [], $request->user());

        return back()->with('success', 'Nebulizer use #'.$use->usage_number.' recorded'.($use->billable_block ? '; a new three-use machine-fee block was added to billing.' : '.'));
    }

    /**
     * Starts an insulin-machine billing block and reports its payment status while reopening the chart.
     */
    public function startInsulinMachineBlock(Request $request, Admission $admission, AdmissionCareChartService $charts)
    {
        $block = $charts->startInsulinMachineBlock($admission, $request->user());
        $message = $block->payment_complete
            ? 'Insulin machine block #'.$block->block_number.' is paid and ready for up to 3 bottle uses.'
            : 'Insulin machine block #'.$block->block_number.' was added to the patient invoice. Payment is required before recording bottle use or insulin doses.';

        return back()->with('success', $message)->with('open_insulin_machine_chart', true);
    }

    /**
     * Validates prescription and bottle details, records machine use, and returns to the open insulin chart.
     */
    public function recordInsulinMachineUse(Request $request, Admission $admission, AdmissionCareChartService $charts)
    {
        $data = $request->validate([
            'prescription_id' => ['required', 'integer', 'exists:prescriptions,id'],
            'bottle_label' => ['nullable', 'string', 'max:100'],
        ]);
        $use = $charts->recordInsulinMachineUse($admission, $data, $request->user());

        return back()->with('success', 'Insulin bottle use recorded for machine block #'.$use->block->block_number.'.')
            ->with('open_insulin_machine_chart', true);
    }

    /**
     * Validates the DAMA decision and witness evidence, records discharge through the care-chart service, and redirects to print.
     */
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

    /**
     * Applies admission access checks through AdmissionService and renders the DAMA record for printing.
     */
    public function printDama(Request $request, Admission $admission)
    {
        app(AdmissionService::class)->showData($admission, $request->user());
        $record = $admission->damaRecord()->with('witnesses', 'clinician')->firstOrFail();

        return view('admissions.dama-print', compact('admission', 'record'));
    }

    /**
     * Applies admission access checks and streams a locally stored witness image for the DAMA record.
     */
    public function damaWitness(Request $request, Admission $admission, int $witness)
    {
        app(AdmissionService::class)->showData($admission, $request->user());
        $image = $admission->damaRecord()->firstOrFail()->witnesses()->findOrFail($witness);
        abort_unless(Storage::disk('local')->exists($image->image_path), 404);

        return Storage::disk('local')->response($image->image_path);
    }
}
