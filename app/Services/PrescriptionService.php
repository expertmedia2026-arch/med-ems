<?php

namespace App\Services;

use App\Enums\WorkStatus;
use App\Models\Drug;
use App\Models\Prescription;
use App\Models\Visit;

class PrescriptionService
{
    public function __construct(private readonly BillingService $billing) {}

    /** @return array{drug: Drug|null, error: string|null} */
    public function prescribe(Visit $visit, array $data, int $doctorId): array
    {
        $drug = Drug::findOrFail($data['drug_id']);
        $allergies = strtolower($visit->patient->allergies ?? '');
        $matches = collect([$drug->name, $drug->generic_name])->filter()
            ->contains(fn ($name) => $allergies !== '' && str_contains($allergies, strtolower($name)));

        if ($matches) {
            return ['drug' => $drug, 'error' => "Allergy alert: patient has a recorded allergy that matches {$drug->name}."];
        }

        if ($data['quantity'] > $drug->stock_quantity) {
            return ['drug' => $drug, 'error' => "Only {$drug->stock_quantity} in stock."];
        }

        $visit->prescriptions()->create($data + ['prescribed_by' => $doctorId]);
        $this->billing->syncFor($visit);

        return ['drug' => $drug, 'error' => null];
    }

    public function remove(Visit $visit, Prescription $prescription): bool
    {
        abort_unless($prescription->visit_id === $visit->id, 404);
        abort_unless($prescription->status === WorkStatus::Pending, 422);

        if (! $this->billing->release($prescription)) {
            return false;
        }

        $prescription->delete();

        return true;
    }
}
