<?php

namespace App\Http\Requests;

use App\Enums\AdmissionStatus;
use App\Enums\Role;
use App\Models\Admission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DischargeAdmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admission = $this->route('admission');

        return $admission instanceof Admission
            && $this->user()?->hasRole(Role::Admin, Role::Doctor)
            && ($this->user()->hasRole(Role::Admin)
                || $admission->attending_doctor_id === $this->user()->id
                || ($this->user()->hasRole(Role::Doctor)
                    && $this->user()->department_id
                    && (int) $this->user()->department_id === (int) $admission->department_id));
    }

    public function rules(): array
    {
        return [
            'discharge_diagnosis' => ['required', 'string', 'max:2000'],
            'discharge_summary' => ['required', 'string', 'max:10000'],
            'discharge_medications' => ['required', 'string', 'max:5000'],
            'follow_up_instructions' => ['required', 'string', 'max:5000'],
            'patient_discharge_instructions' => ['required', 'string', 'max:5000'],
            'pending_results_plan' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $admission = $this->route('admission');
            if (! $admission instanceof Admission || $admission->status !== AdmissionStatus::Active) {
                return;
            }

            $pendingResults = $admission->visit->labRequests()
                ->where(fn ($query) => $query->where('status', '!=', 'completed')->orWhereNull('result_reviewed_at'))
                ->exists();

            if ($pendingResults && blank($this->input('pending_results_plan'))) {
                $validator->errors()->add('pending_results_plan', 'Document who will follow up any outstanding or unreviewed test results before discharge.');
            }
        }];
    }
}
