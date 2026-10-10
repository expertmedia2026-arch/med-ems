<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'result' => $this->result,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'test' => $this->whenLoaded('test', fn () => $this->test ? ['id' => $this->test->id, 'name' => $this->test->name] : null),
            'visit' => $this->whenLoaded('visit', fn () => $this->visit ? [
                'id' => $this->visit->id,
                'patient' => $this->visit->patient?->full_name,
                'hospital_number' => $this->visit->patient?->hospital_number,
            ] : null),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($attachment) => [
                'id' => $attachment->id,
                'name' => $attachment->original_name,
                'mime_type' => $attachment->mime,
                'size' => $attachment->size,
                'download_url' => route('api.v1.lab.attachments.show', $attachment),
            ])->values()),
        ];
    }
}
