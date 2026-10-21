<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnidentifiedEmergencyCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canRegisterEmergencyArrival() ?? false;
    }

    public function rules(): array
    {
        return [
            'complaint' => ['required', 'string', 'max:1000'],
            'arrival_mode' => ['required', Rule::in(['walk_in', 'ambulance', 'referred', 'other'])],
            'gender' => ['required', Rule::in(['unknown', 'male', 'female'])],
            'estimated_age' => ['nullable', 'integer', 'between:0,120'],
            'next_of_kin_name' => ['nullable', 'string', 'max:150'],
            'next_of_kin_phone' => ['nullable', 'string', 'max:20'],
        ];
    }
}
