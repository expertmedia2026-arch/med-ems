<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePrescriptionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'requires_in_facility_administration' => $this->boolean('requires_in_facility_administration'),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'drug_id' => ['required', Rule::exists('drugs', 'id')->where('is_active', true)],
            'dosage' => ['required', 'string', 'max:100'],
            'frequency' => ['required', 'string', 'max:100'],
            'duration' => ['required', 'string', 'max:100'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'instructions' => ['nullable', 'string', 'max:255'],
            'requires_in_facility_administration' => ['boolean'],
            'required_administration_count' => ['nullable', 'required_if:requires_in_facility_administration,1', 'integer', 'min:1', 'max:100'],
        ];
    }
}
