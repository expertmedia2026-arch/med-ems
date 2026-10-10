<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PortalAppointmentFiltersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', 'in:consultation,lab'],
            'department_id' => ['nullable', 'integer'],
            'doctor_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'date', 'after_or_equal:today', 'before_or_equal:'.now()->addDays(config('hms.booking.max_days_ahead'))->toDateString()],
        ];
    }
}
