<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConsultationNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'complaint' => ['nullable', 'string', 'max:500'],
            'diagnosis' => ['nullable', 'string', 'max:1000'],
            'clinical_notes' => ['nullable', 'string', 'max:5000'],
            'patient_visible_note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
