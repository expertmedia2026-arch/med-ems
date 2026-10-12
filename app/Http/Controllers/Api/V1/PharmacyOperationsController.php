<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DispensePrescriptionsRequest;
use App\Http\Requests\PharmacyInventoryRequest;
use App\Http\Requests\RestockDrugRequest;
use App\Http\Requests\SaveDrugRequest;
use App\Http\Resources\Api\V1\DrugResource;
use App\Http\Resources\Api\V1\VisitResource;
use App\Models\Drug;
use App\Models\Visit;
use App\Services\DrugCatalogService;
use App\Services\PharmacyService;

class PharmacyOperationsController extends Controller
{
    public function dashboard(PharmacyService $service): array
    {
        $data = $service->dashboardData();

        return [
            'queue' => VisitResource::collection($data['queue']),
            'low_stock' => DrugResource::collection($data['lowStock']),
            'dispensed_today' => $data['dispensedToday']->map(fn ($prescription) => [
                'prescription_id' => $prescription->id,
                'drug' => $prescription->drug?->display_name,
                'patient' => $prescription->visit?->patient?->full_name,
                'quantity' => $prescription->dispensable_quantity ?? $prescription->quantity,
                'prescribed_quantity' => $prescription->quantity,
                'dispensed_at' => $prescription->dispensed_at?->toIso8601String(),
            ])->values(),
        ];
    }

    public function inventory(PharmacyInventoryRequest $request, PharmacyService $service)
    {
        return DrugResource::collection($service->inventoryData($request->validated('q'), (bool) $request->validated('low')));
    }

    public function show(Visit $visit, PharmacyService $service): VisitResource
    {
        return new VisitResource($service->visitDetails($visit));
    }

    public function dispense(DispensePrescriptionsRequest $request, Visit $visit, PharmacyService $service): array
    {
        $result = $service->dispense($visit, $request->validated('prescription_ids'), $request->user()->id);

        return $result + ['visit' => new VisitResource($service->visitDetails($visit))];
    }

    public function restock(RestockDrugRequest $request, Drug $drug, PharmacyService $service): DrugResource
    {
        return new DrugResource($service->restock($drug, $request->validated('quantity'), $request->user()->id));
    }

    public function createDrug(SaveDrugRequest $request, DrugCatalogService $catalog): DrugResource
    {
        return new DrugResource($catalog->create($request->validated()));
    }

    public function updateDrug(SaveDrugRequest $request, Drug $drug, DrugCatalogService $catalog): DrugResource
    {
        return new DrugResource($catalog->update($drug, $request->validated()));
    }

    public function toggleDrug(Drug $drug, DrugCatalogService $catalog): DrugResource
    {
        return new DrugResource($catalog->toggle($drug));
    }
}
