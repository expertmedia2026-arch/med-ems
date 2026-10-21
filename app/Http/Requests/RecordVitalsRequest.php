<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordVitalsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $visit = $this->route('visit');

        return $this->user()?->hasRole('admin', 'nurse')
            && (! $visit?->is_emergency || $this->user()->canAccessEmergency());
    }

    public function rules(): array
    {
        $visit = $this->route('visit');
        $requiredVitals = $visit?->is_emergency ? 'nullable' : 'required';

        return [
            'temperature' => [$requiredVitals, 'numeric', 'between:30,45'],
            'pulse' => [$requiredVitals, 'integer', 'between:20,250'],
            'respiratory_rate' => ['nullable', 'integer', 'between:5,80'],
            'spo2' => ['nullable', 'integer', 'between:50,100'],
            'bp_systolic' => ['nullable', 'required_with:bp_diastolic', 'integer', 'between:50,300'],
            'bp_diastolic' => ['nullable', 'required_with:bp_systolic', 'integer', 'between:30,200', 'lt:bp_systolic'],
            'weight' => ['nullable', 'numeric', 'between:0.5,400'],
            'height' => ['nullable', 'numeric', 'between:20,250'],
            'notes' => ['nullable', 'string', 'max:500'],
            'complaint' => ['nullable', 'string', 'max:500'],
            'triage_priority' => $visit?->is_emergency && $visit->status?->value === 'waiting_triage'
                ? ['required', Rule::in(['critical', 'high', 'moderate', 'low'])]
                : ['nullable', Rule::in(['critical', 'high', 'moderate', 'low'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'bp_systolic' => 'blood pressure top number',
            'bp_diastolic' => 'blood pressure bottom number',
        ];
    }
}
