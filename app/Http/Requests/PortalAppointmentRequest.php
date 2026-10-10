<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PortalAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:consultation,lab'],
            'department_id' => ['required_if:type,consultation', 'nullable', 'integer', 'exists:departments,id'],
            'doctor_id' => [
                'required_if:type,consultation', 'nullable',
                Rule::exists('users', 'id')->where('role', Role::Doctor->value)->where('is_active', true)
                    ->where('department_id', $this->input('department_id')),
            ],
            'appointment_date' => [
                'required_if:type,consultation', 'nullable', 'date', 'after_or_equal:today',
                'before_or_equal:'.today()->addDays(config('hms.booking.max_days_ahead'))->toDateString(),
            ],
            'slot' => ['required_if:type,lab', 'nullable', 'date_format:Y-m-d H:i'],
            'reason' => ['nullable', 'string', 'max:255'],
            'lab_test_ids' => ['required_if:type,lab', 'nullable', 'array', 'min:1'],
            'lab_test_ids.*' => [Rule::exists('lab_tests', 'id')->where('is_active', true)],
        ];
    }

    public function messages(): array
    {
        return [
            'doctor_id.required_if' => 'Please choose a doctor.',
            'lab_test_ids.required_if' => 'Please select at least one test.',
            'appointment_date.required_if' => 'Please choose an available date.',
            'slot.required_if' => 'Please pick a time.',
        ];
    }
}
