<?php

namespace App\Http\Controllers;

use App\Enums\VisitStatus;
use App\Enums\WorkStatus;
use App\Models\Drug;
use App\Models\Prescription;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PharmacyController extends Controller
{
    public function index()
    {
        return view('pharmacy.index', [
            'queue' => Visit::with([
                    'patient',
                    'prescriptions' => fn ($q) => $q->pending()->with(['drug', 'invoiceItem']),
                ])
                ->withStatus(VisitStatus::Completed)
                ->whereHas('prescriptions', fn ($q) => $q->pending())
                ->oldest('closed_at')
                ->get(),

            'lowStock' => Drug::lowStock()->orderBy('stock_quantity')->get(),

            'dispensedToday' => Prescription::with(['visit.patient', 'drug', 'dispenser'])
                ->completed()
                ->whereDate('dispensed_at', today())
                ->latest('dispensed_at')
                ->limit(15)
                ->get(),
        ]);
    }

    public function show(Visit $visit)
    {
        abort_unless($visit->status === VisitStatus::Completed, 404);

        $visit->load(['patient', 'doctor', 'prescriptions.drug', 'prescriptions.dispenser', 'prescriptions.invoiceItem']);

        return view('pharmacy.show', compact('visit'));
    }

    public function dispense(Request $request, Visit $visit)
    {
        abort_unless($visit->status === VisitStatus::Completed, 403);

        $data = $request->validate([
            'prescription_ids'   => ['required', 'array', 'min:1'],
            'prescription_ids.*' => ['integer'],
        ], ['prescription_ids.required' => 'Select at least one item to dispense.']);

        $dispensed = 0;
        $skipped   = [];

        DB::transaction(function () use ($visit, $data, $request, &$dispensed, &$skipped) {
            $items = $visit->prescriptions()
                ->pending()
                ->with(['invoiceItem', 'drug'])
                ->whereIn('id', $data['prescription_ids'])
                ->get();

            foreach ($items as $rx) {
                if (! $rx->is_paid) {
                    $skipped[] = "{$rx->drug->name} (not paid)";
                    continue;
                }

                // Re-check stock inside the transaction; it may have changed since the page loaded
                $drug = Drug::lockForUpdate()->find($rx->drug_id);

                if ($drug->stock_quantity < $rx->quantity) {
                    $skipped[] = "{$drug->name} (need {$rx->quantity}, have {$drug->stock_quantity})";
                    continue;
                }

                $drug->decrement('stock_quantity', $rx->quantity);

                $rx->update([
                    'status'       => WorkStatus::Completed,
                    'dispensed_by' => $request->user()->id,
                    'dispensed_at' => now(),
                ]);

                $dispensed++;
            }
        });

        $redirect = $visit->prescriptions()->pending()->exists()
            ? redirect()->route('pharmacy.show', $visit)
            : redirect()->route('pharmacy.index');

        if ($skipped) {
            $redirect->with('error', 'Not dispensed: ' . implode(', ', $skipped));
        }

        return $redirect->with('success', "{$dispensed} item(s) dispensed for {$visit->patient->full_name}.");
    }

    public function inventory(Request $request)
    {
        $drugs = Drug::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->query('q');
                $q->where(fn ($q) => $q->whereLike('name', "%{$term}%")
                                       ->orWhereLike('generic_name', "%{$term}%"));
            })
            ->when($request->boolean('low'), fn ($q) => $q->lowStock())
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('pharmacy.inventory', compact('drugs'));
    }

    public function restock(Request $request, Drug $drug)
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);

        $drug->increment('stock_quantity', $data['quantity']);

        return back()->with('success', "Added {$data['quantity']} to {$drug->name}. New stock: {$drug->stock_quantity}.");
    }
}
