<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmergencyCheckInRequest extends FormRequest
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
        ];
    }
}
