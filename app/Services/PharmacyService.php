<?php

namespace App\Services;

use App\Enums\VisitStatus;
use App\Enums\WorkStatus;
use App\Models\Drug;
use App\Models\Prescription;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;

class PharmacyService
{
    public function dashboardData(): array
    {
        return [
            'queue' => Visit::with([
                'patient',
                'prescriptions' => fn ($query) => $query->pending()->with(['drug', 'invoiceItem']),
            ])->withStatus(VisitStatus::Completed)
                ->whereHas('prescriptions', fn ($query) => $query->pending())->oldest('closed_at')->get(),
            'lowStock' => Drug::lowStock()->orderBy('stock_quantity')->get(),
            'dispensedToday' => Prescription::with(['visit.patient', 'drug', 'dispenser'])->completed()
                ->whereDate('dispensed_at', today())->latest('dispensed_at')->limit(15)->get(),
        ];
    }

    public function visitDetails(Visit $visit): Visit
    {
        abort_unless($visit->status === VisitStatus::Completed, 404);

        return $visit->load(['patient', 'doctor', 'prescriptions.drug', 'prescriptions.dispenser', 'prescriptions.invoiceItem']);
    }

    public function inventoryData(?string $term, bool $lowStock)
    {
        return Drug::query()->when($term, fn ($query) => $query->where(fn ($search) => $search
            ->whereLike('name', "%{$term}%")->orWhereLike('generic_name', "%{$term}%")))
            ->when($lowStock, fn ($query) => $query->lowStock())->orderBy('name')->paginate(15)->withQueryString();
    }

    /** @return array{dispensed: int, skipped: array<int, string>} */
    public function dispense(Visit $visit, array $prescriptionIds, int $userId): array
    {
        abort_unless($visit->status === VisitStatus::Completed, 403);
        $dispensed = 0;
        $skipped = [];

        DB::transaction(function () use ($visit, $prescriptionIds, $userId, &$dispensed, &$skipped) {
            $items = $visit->prescriptions()->pending()->with(['invoiceItem', 'drug'])
                ->whereIn('id', array_unique($prescriptionIds))->get();

            foreach ($items as $prescription) {
                if (! $prescription->is_paid) {
                    $skipped[] = "{$prescription->drug->name} (not paid)";

                    continue;
                }

                $drug = Drug::lockForUpdate()->findOrFail($prescription->drug_id);
                if ($drug->stock_quantity < $prescription->quantity) {
                    $skipped[] = "{$drug->name} (need {$prescription->quantity}, have {$drug->stock_quantity})";

                    continue;
                }

                $drug->decrement('stock_quantity', $prescription->quantity);
                $prescription->update([
                    'status' => WorkStatus::Completed,
                    'dispensed_by' => $userId,
                    'dispensed_at' => now(),
                ]);
                $dispensed++;
            }
        });

        return [
            'dispensed' => $dispensed,
            'skipped' => $skipped,
            'has_pending' => $visit->prescriptions()->pending()->exists(),
            'patient_name' => $visit->patient->full_name,
        ];
    }

    public function restock(Drug $drug, int $quantity): Drug
    {
        $drug->increment('stock_quantity', $quantity);

        return $drug->refresh();
    }
}
