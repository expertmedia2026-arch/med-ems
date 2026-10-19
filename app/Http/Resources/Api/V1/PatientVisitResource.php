<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientVisitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $detailsVisible = (bool) $this->resource->getAttribute('patient_details_visible');
        $labResultsVisible = (bool) $this->resource->getAttribute('patient_lab_results_visible');

        return [
            'id' => $this->id,
            'type' => $this->isLabOnly() ? 'lab' : 'consultation',
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'doctor' => $this->whenLoaded('doctor', fn () => $this->doctor ? [
                'id' => $this->doctor->id,
                'name' => $this->doctor->name,
                'department' => $this->doctor->department?->name,
            ] : null),
            'department' => $this->whenLoaded('department', fn () => $this->department ? [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ] : null),
            'consultation_type' => $this->whenLoaded('consultationType', fn () => $this->consultationType ? [
                'id' => $this->consultationType->id,
                'name' => $this->consultationType->name,
                'department' => $this->consultationType->department?->name,
            ] : null),
            'patient_visible_note' => $this->when($detailsVisible, $this->patient_visible_note),
            'vitals' => $this->when($detailsVisible && $this->relationLoaded('vitals') && $this->vitals, fn () => [
                'temperature' => $this->vitals?->temperature,
                'pulse' => $this->vitals?->pulse,
                'blood_pressure' => $this->vitals?->blood_pressure,
                'respiratory_rate' => $this->vitals?->respiratory_rate,
                'spo2' => $this->vitals?->spo2,
                'weight' => $this->vitals?->weight,
                'height' => $this->vitals?->height,
                'bmi' => $this->vitals?->bmi,
            ]),
            'lab_requests' => $this->when($detailsVisible && $this->relationLoaded('labRequests'), fn () => $this->labRequests->map(fn ($labRequest) => [
                'id' => $labRequest->id,
                'test' => $labRequest->test?->name,
                'status' => $labRequest->status->value,
                'completed_at' => $labRequest->completed_at?->toIso8601String(),
                'result' => $this->when($labResultsVisible && $labRequest->status->value === 'completed', $labRequest->result),
                'attachments' => $this->when($labResultsVisible && $labRequest->status->value === 'completed' && $labRequest->relationLoaded('attachments'), fn () => $labRequest->attachments->map(fn ($attachment) => [
                    'id' => $attachment->id,
                    'name' => $attachment->original_name,
                    'mime_type' => $attachment->mime,
                    'size' => $attachment->size,
                    'download_url' => route('api.v1.patient.attachments.show', $attachment),
                ])->values()),
            ])->values()),
            'prescriptions' => $this->when($detailsVisible && $this->relationLoaded('prescriptions'), fn () => $this->prescriptions->map(fn ($prescription) => [
                'id' => $prescription->id,
                'drug' => $prescription->drug?->display_name,
                'dosage' => $prescription->dosage,
                'frequency' => $prescription->frequency,
                'duration' => $prescription->duration,
                'quantity' => $prescription->quantity,
                'instructions' => $prescription->instructions,
                'status' => $prescription->status->value,
            ])->values()),
            'invoice' => $this->whenLoaded('invoice', fn () => $this->invoice ? [
                'invoice_number' => $this->invoice->invoice_number,
                'status' => $this->invoice->status->value,
                'total' => (float) $this->invoice->total,
                'amount_paid' => (float) $this->invoice->amount_paid,
                'balance' => $this->invoice->balance,
                'items' => $this->invoice->items->map(fn ($item) => [
                    'description' => $item->description,
                    'category' => $item->category,
                    'quantity' => $item->quantity,
                    'total' => (float) $item->total,
                    'is_paid' => $item->is_paid,
                ])->values(),
            ] : null),
            'lab_request_count' => $this->when(isset($this->lab_requests_count), $this->lab_requests_count),
            'prescription_count' => $this->when(isset($this->prescriptions_count), $this->prescriptions_count),
            'details_in_progress' => ! $detailsVisible,
        ];
    }
}
