<?php

namespace App\Http\Requests;

use App\Enums\AdmissionStatus;
use App\Enums\Role;
use App\Models\Admission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdmissionDeathRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admission = $this->route('admission');
        $actor = $this->user();

        if (! $admission instanceof Admission || ! $actor?->hasRole(Role::Admin, Role::Doctor)) {
            return false;
        }

        return $admission->status === AdmissionStatus::Active
            && ($actor->hasRole(Role::Admin)
                || $admission->attending_doctor_id === $actor->id
                || ($actor->hasRole(Role::Doctor)
                    && $actor->department_id
                    && (int) $actor->department_id === (int) $admission->department_id));
    }

    public function rules(): array
    {
        return [
            'cause_of_death' => ['required', 'string', 'min:2', 'max:5000'],
            'death_clinical_summary' => ['required', 'string', 'min:2', 'max:10000'],
            'disposition' => ['required', Rule::in(['mortuary_transfer', 'direct_family_release'])],
            'released_to' => ['required_if:disposition,direct_family_release', 'nullable', 'string', 'max:150'],
            'recipient_relationship' => ['required_if:disposition,direct_family_release', 'nullable', 'string', 'max:100'],
            'recipient_id_reference' => ['nullable', 'string', 'max:120'],
            'release_authority' => ['required_if:disposition,direct_family_release', 'nullable', 'string', 'max:150'],
            'release_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
