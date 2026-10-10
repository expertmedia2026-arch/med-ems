<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::enum(Role::class)->except([Role::Patient])],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ];
    }

    public function messages(): array
    {
        return ['email.unique' => 'A staff member with this email already exists.'];
    }
}
