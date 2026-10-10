<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientAppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status->value,
            'scheduled_at' => $this->scheduled_at->toIso8601String(),
            'reason' => $this->reason,
            'doctor' => $this->whenLoaded('doctor', fn () => $this->doctor ? [
                'id' => $this->doctor->id,
                'name' => $this->doctor->name,
                'department' => $this->doctor->department?->name,
            ] : null),
            'lab_tests' => LabTestResource::collection($this->whenLoaded('labTests')),
        ];
    }
}
