<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLabReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $newPatient = $this->input('mode') === 'new';

        return [
            'mode' => ['nullable', 'in:existing,new'],
            'referred_from' => ['nullable', 'string', 'max:150'],
            'referred_by' => ['nullable', 'string', 'max:150'],
            'complaint' => ['nullable', 'string', 'max:500'],
            'lab_test_ids' => ['required', 'array', 'min:1'],
            'lab_test_ids.*' => [Rule::exists('lab_tests', 'id')->where('is_active', true)],
            'first_name' => [$newPatient ? 'required' : 'nullable', 'string', 'max:100'],
            'last_name' => [$newPatient ? 'required' : 'nullable', 'string', 'max:100'],
            'gender' => [$newPatient ? 'required' : 'nullable', 'in:male,female'],
            'age' => [$newPatient ? 'required' : 'nullable', 'integer', 'min:0', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'hospital_number' => [$newPatient ? 'nullable' : 'required', 'string', 'max:100'],
            'confirm_new' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'hospital_number.required' => "Enter the patient's name, hospital number or phone.",
            'lab_test_ids.required' => 'Select at least one test.',
        ];
    }
}
