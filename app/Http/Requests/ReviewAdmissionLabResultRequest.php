<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\Admission;
use App\Models\LabRequest;
use Illuminate\Foundation\Http\FormRequest;

class ReviewAdmissionLabResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admission = $this->route('admission');

        return $admission instanceof Admission
            && $this->user()?->hasRole(Role::Admin, Role::Doctor)
            && ($this->user()->hasRole(Role::Admin) || $admission->attending_doctor_id === $this->user()->id);
    }

    public function rules(): array
    {
        return ['review_note' => ['nullable', 'string', 'max:5000']];
    }
}
