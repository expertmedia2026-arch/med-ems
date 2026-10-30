<?php

namespace App\Http\Requests;

use App\Models\Admission;
use App\Services\MaternityService;
use Illuminate\Foundation\Http\FormRequest;

class StartMaternityLaborRequest extends FormRequest
{
    /** Reject staff outside the admission's maternity access scope before validation runs. */
    public function authorize(MaternityService $maternity): bool
    {
        $admission = $this->route('admission');
        $user = $this->user();

        return $admission instanceof Admission
            && $user !== null
            && $maternity->canAccessAdmission($admission, $user);
    }

    /** Validate the initial labor assessment and the required materials checklist answer. */
    public function rules(): array
    {
        return [
            'labor_started_at' => ['nullable', 'date', 'before_or_equal:now'],
            'labor_assessment' => ['nullable', 'string', 'max:5000'],
            'patient_brought_delivery_materials' => ['required', 'boolean'],
        ];
    }
}
