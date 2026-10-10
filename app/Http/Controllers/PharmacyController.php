<?php

namespace App\Http\Controllers;

use App\Http\Requests\DispensePrescriptionsRequest;
use App\Http\Requests\PharmacyInventoryRequest;
use App\Http\Requests\RestockDrugRequest;
use App\Models\Visit;
use App\Services\PharmacyService;

class PharmacyController extends Controller
{
    public function index(PharmacyService $pharmacy)
    {
        return view('pharmacy.index', $pharmacy->dashboardData());
    }

    public function show(Visit $visit, PharmacyService $pharmacy)
    {
        $visit = $pharmacy->visitDetails($visit);

        return view('pharmacy.show', compact('visit'));
    }

    public function dispense(DispensePrescriptionsRequest $request, Visit $visit, PharmacyService $pharmacy)
    {
        $result = $pharmacy->dispense($visit, $request->validated('prescription_ids'), $request->user()->id);

        $redirect = $result['has_pending']
            ? redirect()->route('pharmacy.show', $visit)
            : redirect()->route('pharmacy.index');

        if ($result['skipped']) {
            $redirect->with('error', 'Not dispensed: '.implode(', ', $result['skipped']));
        }

        return $redirect->with('success', "{$result['dispensed']} item(s) dispensed for {$result['patient_name']}.");
    }

    public function inventory(PharmacyInventoryRequest $request, PharmacyService $pharmacy)
    {
        $data = $request->validated();
        $drugs = $pharmacy->inventoryData($data['q'] ?? null, (bool) ($data['low'] ?? false));

        return view('pharmacy.inventory', compact('drugs'));
    }

    public function restock(RestockDrugRequest $request, Drug $drug, PharmacyService $pharmacy)
    {
        $quantity = $request->validated('quantity');
        $drug = $pharmacy->restock($drug, $quantity);

        return back()->with('success', "Added {$quantity} to {$drug->name}. New stock: {$drug->stock_quantity}.");
    }
}
