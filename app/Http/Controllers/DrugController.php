<?php

namespace App\Http\Controllers;

use App\Models\Drug;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DrugController extends Controller
{
    public const FORMS = ['Tablet', 'Capsule', 'Syrup', 'Suspension', 'Injection', 'Cream', 'Ointment', 'Drops', 'Sachet', 'Inhaler', 'Other'];

    public function create()
    {
        return view('pharmacy.drugs.create', ['forms' => self::FORMS]);
    }

    public function store(Request $request)
    {
        $drug = Drug::create($this->validated($request) + ['is_active' => true]);

        return redirect()
            ->route('pharmacy.inventory')
            ->with('success', "{$drug->display_name} added with {$drug->stock_quantity} in stock.");
    }

    public function edit(Drug $drug)
    {
        return view('pharmacy.drugs.edit', ['drug' => $drug, 'forms' => self::FORMS]);
    }

    public function update(Request $request, Drug $drug)
    {
        $drug->update($this->validated($request, $drug));

        return redirect()->route('pharmacy.inventory')->with('success', "{$drug->display_name} updated.");
    }

    public function toggle(Drug $drug)
    {
        $drug->update(['is_active' => ! $drug->is_active]);

        return back()->with('success', $drug->name . ($drug->is_active ? ' activated.' : ' deactivated. Doctors can no longer prescribe it.'));
    }

    private function validated(Request $request, ?Drug $drug = null): array
    {
        $rules = [
            'name'          => [
                'required', 'string', 'max:150',
                // Same name is fine as long as strength + form differ (e.g. Paracetamol 500mg vs 1g)
                Rule::unique('drugs', 'name')
                    ->where(fn ($q) => $q->where('strength', $request->input('strength'))
                                         ->where('form', $request->input('form')))
                    ->ignore($drug?->id),
            ],
            'generic_name'  => ['nullable', 'string', 'max:150'],
            'form'          => ['nullable', 'string', 'max:30'],
            'strength'      => ['nullable', 'string', 'max:30'],
            'unit_price'    => ['required', 'numeric', 'min:0', 'max:99999999'],
            'reorder_level' => ['required', 'integer', 'min:0', 'max:100000'],
        ];

        // Stock is set once at creation; after that it changes through dispensing and restocking
        if (! $drug) {
            $rules['stock_quantity'] = ['required', 'integer', 'min:0', 'max:1000000'];
        }

        return $request->validate($rules, [
            'name.unique' => 'This drug already exists with the same strength and form.',
        ]);
    }
}