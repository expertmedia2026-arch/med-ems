<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMaternityPostpartumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'postpartum_notes' => ['required', 'string', 'max:5000'],
        ];
    }
}
