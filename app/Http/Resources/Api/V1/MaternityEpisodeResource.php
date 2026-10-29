<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaternityEpisodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'labor_started_at' => $this->labor_started_at?->toIso8601String(),
            'labor_assessment' => $this->labor_assessment,
            'patient_brought_delivery_materials' => $this->patient_brought_delivery_materials,
            'delivery_at' => $this->delivery_at?->toIso8601String(),
            'delivery_mode' => $this->delivery_mode,
            'delivery_notes' => $this->delivery_notes,
            'hospital_supplied_delivery_pack' => $this->hospital_supplied_delivery_pack,
            'delivery_material_notes' => $this->delivery_material_notes,
            'postpartum_notes' => $this->postpartum_notes,
            'admission' => $this->whenLoaded('admission', fn () => [
                'id' => $this->admission->id,
                'admission_number' => $this->admission->admission_number,
                'status' => $this->admission->status?->value,
                'patient' => [
                    'id' => $this->admission->patient->id,
                    'hospital_number' => $this->admission->patient->hospital_number,
                    'name' => $this->admission->patient->full_name,
                ],
            ]),
            'newborns' => $this->whenLoaded('newborns', fn () => $this->newborns->map(fn ($newborn) => [
                'id' => $newborn->id,
                'birth_order' => $newborn->birth_order,
                'outcome' => $newborn->outcome,
                'name' => trim(($newborn->first_name ?? '').' '.($newborn->last_name ?? '')) ?: null,
                'gender' => $newborn->gender,
                'date_of_birth' => $newborn->date_of_birth?->toDateString(),
                'patient' => $newborn->patient ? [
                    'id' => $newborn->patient->id,
                    'hospital_number' => $newborn->patient->hospital_number,
                    'name' => $newborn->patient->full_name,
                ] : null,
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
