<?php

namespace App\Services;

use App\Models\Drug;
use App\Models\DrugStockMovement;
use App\Models\Prescription;
use App\Models\PrescriptionStockReservation;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DrugInventoryService
{
    /** Caller must already be inside a transaction. */
    public function lockDrug(int $drugId): Drug
    {
        $drug = Drug::query()->lockForUpdate()->findOrFail($drugId);
        $this->expireForLockedDrug($drug);

        return $drug->refresh();
    }

    /** Caller must hold the drug row lock and be inside a transaction. */
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

    /** Reserve the billable quantity again before collecting payment or dispensing a legacy order. */
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

    /** Fulfill the reservation and make the physical stock deduction exactly once. */
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

    /** Reacquire expired unpaid holds before they are charged or placed in online checkout. */
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

    /** Release timed-out holds. The drug row is always locked before reservation rows. */
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

    /** Caller must hold the drug row lock and be inside a transaction. */
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

    public function restock(Drug $drug, int $quantity, int $userId): Drug
    {
        return DB::transaction(function () use ($drug, $quantity, $userId) {
            $drug = Drug::query()->lockForUpdate()->findOrFail($drug->id);
            $before = (int) $drug->stock_quantity;
            $drug->stock_quantity += $quantity;
            $drug->save();
            $this->movement($drug, 'restock', $quantity, $before, (int) $drug->stock_quantity, $userId);

            return $drug->refresh();
        });
    }

    public function recordOpeningBalance(Drug $drug): void
    {
        if ($drug->stock_quantity > 0) {
            $this->movement($drug, 'opening_balance', (int) $drug->stock_quantity, 0, (int) $drug->stock_quantity, null, null, 'Opening stock.');
        }
    }

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
