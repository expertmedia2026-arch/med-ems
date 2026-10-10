<?php

namespace App\Services;

use App\Models\Drug;

class DrugCatalogService
{
    public function create(array $attributes): Drug
    {
        return Drug::create($attributes + ['is_active' => true]);
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
