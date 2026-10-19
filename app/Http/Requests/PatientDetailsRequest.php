<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Patient;

class PatientDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $isNhis = $this->boolean('is_nhis');
        $email = $this->input('email');
        $phone = $this->input('phone');

        $this->merge([
            'is_nhis' => $isNhis,
            'nhis_number' => $isNhis ? $this->input('nhis_number') : null,
            'email' => is_string($email) && trim($email) !== '' ? mb_strtolower(trim($email)) : $email,
            'phone' => is_string($phone) && trim($phone) !== '' ? trim($phone) : $phone,
        ]);
    }

    public function rules(): array
    {
        $patient = $this->route('patient');
        $phoneUnique = Rule::unique('patients', 'phone');
        $emailUnique = Rule::unique('patients', 'email');

        if ($patient instanceof Patient) {
            $phoneUnique->ignore($patient->getKey());
            $emailUnique->ignore($patient->getKey());
        }

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', 'in:male,female'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'phone' => ['nullable', 'string', 'max:20', $phoneUnique],
            'email' => ['nullable', 'email', 'max:255', $emailUnique],
            'address' => ['nullable', 'string', 'max:500'],
            'blood_group' => ['nullable', 'in:A+,A-,B+,B-,AB+,AB-,O+,O-'],
            'genotype' => ['nullable', 'in:AA,AS,SS,AC,SC'],
            'allergies' => ['nullable', 'string', 'max:500'],
            'next_of_kin_name' => ['nullable', 'string', 'max:150'],
            'next_of_kin_phone' => ['nullable', 'string', 'max:20'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'is_nhis' => ['boolean'],
            'nhis_number' => ['nullable', 'required_if:is_nhis,1', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.unique' => 'This phone number is already used by another patient.',
            'email.unique' => 'This email address is already used by another patient.',
            'photo.max' => 'The patient photo must be 5 MB or smaller. Choose a smaller image and try again.',
            'photo.uploaded' => 'The patient photo could not be uploaded. It may be too large; choose an image up to 5 MB and try again.',
            'photo.image' => 'Choose a valid image file for the patient photo.',
            'photo.mimes' => 'The patient photo must be a JPG, PNG or WebP image.',
        ];
    }

    public function attributes(): array
    {
        return ['photo' => 'patient photo'];
    }
}
