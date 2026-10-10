<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DispensePrescriptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['prescription_ids' => ['required', 'array', 'min:1'], 'prescription_ids.*' => ['integer']];
    }

    public function messages(): array
    {
        return ['prescription_ids.required' => 'Select at least one item to dispense.'];
    }
}
