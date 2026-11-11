<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GeneralReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['period' => $this->input('period', 'month')]);
    }

    public function rules(): array
    {
        return [
            'period' => ['required', Rule::in(['month', 'quarter', 'year', 'custom'])],
            'year' => ['nullable', 'integer', 'between:2000,'.(now()->year + 1)],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'start_date' => ['required_if:period,custom', 'nullable', 'date'],
            'end_date' => ['required_if:period,custom', 'nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'The end date must be the same as or later than the start date.',
        ];
    }
}
