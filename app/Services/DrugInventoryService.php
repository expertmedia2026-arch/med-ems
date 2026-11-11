<?php

namespace App\Services;

use App\Models\Drug;
use App\Models\DrugStockBatch;
use App\Models\DrugStockMovement;
use App\Models\Prescription;
use App\Models\PrescriptionStockReservation;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Serializes drug-stock reservations and records auditable stock changes.
 */
class DrugInventoryService
{
    /**
     * Lock a drug row and expire any timed-out reservations before returning its refreshed stock.
     *
     * Must be called inside a transaction; the caller retains responsibility for committing it.
     */
    public function lockDrug(int $drugId): Drug
    {
        $drug = Drug::query()->lockForUpdate()->findOrFail($drugId);
        $this->expireForLockedDrug($drug);

        return $drug->refresh();
    }

    /**
     * Increase reserved stock and create a prescription reservation.
     *
     * The caller must hold the drug row lock inside a transaction and ensure sufficient available
     * stock before invoking this operation.
     */
    public function createReservation(Drug $drug, Prescription $prescription, int $quantity, ?CarbonInterface $expiresAt): PrescriptionStockReservation
    {
        $drug->reserved_quantity += $quantity;
        $drug->save();

        return $prescription->stockReservation()->create([
            'visit_id' => $prescription->visit_id,
            'drug_id' => $drug->id,
            'quantity' => $quantity,
            'status' => 'reserved',
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Ensure a prescription has a live reservation for its billable quantity.
     *
     * Locks the drug and reservation records, reuses/extends a valid hold or reacquires stock when
     * needed, and returns false when quantity is invalid or unavailable. Changes are transactional.
     */
    public function ensurePrescriptionReserved(Prescription $prescription, ?CarbonInterface $expiresAt): bool
    {
        return DB::transaction(function () use ($prescription, $expiresAt) {
            $drug = $this->lockDrug((int) $prescription->drug_id);
            $reservation = PrescriptionStockReservation::query()
                ->where('prescription_id', $prescription->id)
                ->lockForUpdate()
                ->first();

            if ($reservation?->status === 'reserved') {
                if ($expiresAt === null || $reservation->expires_at === null || $reservation->expires_at->lt(now())) {
                    $reservation->update(['expires_at' => $expiresAt]);
                } elseif ($expiresAt->gt($reservation->expires_at)) {
                    $reservation->update(['expires_at' => $expiresAt]);
                }

                return true;
            }

            $quantity = (int) ($prescription->dispensable_quantity ?? $prescription->quantity);

            if ($quantity < 1 || $drug->available_quantity < $quantity) {
                return false;
            }

            if ($reservation) {
                $reservation->update([
                    'drug_id' => $drug->id,
                    'visit_id' => $prescription->visit_id,
                    'quantity' => $quantity,
                    'status' => 'reserved',
                    'expires_at' => $expiresAt,
                    'released_at' => null,
                    'release_reason' => null,
                    'fulfilled_at' => null,
                ]);
                $drug->reserved_quantity += $quantity;
                $drug->save();
            } else {
                $this->createReservation($drug, $prescription, $quantity, $expiresAt);
            }

            return true;
        });
    }

    /**
     * Release an unpaid reservation and restore its reserved quantity to the drug's available stock.
     *
     * Completed, expired, missing, or otherwise non-reserved holds are left unchanged.
     */
    public function releasePrescription(Prescription $prescription, string $reason): void
    {
        DB::transaction(function () use ($prescription, $reason) {
            $drug = Drug::query()->lockForUpdate()->find($prescription->drug_id);
            $reservation = PrescriptionStockReservation::query()
                ->where('prescription_id', $prescription->id)
                ->lockForUpdate()
                ->first();

            if (! $reservation || $reservation->status !== 'reserved') {
                return;
            }

            if ($drug) {
                $drug->reserved_quantity = max(0, $drug->reserved_quantity - $reservation->quantity);
                $drug->save();
            }

            $reservation->update([
                'status' => 'released',
                'released_at' => now(),
                'release_reason' => $reason,
            ]);
        });
    }

    /**
     * Convert a live reservation into a physical stock deduction and movement record.
     *
     * Returns false if no live reservation exists or physical quantity is insufficient. Locks and
     * transaction boundaries ensure a successful fulfillment decrements stock exactly once.
     */
    public function fulfillPrescription(Prescription $prescription, int $userId): bool
    {
        return DB::transaction(function () use ($prescription, $userId) {
            $drug = $this->lockDrug((int) $prescription->drug_id);
            $reservation = PrescriptionStockReservation::query()
                ->where('prescription_id', $prescription->id)
                ->lockForUpdate()
                ->first();

            if (! $reservation || $reservation->status !== 'reserved' || $reservation->quantity > $drug->stock_quantity) {
                return false;
            }

            $before = (int) $drug->stock_quantity;
            $drug->stock_quantity -= $reservation->quantity;
            $drug->reserved_quantity = max(0, $drug->reserved_quantity - $reservation->quantity);
            $drug->save();

            $reservation->update(['status' => 'fulfilled', 'fulfilled_at' => now()]);
            $this->movement($drug, 'dispensed', -$reservation->quantity, $before, (int) $drug->stock_quantity, $userId, $prescription->id);

            return true;
        });
    }

    /**
     * Ensure every drug prescription represented by invoice items has a stock reservation.
     *
     * Prescriptions are processed in drug order inside a transaction; if any hold cannot be
     * acquired, the transaction is rolled back and false is returned.
     */
    public function ensureItemsReserved(Collection $items, ?CarbonInterface $expiresAt): bool
    {
        $prescriptions = $items
            ->filter(fn ($item) => $item->category === 'drug' && $item->billable instanceof Prescription)
            ->map(fn ($item) => $item->billable)
            ->unique('id')
            ->sortBy('drug_id');

        try {
            DB::transaction(function () use ($prescriptions, $expiresAt) {
                foreach ($prescriptions as $prescription) {
                    if (! $this->ensurePrescriptionReserved($prescription, $expiresAt)) {
                        throw new StockReservationUnavailable;
                    }
                }
            });

            return true;
        } catch (StockReservationUnavailable) {
            return false;
        }
    }

    /**
     * Expire all elapsed reservations and release their reserved quantities.
     *
     * Each drug is processed in its own transaction with the drug row locked before its
     * reservation rows. Returns the number of reservations expired.
     */
    public function expireReservations(): int
    {
        $drugIds = PrescriptionStockReservation::query()
            ->where('status', 'reserved')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->whereNotNull('drug_id')
            ->distinct()->pluck('drug_id');

        $expired = 0;

        foreach ($drugIds as $drugId) {
            $expired += DB::transaction(function () use ($drugId) {
                $drug = Drug::query()->lockForUpdate()->find($drugId);
                if (! $drug) {
                    return 0;
                }

                return $this->expireForLockedDrug($drug);
            });
        }

        return $expired;
    }

    /**
     * Expire elapsed reservations for a drug whose row is already locked in a transaction.
     *
     * Reserved quantity is restored once and each hold receives its expiration audit fields;
     * returns the number of expired reservations.
     */
    public function expireForLockedDrug(Drug $drug): int
    {
        $reservations = PrescriptionStockReservation::query()
            ->where('drug_id', $drug->id)
            ->where('status', 'reserved')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->lockForUpdate()->get();

        $quantity = (int) $reservations->sum('quantity');

        if ($quantity === 0) {
            return 0;
        }

        $drug->reserved_quantity = max(0, $drug->reserved_quantity - $quantity);
        $drug->save();

        foreach ($reservations as $reservation) {
            $reservation->update([
                'status' => 'expired',
                'released_at' => now(),
                'release_reason' => 'unpaid_reservation_expired',
            ]);
        }

        return $reservations->count();
    }

    /** Add stock and record the restock movement atomically under a drug-row lock. */
    public function restock(Drug $drug, int $quantity, int $userId, ?array $batch = null): Drug
    {
        return DB::transaction(function () use ($drug, $quantity, $userId, $batch) {
            $drug = Drug::query()->lockForUpdate()->findOrFail($drug->id);
            $before = (int) $drug->stock_quantity;
            $drug->stock_quantity += $quantity;
            $drug->save();
            $this->movement($drug, 'restock', $quantity, $before, (int) $drug->stock_quantity, $userId);

            if ($batch !== null) {
                DrugStockBatch::create([
                    'drug_id' => $drug->id,
                    'batch_number' => $batch['batch_number'] ?? null,
                    'quantity_received' => $quantity,
                    'quantity_remaining' => $quantity,
                    'expiry_date' => $batch['expiry_date'],
                    'received_by' => $userId,
                    'received_at' => now(),
                ]);
            }

            return $drug->refresh();
        });
    }

    /** Record the initial stock as a movement when a new catalogue drug starts with positive quantity. */
    public function recordOpeningBalance(Drug $drug): void
    {
        if ($drug->stock_quantity > 0) {
            $this->movement($drug, 'opening_balance', (int) $drug->stock_quantity, 0, (int) $drug->stock_quantity, null, null, 'Opening stock.');
        }
    }

    /** Persist the stock movement audit row with before/after balances and optional prescription context. */
    private function movement(Drug $drug, string $type, int $change, int $before, int $after, ?int $userId, ?int $prescriptionId = null, ?string $notes = null): void
    {
        DrugStockMovement::create([
            'drug_id' => $drug->id,
            'prescription_id' => $prescriptionId,
            'user_id' => $userId,
            'type' => $type,
            'quantity_change' => $change,
            'stock_before' => $before,
            'stock_after' => $after,
            'notes' => $notes,
        ]);
    }
}
