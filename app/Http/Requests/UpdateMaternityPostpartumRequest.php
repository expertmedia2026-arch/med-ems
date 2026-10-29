<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMaternityPostpartumRequest extends FormRequest
{
    /** Route middleware and the service enforce role and patient-admission access. */
    public function authorize(): bool
    {
        return true;
    }

    /** Require a bounded written summary for the postpartum stage. */
    public function rules(): array
    {
        return [
            'postpartum_notes' => ['required', 'string', 'max:5000'],
        ];
    }
}
