<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestLabTestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lab_test_ids' => ['required', 'array', 'min:1'],
            'lab_test_ids.*' => [Rule::exists('lab_tests', 'id')->where('is_active', true)],
        ];
    }

    public function messages(): array
    {
        return ['lab_test_ids.required' => 'Select at least one test.'];
    }
}
