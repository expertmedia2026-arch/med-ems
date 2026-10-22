<?php

namespace App\Http\Controllers;

use App\Models\EmergencyPharmacyItem;
use App\Models\Visit;
use App\Services\EmergencyPharmacyService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmergencyPharmacyController extends Controller
{
    public function index(Request $request, EmergencyPharmacyService $pharmacy)
    {
        return view('emergency.pharmacy.index', $pharmacy->indexData($request->user()));
    }

    public function storeItem(Request $request, EmergencyPharmacyService $pharmacy)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(['medicine', 'injection', 'consumable', 'equipment', 'other'])],
            'unit' => ['required', 'string', 'max:40'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'reorder_level' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);
        $pharmacy->addItem($data, $request->user());

        return back()->with('success', 'A&E stock item added.');
    }

    public function restock(EmergencyPharmacyItem $emergencyPharmacyItem, Request $request, EmergencyPharmacyService $pharmacy)
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:1000000'], 'notes' => ['nullable', 'string', 'max:500']]);
        $pharmacy->restock($emergencyPharmacyItem, (int) $data['quantity'], $request->user(), $data['notes'] ?? null);

        return back()->with('success', 'A&E stock quantity updated.');
    }

    public function update(EmergencyPharmacyItem $emergencyPharmacyItem, Request $request, EmergencyPharmacyService $pharmacy)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(['medicine', 'injection', 'consumable', 'equipment', 'other'])],
            'unit' => ['required', 'string', 'max:40'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'reorder_level' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);
        $pharmacy->updateItem($emergencyPharmacyItem, $data, $request->user());

        return back()->with('success', 'A&E item details and price updated.');
    }

    public function toggle(EmergencyPharmacyItem $emergencyPharmacyItem, Request $request, EmergencyPharmacyService $pharmacy)
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $pharmacy->setActive($emergencyPharmacyItem, (bool) $data['is_active'], $request->user());

        return back()->with('success', $data['is_active'] ? 'A&E item activated.' : 'A&E item deactivated.');
    }

    public function recordUse(Visit $visit, Request $request, EmergencyPharmacyService $pharmacy)
    {
        $data = $request->validate([
            'emergency_pharmacy_item_id' => ['required', Rule::exists('emergency_pharmacy_items', 'id')->where('is_active', true)],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'dose_or_amount' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $pharmacy->recordUse($visit, $data, $request->user());

        return back()->with('success', 'A&E item use recorded and added to the patient invoice.');
    }
}
