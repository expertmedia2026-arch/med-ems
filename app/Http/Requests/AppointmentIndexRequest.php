<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppointmentIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'doctor_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('role', Role::Doctor->value),
            ],
            'hospital_number' => ['nullable', 'string', 'max:50'],
        ];
    }
}
