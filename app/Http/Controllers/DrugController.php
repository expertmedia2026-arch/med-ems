<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveDrugRequest;
use App\Models\Drug;
use App\Services\DrugCatalogService;

/**
 * Delegates medicine catalogue creation and maintenance to DrugCatalogService.
 */
class DrugController extends Controller
{
    public const FORMS = ['Tablet', 'Capsule', 'Syrup', 'Suspension', 'Injection', 'IV Fluid', 'Cream', 'Ointment', 'Drops', 'Sachet', 'Inhaler', 'Other'];

    public function create()
    {
        return view('pharmacy.drugs.create', ['forms' => self::FORMS]);
    }

    /**
     * Creates a medicine from validated catalogue input and redirects to the pharmacy inventory.
     */
    public function store(SaveDrugRequest $request, DrugCatalogService $catalog)
    {
        $drug = $catalog->create($request->validated());

        return redirect()
            ->route('pharmacy.inventory')
            ->with('success', "{$drug->display_name} added with {$drug->stock_quantity} in stock.");
    }

    public function edit(Drug $drug)
    {
        return view('pharmacy.drugs.edit', ['drug' => $drug, 'forms' => self::FORMS]);
    }

    /**
     * Updates a medicine from validated catalogue input and redirects to the pharmacy inventory.
     */
    public function update(SaveDrugRequest $request, Drug $drug, DrugCatalogService $catalog)
    {
        $drug = $catalog->update($drug, $request->validated());

        return redirect()->route('pharmacy.inventory')->with('success', "{$drug->display_name} updated.");
    }

    /**
     * Changes the medicine's active state and returns with a message describing its prescribing availability.
     */
    public function toggle(Drug $drug, DrugCatalogService $catalog)
    {
        $drug = $catalog->toggle($drug);

        return back()->with('success', $drug->name.($drug->is_active ? ' activated.' : ' deactivated. Doctors can no longer prescribe it.'));
    }
}
