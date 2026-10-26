<?php

namespace App\Http\Requests;

use App\Models\LabTest;
use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveLabTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var LabTest|null $test */
        $test = $this->route('labTest');

        return [
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:150', Rule::unique('lab_tests', 'name')->where('department_id', $this->input('department_id'))->ignore($test?->id)],
            'code' => ['nullable', 'string', 'max:20', Rule::unique('lab_tests', 'code')->where('department_id', $this->input('department_id'))->ignore($test?->id)],
            'category' => ['nullable', 'string', 'max:50'],
            'sample_type' => ['nullable', 'string', 'max:50'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'turnaround_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->user()?->hasRole(Role::Lab) && $this->user()->department_id) {
            $this->merge(['department_id' => $this->user()->department_id]);
        }
    }
}
