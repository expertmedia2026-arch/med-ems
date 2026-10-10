<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WalkInCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'complaint' => ['nullable', 'string', 'max:500'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'consultation_type_id' => [
                'required', 'integer',
                Rule::exists('consultation_types', 'id')->where('department_id', $this->input('department_id'))->where('is_active', true),
            ],
            'doctor_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('role', Role::Doctor->value)->where('is_active', true)
                    ->where('department_id', $this->input('department_id')),
            ],
        ];
    }
}
