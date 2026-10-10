<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PortalInvoicePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return ['item_ids.required' => 'Select at least one item to pay for.'];
    }
}
