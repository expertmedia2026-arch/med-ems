<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReleaseMortuaryCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessMortuary() ?? false;
    }

    public function rules(): array
    {
        return [
            'released_to' => ['required', 'string', 'max:150'],
            'recipient_relationship' => ['required', 'string', 'max:100'],
            'recipient_id_reference' => ['nullable', 'string', 'max:120'],
            'release_authority' => ['required', 'string', 'max:150'],
            'release_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
