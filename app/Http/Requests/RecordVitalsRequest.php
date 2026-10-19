<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordVitalsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'temperature' => ['required', 'numeric', 'between:30,45'],
            'pulse' => ['required', 'integer', 'between:20,250'],
            'respiratory_rate' => ['nullable', 'integer', 'between:5,80'],
            'spo2' => ['nullable', 'integer', 'between:50,100'],
            'bp_systolic' => ['nullable', 'required_with:bp_diastolic', 'integer', 'between:50,300'],
            'bp_diastolic' => ['nullable', 'required_with:bp_systolic', 'integer', 'between:30,200', 'lt:bp_systolic'],
            'weight' => ['nullable', 'numeric', 'between:0.5,400'],
            'height' => ['nullable', 'numeric', 'between:20,250'],
            'notes' => ['nullable', 'string', 'max:500'],
            'complaint' => ['nullable', 'string', 'max:500'],
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
