<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMedicationAdministrationRequest;
use App\Http\Requests\WaiveMedicationAdministrationRequest;
use App\Http\Resources\Api\V1\VisitResource;
use App\Models\Prescription;
use App\Models\Visit;
use App\Services\MedicationAdministrationService;
use Illuminate\Http\Request;

class NursingAdministrationController extends Controller
{
    public function index(Request $request, MedicationAdministrationService $administrations): array
    {
        return ['visits' => VisitResource::collection($administrations->queue($request->user()))];
    }

    public function show(Request $request, Visit $visit, MedicationAdministrationService $administrations): VisitResource
    {
        return new VisitResource($administrations->visitDetails($visit, $request->user()));
    }

    public function store(StoreMedicationAdministrationRequest $request, Visit $visit, Prescription $prescription, MedicationAdministrationService $administrations): array
    {
        $record = $administrations->record($visit, $prescription, $request->validated(), $request->user());

        return [
            'message' => 'Medication administration outcome recorded.',
            'administration' => [
                'id' => $record->id,
                'prescription_id' => $record->prescription_id,
                'status' => $record->status,
                'administered_at' => $record->administered_at?->toIso8601String(),
            ],
            'visit' => new VisitResource($administrations->visitDetails($visit, $request->user())),
        ];
    }

    public function waive(WaiveMedicationAdministrationRequest $request, Visit $visit, Prescription $prescription, MedicationAdministrationService $administrations): array
    {
        $prescription = $administrations->waive($visit, $prescription, $request->validated('reason'), $request->user());

        return [
            'message' => 'Administration requirement waived.',
            'prescription_id' => $prescription->id,
            'waived_at' => $prescription->administration_waived_at?->toIso8601String(),
            'waiver_reason' => $prescription->administration_waiver_reason,
        ];
    }

    public function discharge(Request $request, Visit $visit, MedicationAdministrationService $administrations): array
    {
        $visit = $administrations->discharge($visit, $request->user());

        return [
            'message' => 'Visit discharged.',
            'visit' => new VisitResource($administrations->visitDetails($visit, $request->user())),
        ];
    }
}
