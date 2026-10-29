<?php

namespace App\Services;

use App\Enums\WorkStatus;
use App\Enums\VisitStatus;
use App\Models\Drug;
use App\Models\Prescription;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;

/**
 * Creates, removes, and forwards prescriptions to the pharmacy workflow.
 *
 * Drug stock and prescription rows are locked or reserved within transactions to
 * avoid concurrent over-allocation. Billing changes are synchronized alongside
 * prescription creation or removal.
 */
class PrescriptionService
{
    /**
     * @param  BillingService  $billing  Synchronizes invoice items and releases related billing state.
     * @param  DrugInventoryService  $inventory  Locks drugs and manages stock reservations.
     */
    public function __construct(
        private readonly BillingService $billing,
        private readonly DrugInventoryService $inventory,
    ) {}

    /**
     * Validate allergies and stock, create a prescription, reserve quantity, and sync billing.
     *
     * The drug row is locked during the transaction. An allergy match or empty stock
     * returns an error without creating a prescription. For successful in-facility
     * administration orders, nurses are notified after commit.
     *
     * @param  Visit  $visit  Visit receiving the prescription.
     * @param  array<string, mixed>  $data  Validated prescription fields, including drug ID and requested quantity.
     * @param  int  $doctorId  Prescribing doctor's user ID.
     * @return array{drug: Drug|null, error: string|null, reserved_quantity?: int, shortage?: int} Prescription outcome and reservation details when created.
     */
    public function prescribe(Visit $visit, array $data, int $doctorId): array
    {
        $result = DB::transaction(function () use ($visit, $data, $doctorId) {
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
            $data['required_administration_count'] = ($data['requires_in_facility_administration'] ?? false)
                ? (int) ($data['required_administration_count'] ?? 1)
                : 1;
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

        if (! $result['error'] && ($data['requires_in_facility_administration'] ?? false)) {
            $visit->loadMissing('patient');
            DB::afterCommit(function () use ($visit, $result) {
                app(WorkflowNotificationService::class)->medicationNursesFor(
                    $visit,
                    'New medication administration order',
                    "Drugs requiring administration at the hospital were ordered for {$visit->patient->full_name} ({$visit->patient->hospital_number}). {$result['drug']->display_name} is awaiting payment and pharmacy dispensing.",
                );
            });
        }

        return $result;
    }

    /**
     * Remove a pending prescription and release its associated invoice and stock reservation.
     *
     * The drug and prescription are locked using the shared inventory lock order.
     * Prescriptions already processed, ordered by pharmacy, or attached to another
     * visit are rejected; false indicates billing could not be released.
     *
     * @param  Visit  $visit  Visit that must own the prescription.
     * @param  Prescription  $prescription  Pending prescription to remove.
     * @return bool Whether removal and reservation release completed.
     */
    public function remove(Visit $visit, Prescription $prescription): bool
    {
        return DB::transaction(function () use ($visit, $prescription) {
            // Keep the same drug → reservation → prescription lock order used by
            // payment and dispensing so concurrent updates do not deadlock.
            $this->inventory->lockDrug((int) $prescription->drug_id);
            $prescription = Prescription::query()->lockForUpdate()->findOrFail($prescription->id);
            abort_unless($prescription->visit_id === $visit->id, 404);
            abort_unless($prescription->status === WorkStatus::Pending, 422);
            abort_unless($prescription->pharmacy_ordered_at === null, 422, 'This prescription has already been sent to pharmacy and cannot be removed here.');

            if (! $this->billing->release($prescription)) {
                return false;
            }

            $this->inventory->releasePrescription($prescription, 'prescription_removed');
            $prescription->delete();

            return true;
        });
    }

    /**
     * Mark all pending, not-yet-ordered prescriptions for the doctor's consultation as ordered.
     *
     * The visit is row-locked and must still be in consultation and assigned to the
     * supplied doctor.
     *
     * @param  Visit  $visit  Consultation whose prescriptions should be sent to pharmacy.
     * @param  int  $doctorId  Doctor ID that must own the active consultation.
     * @return int Number of prescription rows marked as ordered.
     */
    public function placeOrder(Visit $visit, int $doctorId): int
    {
        return DB::transaction(function () use ($visit, $doctorId) {
            $visit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            abort_unless($visit->status === VisitStatus::InConsultation && $visit->doctor_id === $doctorId, 403, 'This consultation is not open for ordering.');

            return $visit->prescriptions()
                ->where('status', WorkStatus::Pending->value)
                ->whereNull('pharmacy_ordered_at')
                ->update(['pharmacy_ordered_at' => now()]);
        });
    }
}
