<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LabReferralFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['patient' => ['nullable', 'integer', 'exists:patients,id']];
    }
}
