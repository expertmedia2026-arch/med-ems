<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BillingIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'in:unpaid,partial,paid'],
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }
}
