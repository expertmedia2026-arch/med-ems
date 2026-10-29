<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'admission_number' => $this->admission_number,
            'status' => $this->status?->value,
            'admission_reason' => $this->admission_reason,
            'admission_diagnosis' => $this->admission_diagnosis,
            'initial_treatment_plan' => $this->initial_treatment_plan,
            'admitted_at' => $this->admitted_at?->toIso8601String(),
            'expected_discharge_date' => $this->expected_discharge_date?->toDateString(),
            'discharge_diagnosis' => $this->discharge_diagnosis,
            'discharge_summary' => $this->discharge_summary,
            'discharge_medications' => $this->discharge_medications,
            'follow_up_instructions' => $this->follow_up_instructions,
            'patient_discharge_instructions' => $this->patient_discharge_instructions,
            'pending_results_plan' => $this->pending_results_plan,
            'discharged_at' => $this->discharged_at?->toIso8601String(),
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient->id,
                'hospital_number' => $this->patient->hospital_number,
                'name' => $this->patient->full_name,
                'phone' => $this->patient->phone,
            ]),
            'department' => $this->whenLoaded('department', fn () => [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ]),
            'attending_doctor' => $this->whenLoaded('attendingDoctor', fn () => $this->attendingDoctor ? [
                'id' => $this->attendingDoctor->id,
                'name' => $this->attendingDoctor->name,
            ] : null),
            'admitted_by' => $this->whenLoaded('admittedBy', fn () => $this->admittedBy?->name),
            'discharged_by' => $this->whenLoaded('dischargedBy', fn () => $this->dischargedBy?->name),
            'visit' => $this->whenLoaded('visit', fn () => new VisitResource($this->visit)),
            'notes' => $this->whenLoaded('notes', fn () => $this->notes->map(fn ($note) => [
                'id' => $note->id,
                'type' => $note->note_type,
                'note' => $note->note,
                'author' => $note->author?->name,
                'created_at' => $note->created_at?->toIso8601String(),
            ])->values()),
            'vitals' => $this->whenLoaded('vitals', fn () => $this->vitals->map(fn ($vital) => [
                'id' => $vital->id,
                'temperature' => $vital->temperature,
                'pulse' => $vital->pulse,
                'respiratory_rate' => $vital->respiratory_rate,
                'bp_systolic' => $vital->bp_systolic,
                'bp_diastolic' => $vital->bp_diastolic,
                'spo2' => $vital->spo2,
                'weight' => $vital->weight,
                'height' => $vital->height,
                'notes' => $vital->notes,
                'recorded_by' => $vital->recorder?->name,
                'recorded_at' => $vital->recorded_at?->toIso8601String(),
            ])->values()),
            'sugar_checks' => $this->whenLoaded('sugarChecks', fn () => $this->sugarChecks->map(fn ($check) => [
                'id' => $check->id,
                'reading_type' => $check->reading_type,
                'result' => $check->result,
                'unit' => $check->unit,
                'checked_by' => $check->checker?->name,
                'checked_at' => $check->checked_at?->toIso8601String(),
            ])->values()),
            'fluid_entries' => $this->whenLoaded('fluidEntries', fn () => $this->fluidEntries->map(fn ($entry) => [
                'id' => $entry->id,
                'flow' => $entry->flow,
                'route_or_output' => $entry->route_or_output,
                'fluid_type' => $entry->fluid_type,
                'quantity_ml' => $entry->quantity_ml,
                'drops_per_minute' => $entry->drops_per_minute,
                'recorded_by' => $entry->recorder?->name,
                'recorded_at' => $entry->recorded_at?->toIso8601String(),
            ])->values()),
            'oxygen_administrations' => $this->whenLoaded('oxygenAdministrations', fn () => $this->oxygenAdministrations->map(fn ($entry) => [
                'id' => $entry->id,
                'concentrator' => $entry->concentrator,
                'flow_rate' => $entry->flow_rate,
                'indication' => $entry->indication,
                'administered_by' => $entry->nurse?->name,
                'administered_at' => $entry->administered_at?->toIso8601String(),
            ])->values()),
            'nebulizer_uses' => $this->whenLoaded('nebulizerUses', fn () => $this->nebulizerUses->map(fn ($use) => [
                'id' => $use->id,
                'usage_number' => $use->usage_number,
                'billable_block' => $use->billable_block,
                'recorded_by' => $use->recorder?->name,
                'used_at' => $use->used_at?->toIso8601String(),
            ])->values()),
            'insulin_machine_blocks' => $this->whenLoaded('insulinMachineBlocks', fn () => $this->insulinMachineBlocks->map(fn ($block) => [
                'id' => $block->id,
                'block_number' => $block->block_number,
                'fee' => $block->fee,
                'payment_complete' => $block->payment_complete,
                'started_by' => $block->starter?->name,
                'started_at' => $block->started_at?->toIso8601String(),
                'uses' => $block->uses->map(fn ($use) => [
                    'id' => $use->id,
                    'prescription_id' => $use->prescription_id,
                    'bottle_label' => $use->bottle_label,
                    'opened_by' => $use->opener?->name,
                    'opened_at' => $use->opened_at?->toIso8601String(),
                ])->values(),
            ])->values()),
            'dama_record' => $this->whenLoaded('damaRecord', fn () => $this->damaRecord ? [
                'id' => $this->damaRecord->id,
                'patient_or_representative' => $this->damaRecord->patient_or_representative,
                'relationship' => $this->damaRecord->relationship,
                'reason' => $this->damaRecord->reason,
                'risks_explained' => $this->damaRecord->risks_explained,
                'follow_up_advice' => $this->damaRecord->follow_up_advice,
                'additional_notes' => $this->damaRecord->additional_notes,
                'completed_by' => $this->damaRecord->clinician?->name,
                'completed_at' => $this->damaRecord->completed_at?->toIso8601String(),
                'witnesses' => $this->damaRecord->witnesses->map(fn ($witness) => [
                    'id' => $witness->id,
                    'name' => $witness->name,
                    'download_url' => route('api.v1.admissions.dama.witnesses.show', [$this->id, $witness->id]),
                ])->values(),
            ] : null),
        ];
    }
}
