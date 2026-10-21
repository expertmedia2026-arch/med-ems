<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmergencyProgressNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $visit = $this->route('visit');
        $user = $this->user();

        return $visit && $user && $visit->is_emergency
            && ($user->hasRole('doctor', 'nurse') && $user->canAccessEmergency());
    }

    public function rules(): array
    {
        return ['note' => ['required', 'string', 'min:2', 'max:5000']];
    }
}
