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
    public function __construct(private readonly DrugInventoryService $inventory) {}

    public function dashboardData(): array
    {
        $this->inventory->expireReservations();

        return [
            'queue' => Visit::with([
                'patient',
                'prescriptions' => fn ($query) => $query->pending()->with(['drug', 'invoiceItem', 'stockReservation']),
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

        $this->inventory->expireReservations();
        $visit->loadMissing(['prescriptions' => fn ($query) => $query->pending()->with('invoiceItem')]);
        foreach ($visit->prescriptions as $prescription) {
            if ($prescription->is_paid) {
                $this->inventory->ensurePrescriptionReserved($prescription, null);
            }
        }

        return $visit->load(['patient', 'doctor', 'prescriptions.drug', 'prescriptions.dispenser', 'prescriptions.invoiceItem', 'prescriptions.stockReservation']);
    }

    public function inventoryData(?string $term, bool $lowStock)
    {
        $this->inventory->expireReservations();

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
            $items = $visit->prescriptions()->pending()->with(['invoiceItem', 'drug', 'stockReservation'])
                ->whereIn('id', array_unique($prescriptionIds))->orderBy('drug_id')->get();

            foreach ($items as $prescription) {
                if (! $prescription->is_paid) {
                    $skipped[] = "{$prescription->drug->name} (not paid)";

                    continue;
                }

                if (! $this->inventory->ensurePrescriptionReserved($prescription, null)) {
                    $drug = Drug::findOrFail($prescription->drug_id);
                    $skipped[] = "{$drug->name} (reserved stock expired and only {$drug->available_quantity} is now available)";

                    continue;
                }

                if (! $this->inventory->fulfillPrescription($prescription, $userId)) {
                    $skipped[] = "{$prescription->drug->name} (reserved stock is no longer available)";

                    continue;
                }

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

    public function restock(Drug $drug, int $quantity, int $userId): Drug
    {
        return $this->inventory->restock($drug, $quantity, $userId);
    }
}
