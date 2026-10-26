<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;

class RecordDepartmentInventoryMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(Role::Admin, Role::Lab) ?? false;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
