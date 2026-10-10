<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaystackCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reference' => ['required_without:trxref', 'nullable', 'string', 'max:100'],
            'trxref' => ['required_without:reference', 'nullable', 'string', 'max:100'],
        ];
    }
}
