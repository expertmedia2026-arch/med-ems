<?php

namespace App\Services;

use App\Models\Drug;
use App\Models\DrugStockBatch;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Coordinates drug catalogue changes with their initial inventory audit records.
 */
class DrugCatalogService
{
    /** Inject inventory operations used to record the initial stock movement. */
    public function __construct(private readonly DrugInventoryService $inventory) {}

    /**
     * Create an active drug and its opening-stock movement atomically.
     *
     * @param  array<string, mixed>  $attributes  Validated catalogue and initial stock attributes.
     */
    public function create(array $attributes, ?int $userId = null): Drug
    {
        return DB::transaction(function () use ($attributes, $userId) {
            $drug = Drug::create(Arr::except($attributes, ['expiry_date', 'batch_number']) + ['is_active' => true, 'reserved_quantity' => 0]);
            $this->inventory->recordOpeningBalance($drug);

            if (isset($attributes['expiry_date']) && $drug->stock_quantity > 0) {
                DrugStockBatch::create([
                    'drug_id' => $drug->id,
                    'batch_number' => $attributes['batch_number'] ?? null,
                    'quantity_received' => $drug->stock_quantity,
                    'quantity_remaining' => $drug->stock_quantity,
                    'expiry_date' => $attributes['expiry_date'],
                    'received_by' => $userId,
                    'received_at' => now(),
                ]);
            }

            return $drug;
        });
    }

    /**
     * Update catalogue attributes without changing stock movement history.
     *
     * @param  array<string, mixed>  $attributes  Validated editable drug fields.
     */
    public function update(Drug $drug, array $attributes): Drug
    {
        $drug->update($attributes);

        return $drug->refresh();
    }

    /** Invert the drug's active flag and return the refreshed catalogue record. */
    public function toggle(Drug $drug): Drug
    {
        $drug->update(['is_active' => ! $drug->is_active]);

        return $drug->refresh();
    }
}
