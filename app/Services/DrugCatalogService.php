<?php

namespace App\Services;

use App\Models\Drug;
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
     * @param array<string, mixed> $attributes Validated catalogue and initial stock attributes.
     */
    public function create(array $attributes): Drug
    {
        return DB::transaction(function () use ($attributes) {
            $drug = Drug::create($attributes + ['is_active' => true, 'reserved_quantity' => 0]);
            $this->inventory->recordOpeningBalance($drug);

            return $drug;
        });
    }

    /**
     * Update catalogue attributes without changing stock movement history.
     *
     * @param array<string, mixed> $attributes Validated editable drug fields.
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
