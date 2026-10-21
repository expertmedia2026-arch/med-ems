<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MoveMortuaryCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessMortuary() ?? false;
    }

    public function rules(): array
    {
        return [
            'storage_location' => ['required', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
