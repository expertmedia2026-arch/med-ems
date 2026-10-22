<?php

namespace App\Http\Requests;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'hod_id' => ['nullable', 'integer', 'exists:users,id', Rule::unique('departments', 'hod_id')->ignore($department?->id)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $hodId = $this->input('hod_id');
            if (! $hodId) {
                return;
            }

            /** @var Department|null $department */
            $department = $this->route('department');
            $isMember = $department?->users()->whereKey($hodId)->where('is_active', true)->where('role', '!=', 'patient')->exists();

            if (! $isMember) {
                $validator->errors()->add('hod_id', 'The HOD must be an active staff member assigned to this department.');
            }
        }];
    }
}
