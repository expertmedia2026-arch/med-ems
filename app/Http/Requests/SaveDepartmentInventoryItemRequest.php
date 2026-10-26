<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDepartmentInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->hasRole(Role::Admin)
            || ($user?->hasRole(Role::Lab) && $user->department_id !== null);
    }

    protected function prepareForValidation(): void
    {
        if ($this->user()?->hasRole(Role::Lab) && $this->user()->department_id) {
            $this->merge(['department_id' => $this->user()->department_id]);
        }
    }

    public function rules(): array
    {
        return [
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:150', Rule::unique('department_inventory_items', 'name')->where('department_id', $this->input('department_id'))],
            'category' => ['nullable', 'string', 'max:80'],
            'unit' => ['required', 'string', 'max:40'],
            'opening_quantity' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'reorder_level' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ];
    }
}
