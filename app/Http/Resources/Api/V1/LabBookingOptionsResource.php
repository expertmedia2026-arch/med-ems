<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabBookingOptionsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'date' => $this['date'],
            'lab_tests' => LabTestResource::collection($this['tests']),
            'available_slots' => $this['slots']->map(fn ($slot) => [
                'slot' => $slot->format('Y-m-d H:i'),
                'starts_at' => $slot->toIso8601String(),
            ])->values(),
        ];
    }
}
