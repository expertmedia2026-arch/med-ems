<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReassignAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(Role::Admin, Role::Receptionist) ?? false;
    }

    public function rules(): array
    {
        return [
            'doctor_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('role', Role::Doctor->value)->where('is_active', true),
            ],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
