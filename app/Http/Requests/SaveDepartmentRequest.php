<?php

namespace App\Http\Requests;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Department|null $department */
        $department = $this->route('department');

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('departments', 'name')->ignore($department?->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'has_ward' => ['required', 'boolean'],
        ];
    }
}
