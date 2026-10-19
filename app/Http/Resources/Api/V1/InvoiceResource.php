<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isPatient = $request->user()?->hasRole('patient') ?? false;

        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'status' => $this->status->value,
            'total' => (float) $this->total,
            'amount_paid' => (float) $this->amount_paid,
            'balance' => $this->balance,
            'patient' => $this->whenLoaded('patient', fn () => $this->patient ? [
                'id' => $this->patient->id,
                'hospital_number' => $this->patient->hospital_number,
                'name' => $this->patient->full_name,
            ] : null),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'description' => $isPatient ? $item->descriptionForPatient() : $item->description,
                'category' => $item->category,
                'quantity' => $isPatient && $item->category === 'drug' ? null : $item->quantity,
                'total' => (float) $item->total,
                'is_paid' => $item->is_paid,
            ])->values()),
        ];
    }
}
