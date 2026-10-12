<?php

namespace App\Http\Controllers;

use App\Http\Requests\DispensePrescriptionsRequest;
use App\Http\Requests\PharmacyInventoryRequest;
use App\Http\Requests\RestockDrugRequest;
use App\Models\Drug;
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

        $message = "{$result['dispensed']} item(s) dispensed for {$result['patient_name']}.";
        if ($result['nursing_ready'] > 0) {
            $message .= " {$result['nursing_ready']} hospital administration task(s) sent to the nursing queue.";
        }

        return $redirect->with('success', $message);
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
        $drug = $pharmacy->restock($drug, $quantity, $request->user()->id);

        return back()->with('success', "Added {$quantity} to {$drug->name}. On hand: {$drug->stock_quantity}; available: {$drug->available_quantity}.");
    }
}
