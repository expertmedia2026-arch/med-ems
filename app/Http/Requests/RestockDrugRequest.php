<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestockDrugRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = ['quantity' => ['required', 'integer', 'min:1', 'max:100000']];

        if ($this->user()?->hospital?->isStandalonePharmacy()) {
            $rules['expiry_date'] = ['required', 'date', 'after:today'];
            $rules['batch_number'] = ['nullable', 'string', 'max:100'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return ['expiry_date.after' => 'The restocked batch expiry date must be in the future.'];
    }
}
