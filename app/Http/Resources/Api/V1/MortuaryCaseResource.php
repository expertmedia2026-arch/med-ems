<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MortuaryCaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'case_number' => $this->case_number,
            'source' => $this->source,
            'status' => $this->status,
            'deceased_at' => $this->deceased_at?->toIso8601String(),
            'deceased_age' => $this->deceased_age,
            'declared_at' => $this->declared_at?->toIso8601String(),
            'cause_of_death' => $this->cause_of_death,
            'death_clinical_summary' => $this->death_clinical_summary,
            'release_route' => $this->release_route,
            'mortuary_transfer_approved_at' => $this->mortuary_transfer_approved_at?->toIso8601String(),
            'mortuary_transfer_approval_note' => $this->mortuary_transfer_approval_note,
            'received_at' => $this->received_at?->toIso8601String(),
            'storage_location' => $this->storage_location,
            'storage_billed_through' => $this->storage_billed_through?->toIso8601String(),
            'body_tag_number' => $this->body_tag_number,
            'handoff_note' => $this->handoff_note,
            'released_at' => $this->released_at?->toIso8601String(),
            'released_to' => $this->released_to,
            'recipient_relationship' => $this->recipient_relationship,
            'recipient_id_reference' => $this->recipient_id_reference,
            'release_authority' => $this->release_authority,
            'release_note' => $this->release_note,
            'external_contact' => $this->when($this->source === 'walk_in', [
                'name' => $this->external_contact_name,
                'phone' => $this->external_contact_phone,
                'address' => $this->external_contact_address,
            ]),
            'fees' => [
                'intake' => (float) $this->intake_fee,
                'daily_storage' => (float) $this->daily_storage_fee,
                'state_tax_daily' => (float) $this->state_tax_daily_fee,
            ],
            'death_certificate' => $this->when($this->death_certificate_issued_at !== null, [
                'number' => $this->death_certificate_number,
                'issued_at' => $this->death_certificate_issued_at?->toIso8601String(),
                'issuer' => $this->death_certificate_issuer_name,
                'registration_number' => $this->death_certificate_registration_number,
                'cause' => $this->death_certificate_cause,
                'other_conditions' => $this->death_certificate_other_conditions,
            ]),
            'patient' => $this->whenLoaded('patient', fn () => $this->patient ? [
                'id' => $this->patient->id,
                'hospital_number' => $this->patient->hospital_number,
                'name' => $this->patient->full_name,
                'identity_status' => $this->patient->identity_status,
            ] : null),
            'visit' => $this->whenLoaded('visit', fn () => $this->visit ? [
                'id' => $this->visit->id,
                'status' => $this->visit->status?->value,
                'department' => $this->visit->department?->name,
            ] : null),
            'invoice' => $this->whenLoaded('invoice', fn () => $this->invoice ? [
                'id' => $this->invoice->id,
                'invoice_number' => $this->invoice->invoice_number,
                'status' => $this->invoice->status?->value,
                'total' => (float) $this->invoice->total,
                'amount_paid' => (float) $this->invoice->amount_paid,
                'balance' => (float) $this->invoice->balance,
                'items' => $this->when($this->invoice->relationLoaded('items'), fn () => $this->invoice->items->map(fn ($item) => [
                    'id' => $item->id,
                    'category' => $item->category,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'total' => (float) $item->total,
                    'is_paid' => $item->is_paid,
                ])->values()),
            ] : null),
            'opened_by' => $this->whenLoaded('openedBy', fn () => $this->openedBy?->name),
            'declared_by' => $this->whenLoaded('declaredBy', fn () => $this->declaredBy?->name),
            'transfer_approved_by' => $this->whenLoaded('mortuaryTransferApprovedBy', fn () => $this->mortuaryTransferApprovedBy?->name),
            'received_by' => $this->whenLoaded('receivedBy', fn () => $this->receivedBy?->name),
            'released_by' => $this->whenLoaded('releasedBy', fn () => $this->releasedBy?->name),
            'events' => $this->whenLoaded('events', fn () => $this->events->map(fn ($event) => [
                'id' => $event->id,
                'type' => $event->event_type,
                'occurred_at' => $event->occurred_at?->toIso8601String(),
                'performed_by' => $event->actor?->name,
                'details' => $event->details,
            ])->values()),
        ];
    }
}
