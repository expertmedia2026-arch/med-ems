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
            'is_emergency' => (bool) $this->is_emergency,
            'arrival_mode' => $this->arrival_mode,
            'triage_priority' => $this->triage_priority,
            'triaged_at' => $this->triaged_at?->toIso8601String(),
            'disposition' => $this->disposition,
            'disposition_note' => $this->disposition_note,
            'status' => $this->status?->value,
            'complaint' => $this->complaint,
            'diagnosis' => $this->diagnosis,
            'clinical_notes' => $this->clinical_notes,
            'patient_visible_note' => $this->patient_visible_note,
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'consulted_at' => $this->consulted_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'discharged_at' => $this->discharged_at?->toIso8601String(),
            'discharged_by' => $this->whenLoaded('dischargeActor', fn () => $this->dischargeActor?->name),
            'patient' => $this->whenLoaded('patient', fn () => $this->patient ? [
                'id' => $this->patient->id,
                'hospital_number' => $this->patient->hospital_number,
                'name' => $this->patient->full_name,
                'phone' => $this->patient->phone,
                'identity_status' => $this->patient->identity_status,
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
            'emergency_observations' => $this->whenLoaded('emergencyObservations', fn () => $this->emergencyObservations->map(fn ($observation) => [
                'temperature' => $observation->temperature,
                'pulse' => $observation->pulse,
                'respiratory_rate' => $observation->respiratory_rate,
                'bp_systolic' => $observation->bp_systolic,
                'bp_diastolic' => $observation->bp_diastolic,
                'spo2' => $observation->spo2,
                'weight' => $observation->weight,
                'height' => $observation->height,
                'notes' => $observation->notes,
                'recorded_by' => $observation->recorder?->name,
                'recorded_at' => $observation->recorded_at?->toIso8601String(),
            ])->values()),
            'emergency_progress_notes' => $this->whenLoaded('emergencyProgressNotes', fn () => $this->emergencyProgressNotes->map(fn ($note) => [
                'id' => $note->id,
                'type' => $note->note_type,
                'note' => $note->note,
                'author' => $note->author?->name,
                'created_at' => $note->created_at?->toIso8601String(),
            ])->values()),
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
                'requires_in_facility_administration' => (bool) $item->requires_in_facility_administration,
                'required_administration_count' => $item->required_administration_count,
                'administered_count' => $item->requires_in_facility_administration ? $item->administered_count : 0,
                'administration_waived_at' => $item->administration_waived_at?->toIso8601String(),
                'administration_waiver_reason' => $item->administration_waiver_reason,
                'administration_waived_by' => $item->relationLoaded('administrationWaiver') ? $item->administrationWaiver?->name : null,
                'administrations' => $item->relationLoaded('administrations') ? $item->administrations->map(fn ($record) => [
                    'id' => $record->id,
                    'status' => $record->status,
                    'administered_by' => $record->nurse?->name,
                    'dose' => $record->dose,
                    'route' => $record->route,
                    'site' => $record->site,
                    'notes' => $record->notes,
                    'reason' => $record->reason,
                    'administered_at' => $record->administered_at?->toIso8601String(),
                ])->values() : null,
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
