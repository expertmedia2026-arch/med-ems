<?php

namespace App\Http\Requests\Api\V1;

use App\Services\GlobalAdminHospitalService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlatformHospitalConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', Rule::in(array_keys(GlobalAdminHospitalService::MODULES))],
            'admission_template' => ['required', Rule::in(['current', 'basic'])],
        ];
    }
}
