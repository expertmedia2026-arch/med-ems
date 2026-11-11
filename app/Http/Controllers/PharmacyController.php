<?php

namespace App\Http\Controllers;

use App\Http\Requests\DispensePrescriptionsRequest;
use App\Http\Requests\PharmacyInventoryRequest;
use App\Http\Requests\RestockDrugRequest;
use App\Models\Drug;
use App\Models\Visit;
use App\Services\PharmacyService;
use Illuminate\Http\Request;

/**
 * Coordinates pharmacy queue, inventory, and dispensing pages with PharmacyService.
 */
class PharmacyController extends Controller
{
    /**
     * Loads pharmacy dashboard data and renders the dispensing queue.
     */
    public function index(PharmacyService $pharmacy)
    {
        return view('pharmacy.index', $pharmacy->dashboardData());
    }

    /**
     * Uses the requested audit period to retrieve and render pharmacy audit data.
     */
    public function audit(Request $request, PharmacyService $pharmacy)
    {
        return view('pharmacy.audit', $pharmacy->auditData((string) $request->query('period', 'month')));
    }

    /**
     * Loads authorized visit prescription details and renders the pharmacy dispensing page.
     */
    public function show(Visit $visit, PharmacyService $pharmacy)
    {
        $visit = $pharmacy->visitDetails($visit);

        return view('pharmacy.show', compact('visit'));
    }

    /**
     * Dispenses validated prescription selections and redirects with stock and nursing handoff outcomes.
     */
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

    /**
     * Uses validated inventory filters to retrieve and render pharmacy stock.
     */
    public function inventory(PharmacyInventoryRequest $request, PharmacyService $pharmacy)
    {
        $data = $request->validated();
        $drugs = $pharmacy->inventoryData(
            $data['q'] ?? null,
            (bool) ($data['low'] ?? false),
            $request->user()->hospital?->isStandalonePharmacy() === true,
        );

        return view('pharmacy.inventory', compact('drugs'));
    }

    /**
     * Restocks the selected drug with the validated quantity and returns with the updated balance.
     */
    public function restock(RestockDrugRequest $request, Drug $drug, PharmacyService $pharmacy)
    {
        $data = $request->validated();
        $quantity = $data['quantity'];
        $batch = isset($data['expiry_date']) ? [
            'expiry_date' => $data['expiry_date'],
            'batch_number' => $data['batch_number'] ?? null,
        ] : null;
        $drug = $pharmacy->restock($drug, $quantity, $request->user()->id, $batch);

        return back()->with('success', "Added {$quantity} to {$drug->name}. On hand: {$drug->stock_quantity}; available: {$drug->available_quantity}.");
    }
}
