<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DrugResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'generic_name' => $this->generic_name,
            'form' => $this->form,
            'strength' => $this->strength,
            'unit_price' => (float) $this->unit_price,
            'stock_quantity' => $this->stock_quantity,
            'reserved_quantity' => $this->reserved_quantity,
            'available_quantity' => $this->available_quantity,
            'reorder_level' => $this->reorder_level,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
