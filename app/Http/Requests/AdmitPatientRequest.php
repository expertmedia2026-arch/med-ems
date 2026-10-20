<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdmitPatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(Role::Doctor) ?? false;
    }

    public function rules(): array
    {
        return [
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')->where('has_ward', true)],
            'admission_diagnosis' => ['required', 'string', 'max:1000'],
            'admission_reason' => ['required', 'string', 'max:5000'],
            'initial_treatment_plan' => ['required', 'string', 'max:5000'],
            'expected_discharge_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
        ];
    }
}
