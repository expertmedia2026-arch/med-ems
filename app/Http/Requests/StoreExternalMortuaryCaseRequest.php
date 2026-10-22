<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExternalMortuaryCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessMortuary() ?? false;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'gender' => ['required', Rule::in(['male', 'female', 'unknown'])],
            'estimated_age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'deceased_at' => ['required', 'date', 'before_or_equal:now'],
            'external_contact_name' => ['required', 'string', 'max:150'],
            'external_contact_phone' => ['required', 'string', 'max:30'],
            'external_contact_address' => ['nullable', 'string', 'max:1000'],
            'storage_location' => ['required', 'string', 'max:120'],
            'body_tag_number' => ['nullable', 'string', 'max:80', Rule::unique('mortuary_cases', 'body_tag_number')],
            'handoff_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
