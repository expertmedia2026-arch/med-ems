<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class CompleteConsultationRequest extends ConsultationNotesRequest
{
    public function rules(): array
    {
        $visit = $this->route('visit');
        $rules = array_replace(parent::rules(), ['diagnosis' => ['required', 'string', 'max:1000']]);

        if ($visit?->is_emergency) {
            $rules['disposition'] = ['required', Rule::in(['discharged', 'transferred', 'deceased'])];
            $rules['disposition_note'] = ['nullable', 'string', 'max:2000', 'required_if:disposition,transferred'];
            $rules['deceased_at'] = ['required_if:disposition,deceased', 'nullable', 'date', 'before_or_equal:now'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'diagnosis.required' => 'Enter a diagnosis before completing the consultation.',
            'disposition.required' => 'Choose how the emergency visit ended.',
            'disposition_note.required_if' => 'Enter the receiving facility or transfer details.',
            'deceased_at.required_if' => 'Record the date and time of death.',
        ];
    }
}
