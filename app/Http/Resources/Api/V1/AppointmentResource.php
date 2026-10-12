<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status?->value,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'reason' => $this->reason,
            'patient' => $this->whenLoaded('patient', fn () => $this->patient ? [
                'id' => $this->patient->id,
                'hospital_number' => $this->patient->hospital_number,
                'name' => $this->patient->full_name,
                'phone' => $this->patient->phone,
            ] : null),
            'doctor' => $this->whenLoaded('doctor', fn () => $this->doctor ? [
                'id' => $this->doctor->id,
                'name' => $this->doctor->name,
                'department' => $this->doctor->department?->name,
            ] : null),
            'reassignments' => $this->whenLoaded('reassignments', fn () => $this->reassignments->map(fn ($reassignment) => [
                'from_doctor' => $reassignment->fromDoctor?->name,
                'to_doctor' => $reassignment->toDoctor?->name,
                'reassigned_by' => $reassignment->reassignedBy?->name,
                'reason' => $reassignment->reason,
                'reassigned_at' => $reassignment->created_at?->toIso8601String(),
            ])->values()),
            'lab_tests' => LabTestResource::collection($this->whenLoaded('labTests')),
        ];
    }
}
