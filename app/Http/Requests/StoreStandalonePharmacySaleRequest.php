<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStandalonePharmacySaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hospital?->isStandalonePharmacy() === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['nullable', 'string', 'max:150'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'payment_method' => ['required', Rule::in(['cash', 'pos', 'transfer'])],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.drug_id' => ['required', 'integer', 'distinct', 'exists:drugs,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
