<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteConsultationRequest;
use App\Http\Requests\ConsultationNotesRequest;
use App\Http\Requests\RecordVitalsRequest;
use App\Http\Requests\RequestLabTestsRequest;
use App\Http\Requests\StorePrescriptionRequest;
use App\Http\Resources\Api\V1\DrugResource;
use App\Http\Resources\Api\V1\LabTestResource;
use App\Http\Resources\Api\V1\VisitResource;
use App\Models\Drug;
use App\Models\LabRequest;
use App\Models\LabTest;
use App\Models\Prescription;
use App\Models\Visit;
use App\Services\BillingService;
use App\Services\ConsultationService;
use App\Services\LabWorkflowService;
use App\Services\PrescriptionService;
use App\Services\TriageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClinicalController extends Controller
{
    public function options(): array
    {
        return [
            'available_drugs' => DrugResource::collection(Drug::active()->where('stock_quantity', '>', 0)->orderBy('name')->get()),
            'lab_tests' => LabTestResource::collection(LabTest::active()->orderBy('name')->get()),
        ];
    }

    public function triageIndex(TriageService $triage): array
    {
        $data = $triage->indexData();

        return [
            'waiting' => VisitResource::collection($data['waiting']),
            'waiting_for_doctor' => VisitResource::collection($data['done']),
        ];
    }

    public function triageShow(Visit $visit, BillingService $billing, TriageService $triage): VisitResource
    {
        $triage->assertVitalsEditable($visit);
        abort_unless($billing->consultationPaid($visit), 402, 'The consultation charge must be paid before triage.');

        return new VisitResource($triage->loadVitalsData($visit));
    }

    public function triageStore(RecordVitalsRequest $request, Visit $visit, BillingService $billing, TriageService $triage): VisitResource
    {
        $triage->assertVitalsEditable($visit);
        abort_unless($billing->consultationPaid($visit), 402, 'The consultation charge must be paid before triage.');
        $triage->recordVitals($visit, $request->validated(), $request->user());

        return new VisitResource($triage->loadVitalsData($visit->refresh()));
    }

    public function consultationIndex(Request $request, ConsultationService $consultations): AnonymousResourceCollection
    {
        return VisitResource::collection($consultations->queueFor($request->user()));
    }

    public function consultationShow(Request $request, Visit $visit, ConsultationService $consultations): VisitResource
    {
        return new VisitResource($consultations->showData($visit, $request->user())['visit']);
    }

    public function consultationHistory(Visit $visit, ConsultationService $consultations): VisitResource
    {
        return new VisitResource($consultations->historyData($visit));
    }

    public function consultationStart(Request $request, Visit $visit, ConsultationService $consultations): VisitResource
    {
        $consultations->start($visit, $request->user());

        return new VisitResource($consultations->showData($visit->refresh(), $request->user())['visit']);
    }

    public function consultationUpdate(ConsultationNotesRequest $request, Visit $visit, ConsultationService $consultations): VisitResource
    {
        $consultations->saveNotes($visit, $request->validated(), $request->user());

        return new VisitResource($consultations->showData($visit->refresh(), $request->user())['visit']);
    }

    public function consultationComplete(CompleteConsultationRequest $request, Visit $visit, ConsultationService $consultations): VisitResource
    {
        $consultations->complete($visit, $request->validated(), $request->user());

        return new VisitResource($visit->refresh()->load(['patient', 'doctor', 'vitals']));
    }

    public function requestLabTests(RequestLabTestsRequest $request, Visit $visit, LabWorkflowService $lab): array
    {
        abort_unless($visit->isOpenFor($request->user()), 403);
        $count = $lab->requestTests($visit, $request->validated('lab_test_ids'), $request->user()->id);

        return ['requested' => $count, 'visit' => new VisitResource($visit->load('labRequests.test'))];
    }

    public function removeLabRequest(Request $request, Visit $visit, LabRequest $labRequest, LabWorkflowService $lab): array
    {
        abort_unless($visit->isOpenFor($request->user()), 403);
        abort_unless($labRequest->visit_id === $visit->id, 404);
        abort_unless($lab->removeRequest($labRequest, $request->user()), 422, 'This test has already been paid for and cannot be removed.');

        return ['message' => 'Lab request removed.'];
    }

    public function prescribe(StorePrescriptionRequest $request, Visit $visit, PrescriptionService $prescriptions): array
    {
        abort_unless($visit->isOpenFor($request->user()), 403);
        $result = $prescriptions->prescribe($visit, $request->validated(), $request->user()->id);
        abort_if($result['error'], 422, $result['error']);
        $prescription = $visit->prescriptions()->with('drug')->latest('id')->firstOrFail();

        return [
            'prescription' => [
                'id' => $prescription->id,
                'drug' => $prescription->drug->display_name,
                'dosage' => $prescription->dosage,
                'frequency' => $prescription->frequency,
                'duration' => $prescription->duration,
                'quantity' => $prescription->quantity,
            ],
            'visit' => new VisitResource($visit->load('prescriptions.drug', 'prescriptions.invoiceItem')),
        ];
    }

    public function removePrescription(Request $request, Visit $visit, Prescription $prescription, PrescriptionService $prescriptions): array
    {
        abort_unless($visit->isOpenFor($request->user()), 403);
        abort_unless($prescriptions->remove($visit, $prescription), 422, 'This prescription has already been paid for and cannot be removed.');

        return ['message' => 'Prescription removed.'];
    }
}
