<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveMortuaryCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canReceiveEmergencyMortuaryHandoff() ?? false;
    }

    public function rules(): array
    {
        return [
            'storage_location' => ['required', 'string', 'max:120'],
            'handoff_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
