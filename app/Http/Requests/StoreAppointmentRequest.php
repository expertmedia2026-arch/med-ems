<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hospital_number' => ['required', 'exists:patients,hospital_number'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'doctor_id' => [
                'required',
                Rule::exists('users', 'id')->where('role', Role::Doctor->value)
                    ->where('is_active', true)->where('department_id', $this->input('department_id')),
            ],
            'appointment_date' => [
                'required', 'date', 'after_or_equal:today',
                'before_or_equal:'.today()->addDays(config('hms.booking.max_days_ahead'))->toDateString(),
            ],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
