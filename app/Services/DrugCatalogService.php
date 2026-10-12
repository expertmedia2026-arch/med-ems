<?php

namespace App\Services;

use App\Models\Drug;
use Illuminate\Support\Facades\DB;

class DrugCatalogService
{
    public function __construct(private readonly DrugInventoryService $inventory) {}

    public function create(array $attributes): Drug
    {
        return DB::transaction(function () use ($attributes) {
            $drug = Drug::create($attributes + ['is_active' => true, 'reserved_quantity' => 0]);
            $this->inventory->recordOpeningBalance($drug);

            return $drug;
        });
    }

    public function update(Drug $drug, array $attributes): Drug
    {
        $drug->update($attributes);

        return $drug->refresh();
    }

    public function toggle(Drug $drug): Drug
    {
        $drug->update(['is_active' => ! $drug->is_active]);

        return $drug->refresh();
    }
}
