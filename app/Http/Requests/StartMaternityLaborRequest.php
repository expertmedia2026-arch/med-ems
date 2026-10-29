<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartMaternityLaborRequest extends FormRequest
{
    /** Authorization is applied by the maternity route middleware and service access check. */
    public function authorize(): bool
    {
        return true;
    }

    /** Normalize browser radio values and API booleans to a consistent boolean input. */
    protected function prepareForValidation(): void
    {
        if ($this->has('patient_brought_delivery_materials')) {
            $this->merge([
                'patient_brought_delivery_materials' => $this->boolean('patient_brought_delivery_materials'),
            ]);
        }
    }

    /** Validate the initial labor assessment and the required materials checklist answer. */
    public function rules(): array
    {
        return [
            'labor_started_at' => ['nullable', 'date', 'before_or_equal:now'],
            'labor_assessment' => ['nullable', 'string', 'max:5000'],
            'patient_brought_delivery_materials' => ['required', 'boolean'],
        ];
    }
}
