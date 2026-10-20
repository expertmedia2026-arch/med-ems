<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;

class StoreAdmissionNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(Role::Doctor, Role::Nurse) ?? false;
    }

    public function rules(): array
    {
        return ['note' => ['required', 'string', 'min:2', 'max:5000']];
    }
}
