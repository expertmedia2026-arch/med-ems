<?php

namespace App\Http\Requests\Api\V1;

use App\Services\GlobalAdminHospitalService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePlatformHospitalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:80', 'alpha_dash', 'unique:hospitals,slug'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', Rule::in(array_keys(GlobalAdminHospitalService::MODULES))],
            'admission_template' => ['required', Rule::in(['current', 'basic'])],
        ];
    }
}
