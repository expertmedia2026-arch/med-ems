<?php

namespace App\Http\Requests;

class CompleteConsultationRequest extends ConsultationNotesRequest
{
    public function rules(): array
    {
        return array_replace(parent::rules(), ['diagnosis' => ['required', 'string', 'max:1000']]);
    }

    public function messages(): array
    {
        return ['diagnosis.required' => 'Enter a diagnosis before completing the consultation.'];
    }
}
