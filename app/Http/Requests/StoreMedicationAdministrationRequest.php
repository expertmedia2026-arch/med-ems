<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMedicationAdministrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:administered,refused,deferred,not_given'],
            'dose' => ['nullable', 'string', 'max:100'],
            'route' => ['nullable', 'string', 'max:50'],
            'site' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'reason' => ['nullable', 'required_unless:status,administered', 'string', 'max:2000'],
        ];
    }
}
