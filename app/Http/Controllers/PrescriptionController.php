<?php

namespace App\Http\Controllers;

use App\Enums\WorkStatus;
use App\Models\Drug;
use App\Models\Prescription;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Services\BillingService;

class PrescriptionController extends Controller
{
    public function store(Request $request, Visit $visit)
    {
        abort_unless($visit->isOpenFor($request->user()), 403);

        $data = $request->validate([
            'drug_id' => ['required', Rule::exists('drugs', 'id')->where('is_active', true)],            'dosage'       => ['required', 'string', 'max:100'],
            'frequency'    => ['required', 'string', 'max:100'],
            'duration'     => ['required', 'string', 'max:100'],
            'quantity'     => ['required', 'integer', 'min:1', 'max:1000'],
            'instructions' => ['nullable', 'string', 'max:255'],
        ]);

        $drug = Drug::findOrFail($data['drug_id']);

        // Allergy check: matches the recorded allergy text against the drug's brand/generic name
        $allergies = strtolower($visit->patient->allergies ?? '');
        $matches   = collect([$drug->name, $drug->generic_name])
            ->filter()
            ->contains(fn ($name) => $allergies !== '' && str_contains($allergies, strtolower($name)));

        if ($matches) {
            return back()->withInput()->withErrors([
                'drug_id' => "Allergy alert: patient has a recorded allergy that matches {$drug->name}.",
            ]);
        }

        if ($data['quantity'] > $drug->stock_quantity) {
            return back()->withInput()->withErrors([
                'quantity' => "Only {$drug->stock_quantity} in stock.",
            ]);
        }

        $visit->prescriptions()->create($data + ['prescribed_by' => $request->user()->id]);
        app(BillingService::class)->syncFor($visit);

        return back()->with('success', "{$drug->name} added to the prescription.");
    }

    public function destroy(Request $request, Visit $visit, Prescription $prescription)
    {
        abort_unless($visit->isOpenFor($request->user()), 403);
        abort_unless($prescription->status === WorkStatus::Pending, 422);
        
        if (! app(BillingService::class)->release($prescription)) {
            return back()->with('error', 'This drug has already been paid for and cannot be removed.');
        }
        
        $prescription->delete();

        return back()->with('success', 'Prescription removed.');
    }
}