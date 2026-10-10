<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppointmentCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $appointment = $this->route('appointment');
        if (! $appointment || $appointment->isLab()) {
            return [];
        }

        $appointment->loadMissing('doctor');
        $departmentId = $appointment->doctor?->department_id;

        if (! $departmentId) {
            return [];
        }

        return [
            'consultation_type_id' => [
                'required', 'integer',
                Rule::exists('consultation_types', 'id')->where('department_id', $departmentId)->where('is_active', true),
            ],
        ];
    }
}
