<?php

namespace App\Services;

use App\Enums\WorkStatus;
use App\Models\Drug;
use App\Models\Prescription;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;

class PrescriptionService
{
    public function __construct(
        private readonly BillingService $billing,
        private readonly DrugInventoryService $inventory,
    ) {}

    /** @return array{drug: Drug|null, error: string|null, reserved_quantity?: int, shortage?: int} */
    public function prescribe(Visit $visit, array $data, int $doctorId): array
    {
        return DB::transaction(function () use ($visit, $data, $doctorId) {
            $drug = $this->inventory->lockDrug((int) $data['drug_id']);
            $allergies = strtolower($visit->patient->allergies ?? '');
            $matches = collect([$drug->name, $drug->generic_name])->filter()
                ->contains(fn ($name) => $allergies !== '' && str_contains($allergies, strtolower($name)));

            if ($matches) {
                return ['drug' => $drug, 'error' => "Allergy alert: patient has a recorded allergy that matches {$drug->name}."];
            }

            if ($drug->available_quantity < 1) {
                return ['drug' => $drug, 'error' => "No unreserved stock of {$drug->name} is available."];
            }

            $reservedQuantity = min((int) $data['quantity'], $drug->available_quantity);
            $prescription = $visit->prescriptions()->create($data + [
                'dispensable_quantity' => $reservedQuantity,
                'prescribed_by' => $doctorId,
            ]);
            $expiresAt = now()->addMinutes((int) config('hms.pharmacy.unpaid_reservation_minutes', 120));
            $this->inventory->createReservation($drug, $prescription, $reservedQuantity, $expiresAt);
            $this->billing->syncFor($visit);

            return [
                'drug' => $drug,
                'error' => null,
                'reserved_quantity' => $reservedQuantity,
                'shortage' => (int) $data['quantity'] - $reservedQuantity,
            ];
        });
    }

    public function remove(Visit $visit, Prescription $prescription): bool
    {
        return DB::transaction(function () use ($visit, $prescription) {
            // Keep the same drug → reservation → prescription lock order used by
            // payment and dispensing so concurrent updates do not deadlock.
            $this->inventory->lockDrug((int) $prescription->drug_id);
            $prescription = Prescription::query()->lockForUpdate()->findOrFail($prescription->id);
            abort_unless($prescription->visit_id === $visit->id, 404);
            abort_unless($prescription->status === WorkStatus::Pending, 422);

            if (! $this->billing->release($prescription)) {
                return false;
            }

            $this->inventory->releasePrescription($prescription, 'prescription_removed');
            $prescription->delete();

            return true;
        });
    }
}
