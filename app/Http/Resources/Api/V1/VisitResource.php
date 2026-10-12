<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'appointment_id' => $this->appointment_id,
            'type' => $this->type,
            'status' => $this->status?->value,
            'complaint' => $this->complaint,
            'diagnosis' => $this->diagnosis,
            'clinical_notes' => $this->clinical_notes,
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'consulted_at' => $this->consulted_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'patient' => $this->whenLoaded('patient', fn () => $this->patient ? [
                'id' => $this->patient->id,
                'hospital_number' => $this->patient->hospital_number,
                'name' => $this->patient->full_name,
                'phone' => $this->patient->phone,
                'age_group' => $this->patient->age === null ? null : ($this->patient->age < 18 ? 'Child' : 'Adult'),
            ] : null),
            'doctor' => $this->whenLoaded('doctor', fn () => $this->doctor ? [
                'id' => $this->doctor->id,
                'name' => $this->doctor->name,
            ] : null),
            'department' => $this->whenLoaded('department', fn () => $this->department ? [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ] : null),
            'consultation_type' => $this->whenLoaded('consultationType', fn () => $this->consultationType ? [
                'id' => $this->consultationType->id,
                'name' => $this->consultationType->name,
            ] : null),
            'vitals' => $this->whenLoaded('vitals', fn () => $this->vitals ? [
                'temperature' => $this->vitals->temperature,
                'pulse' => $this->vitals->pulse,
                'respiratory_rate' => $this->vitals->respiratory_rate,
                'bp_systolic' => $this->vitals->bp_systolic,
                'bp_diastolic' => $this->vitals->bp_diastolic,
                'spo2' => $this->vitals->spo2,
                'weight' => $this->vitals->weight,
                'height' => $this->vitals->height,
                'bmi' => $this->vitals->bmi,
                'notes' => $this->vitals->notes,
            ] : null),
            'prescriptions' => $this->whenLoaded('prescriptions', fn () => $this->prescriptions->map(fn ($item) => [
                'id' => $item->id,
                'drug' => $item->drug?->display_name,
                'dosage' => $item->dosage,
                'frequency' => $item->frequency,
                'duration' => $item->duration,
                'quantity' => $item->quantity,
                'reserved_quantity' => $item->dispensable_quantity ?? $item->quantity,
                'shortage_quantity' => max(0, $item->quantity - ($item->dispensable_quantity ?? $item->quantity)),
                'reservation_status' => $item->stockReservation?->status,
                'instructions' => $item->instructions,
                'status' => $item->status?->value,
                'is_paid' => $item->is_paid,
            ])->values()),
            'lab_requests' => $this->whenLoaded('labRequests', fn () => $this->labRequests->map(fn ($item) => [
                'id' => $item->id,
                'test' => $item->test?->name,
                'status' => $item->status?->value,
                'result' => $item->result,
                'is_paid' => $item->is_paid,
            ])->values()),
        ];
    }
}
