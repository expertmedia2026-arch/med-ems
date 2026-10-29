<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdmitPatientRequest;
use App\Http\Requests\DischargeAdmissionRequest;
use App\Http\Requests\RecordAdmissionVitalsRequest;
use App\Http\Requests\ReviewAdmissionLabResultRequest;
use App\Http\Requests\StoreAdmissionDeathRequest;
use App\Http\Requests\StoreAdmissionNoteRequest;
use App\Http\Requests\StorePrescriptionRequest;
use App\Http\Resources\Api\V1\AdmissionResource;
use App\Http\Resources\Api\V1\LabRequestResource;
use App\Models\Admission;
use App\Models\HospitalSetting;
use App\Models\LabRequest;
use App\Models\Visit;
use App\Services\AdmissionCareChartService;
use App\Services\AdmissionService;
use App\Services\HospitalDeathCertificateService;
use App\Services\WorkflowNotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AdmissionController extends Controller
{
    public function index(Request $request, AdmissionService $admissions): array
    {
        $validated = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $data = $admissions->indexData($request->user(), trim($validated['search'] ?? ''));

        return [
            'active_admissions' => $data['activeAdmissions']->through(
                fn (Admission $admission) => (new AdmissionResource($admission))->resolve($request),
            ),
            'discharged_admissions' => $data['dischargedAdmissions']->through(
                fn (Admission $admission) => (new AdmissionResource($admission))->resolve($request),
            ),
            'active_count' => $data['activeCount'],
            'pending_result_review_count' => $data['pendingReviewCount'],
        ];
    }

    public function show(Request $request, Admission $admission, AdmissionService $admissions): AdmissionResource
    {
        $data = $admissions->showData($admission, $request->user());

        return (new AdmissionResource($data['admission']))->additional([
            'can_manage' => $data['canManage'],
            'can_prescribe' => $data['canPrescribe'],
            'can_record_care' => $data['canRecordCare'],
            'pending_results' => LabRequestResource::collection($data['pendingResults']),
            'unresolved_results' => LabRequestResource::collection($data['unresolvedResults']),
        ]);
    }

    public function admit(AdmitPatientRequest $request, Visit $visit, AdmissionService $admissions): Response
    {
        $admission = $admissions->admit($visit, $request->validated(), $request->user());
        $admission->load(['patient', 'department', 'attendingDoctor']);

        return (new AdmissionResource($admission))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function discharge(DischargeAdmissionRequest $request, Admission $admission, AdmissionService $admissions): AdmissionResource
    {
        return new AdmissionResource($admissions->discharge($admission, $request->validated(), $request->user()));
    }

    public function declareDeceased(StoreAdmissionDeathRequest $request, Admission $admission, AdmissionService $admissions): array
    {
        $case = $admissions->declareDeceased($admission, $request->validated(), $request->user());

        return [
            'mortuary_case_id' => $case->id,
            'status' => $case->status,
            'release_route' => $case->release_route,
        ];
    }

    public function issueDeathCertificate(Request $request, Admission $admission, HospitalDeathCertificateService $certificates): array
    {
        abort_unless($request->user()->hasRole(Role::Doctor), 403);
        $data = $request->validate([
            'registration_number' => ['required', 'string', 'max:100'],
            'certified_cause' => ['required', 'string', 'min:2', 'max:5000'],
            'other_conditions' => ['nullable', 'string', 'max:5000'],
        ]);
        $case = $certificates->issue($admission, $data, $request->user());

        return [
            'certificate_number' => $case->death_certificate_number,
            'issued_at' => $case->death_certificate_issued_at?->toIso8601String(),
        ];
    }

    public function downloadDeathCertificate(Request $request, Admission $admission, AdmissionService $admissions): Response
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

    public function reviewLabResult(ReviewAdmissionLabResultRequest $request, Admission $admission, LabRequest $labRequest, AdmissionService $admissions): LabRequestResource
    {
        return new LabRequestResource($admissions->reviewLabResult($admission, $labRequest, $request->validated(), $request->user()));
    }

    public function addProgressNote(StoreAdmissionNoteRequest $request, Admission $admission, AdmissionService $admissions): array
    {
        $note = $admissions->addProgressNote($admission, $request->validated('note'), $request->user());
        $note->load('author');

        return [
            'id' => $note->id,
            'type' => $note->note_type,
            'note' => $note->note,
            'author' => $note->author?->name,
            'created_at' => $note->created_at?->toIso8601String(),
        ];
    }

    public function recordVitals(RecordAdmissionVitalsRequest $request, Admission $admission, AdmissionService $admissions): array
    {
        $vitals = $admissions->recordVitals($admission, $request->validated(), $request->user());
        $vitals->load('recorder');

        return [
            'id' => $vitals->id,
            'temperature' => $vitals->temperature,
            'pulse' => $vitals->pulse,
            'respiratory_rate' => $vitals->respiratory_rate,
            'bp_systolic' => $vitals->bp_systolic,
            'bp_diastolic' => $vitals->bp_diastolic,
            'spo2' => $vitals->spo2,
            'weight' => $vitals->weight,
            'height' => $vitals->height,
            'notes' => $vitals->notes,
            'recorded_by' => $vitals->recorder?->name,
            'recorded_at' => $vitals->recorded_at?->toIso8601String(),
        ];
    }

    public function prescribe(StorePrescriptionRequest $request, Admission $admission, AdmissionService $admissions, WorkflowNotificationService $notifications): array
    {
        $result = $admissions->prescribeMedication($admission, $request->validated(), $request->user());
        abort_if($result['error'], 422, $result['error']);
        $prescription = $admission->visit->prescriptions()->with('drug')->latest('id')->firstOrFail();
        $notifications->pharmacy(
            $admission->visit,
            "An inpatient medication order for {$admission->patient->full_name} ({$admission->patient->hospital_number}) is ready in the pharmacy queue.",
        );

        return [
            'prescription_id' => $prescription->id,
            'drug' => $result['drug']?->display_name,
            'reserved_quantity' => $result['reserved_quantity'],
            'shortage_quantity' => $result['shortage'],
            'message' => $result['shortage'] > 0
                ? "Only {$result['reserved_quantity']} of {$request->validated('quantity')} {$result['drug']->name} unit(s) were reserved; arrange an alternative for the shortage."
                : "{$result['drug']->name} added to the inpatient medication orders and sent to pharmacy.",
        ];
    }

    public function sugarCheck(Request $request, Admission $admission, AdmissionCareChartService $charts): array
    {
        $data = $request->validate([
            'reading_type' => ['required', Rule::in(['FBS', 'RBS'])],
            'result' => ['required', 'numeric', 'min:0', 'max:1000'],
            'unit' => ['required', Rule::in(['mmol/L', 'mg/dL'])],
        ]);
        $check = $charts->recordSugar($admission, $data, $request->user());

        return ['id' => $check->id, 'reading_type' => $check->reading_type, 'result' => $check->result, 'unit' => $check->unit, 'checked_at' => $check->checked_at?->toIso8601String()];
    }

    public function fluidEntry(Request $request, Admission $admission, AdmissionCareChartService $charts): array
    {
        $data = $request->validate([
            'flow' => ['required', Rule::in(['intake', 'output'])],
            'route_or_output' => ['required', Rule::in(['oral', 'subcutaneous', 'gastric', 'drip', 'rectal', 'IV', 'IP', 'urine', 'aspiration', 'vomit', 'NG', 'bowels', 'other'])],
            'prescription_id' => ['required_if:flow,intake', 'nullable', 'integer', 'exists:prescriptions,id'],
            'quantity_ml' => ['required', 'integer', 'min:1', 'max:100000'],
            'drops_per_minute' => ['nullable', 'integer', 'min:1', 'max:300'],
        ]);

        if ($data['flow'] === 'intake' && ! in_array($data['route_or_output'], ['oral', 'subcutaneous', 'gastric', 'drip', 'rectal', 'IV', 'IP'], true)) {
            throw ValidationException::withMessages(['route_or_output' => 'Choose an intake route for an intake entry.']);
        }

        if ($data['flow'] === 'output' && ! in_array($data['route_or_output'], ['urine', 'aspiration', 'vomit', 'NG', 'bowels', 'other'], true)) {
            throw ValidationException::withMessages(['route_or_output' => 'Choose an output type for an output entry.']);
        }

        $entry = $charts->recordFluid($admission, $data, $request->user());

        return ['id' => $entry->id, 'flow' => $entry->flow, 'quantity_ml' => $entry->quantity_ml, 'recorded_at' => $entry->recorded_at?->toIso8601String()];
    }

    public function oxygen(Request $request, Admission $admission, AdmissionCareChartService $charts): array
    {
        $data = $request->validate([
            'concentrator' => ['required', Rule::in(['yes', 'no'])],
            'flow_rate' => ['nullable', 'string', 'max:40'],
            'indication' => ['nullable', 'string', 'max:1000'],
        ]);
        $entry = $charts->recordOxygen($admission, $data, $request->user());

        return ['id' => $entry->id, 'concentrator' => $entry->concentrator, 'flow_rate' => $entry->flow_rate, 'administered_at' => $entry->administered_at?->toIso8601String()];
    }

    public function nebulizer(Request $request, Admission $admission, AdmissionCareChartService $charts): array
    {
        $use = $charts->recordNebulizerUse($admission, [], $request->user());

        return ['id' => $use->id, 'usage_number' => $use->usage_number, 'billable_block' => $use->billable_block, 'used_at' => $use->used_at?->toIso8601String()];
    }

    public function startInsulinMachineBlock(Request $request, Admission $admission, AdmissionCareChartService $charts): array
    {
        $block = $charts->startInsulinMachineBlock($admission, $request->user());

        return [
            'id' => $block->id,
            'block_number' => $block->block_number,
            'fee' => $block->fee,
            'payment_complete' => $block->payment_complete,
            'started_at' => $block->started_at?->toIso8601String(),
        ];
    }

    public function recordInsulinMachineUse(Request $request, Admission $admission, AdmissionCareChartService $charts): array
    {
        $data = $request->validate([
            'prescription_id' => ['required', 'integer', 'exists:prescriptions,id'],
            'bottle_label' => ['nullable', 'string', 'max:100'],
        ]);
        $use = $charts->recordInsulinMachineUse($admission, $data, $request->user());

        return ['id' => $use->id, 'block_id' => $use->block_id, 'prescription_id' => $use->prescription_id, 'bottle_label' => $use->bottle_label, 'opened_at' => $use->opened_at?->toIso8601String()];
    }

    public function dama(Request $request, Admission $admission, AdmissionCareChartService $charts): array
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
        $witnesses = collect($data['witnesses'])
            ->filter(fn ($witness) => filled($witness['name'] ?? null) && ($witness['image'] ?? null))
            ->map(fn ($witness) => ['name' => $witness['name'], 'image' => $witness['image']])
            ->values()
            ->all();
        $record = $charts->dischargeDama($admission, $data, $witnesses, $request->user());

        return [
            'id' => $record->id,
            'completed_at' => $record->completed_at?->toIso8601String(),
            'witnesses' => $record->witnesses->map(fn ($witness) => [
                'id' => $witness->id,
                'name' => $witness->name,
                'download_url' => route('api.v1.admissions.dama.witnesses.show', [$admission->id, $witness->id]),
            ])->values(),
        ];
    }

    public function damaRecord(Request $request, Admission $admission, AdmissionService $admissions): array
    {
        $admissions->showData($admission, $request->user());
        $record = $admission->damaRecord()->with('witnesses', 'clinician')->firstOrFail();

        return [
            'id' => $record->id,
            'patient_or_representative' => $record->patient_or_representative,
            'relationship' => $record->relationship,
            'reason' => $record->reason,
            'risks_explained' => $record->risks_explained,
            'follow_up_advice' => $record->follow_up_advice,
            'additional_notes' => $record->additional_notes,
            'completed_by' => $record->clinician?->name,
            'completed_at' => $record->completed_at?->toIso8601String(),
            'witnesses' => $record->witnesses->map(fn ($witness) => [
                'id' => $witness->id,
                'name' => $witness->name,
                'download_url' => route('api.v1.admissions.dama.witnesses.show', [$admission->id, $witness->id]),
            ])->values(),
        ];
    }

    public function damaWitness(Request $request, Admission $admission, int $witness, AdmissionService $admissions): Response
    {
        $admissions->showData($admission, $request->user());
        $witness = $admission->damaRecord()->firstOrFail()->witnesses()->findOrFail($witness);
        abort_unless(Storage::disk('local')->exists($witness->image_path), 404);

        return Storage::disk('local')->response($witness->image_path);
    }
}
