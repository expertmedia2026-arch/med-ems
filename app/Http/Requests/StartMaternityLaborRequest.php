<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartMaternityLaborRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('patient_brought_delivery_materials')) {
            $this->merge([
                'patient_brought_delivery_materials' => $this->boolean('patient_brought_delivery_materials'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'labor_started_at' => ['nullable', 'date', 'before_or_equal:now'],
            'labor_assessment' => ['nullable', 'string', 'max:5000'],
            'patient_brought_delivery_materials' => ['required', 'boolean'],
        ];
    }
}
