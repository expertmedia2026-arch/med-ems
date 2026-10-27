<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHospitalSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [
            'clear_paystack_secret_key' => $this->boolean('clear_paystack_secret_key'),
            'remove_logo' => $this->boolean('remove_logo'),
            'remove_administrator_signature' => $this->boolean('remove_administrator_signature'),
        ];

        if (! $this->is('api/*')) {
            $normalized['use_accent_color'] = $this->boolean('use_accent_color');
            $normalized['use_highlight_color'] = $this->boolean('use_highlight_color');
        }

        $this->merge($normalized);
    }

    public function rules(): array
    {
        $section = $this->route('section');
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'website' => ['nullable', 'string', 'max:150'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'administrator_signature' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'registration_fee' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'emergency_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'mortuary_intake_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'mortuary_daily_storage_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'state_mortuary_tax_daily_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'oxygen_per_administration_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'nebulizer_fee_per_three_uses' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'sugar_check_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'paystack_public_key' => ['nullable', 'string', 'max:255'],
            'paystack_secret_key' => ['nullable', 'string', 'max:1000'],
            'clear_paystack_secret_key' => ['boolean'],
            'remove_logo' => ['boolean'],
            'remove_administrator_signature' => ['boolean'],
        ];

        if ($this->is('api/*')) {
            return $rules + [
                'payment_gateway' => ['nullable', Rule::in(['paystack', 'manual'])],
                'primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'secondary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'accent_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'highlight_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'use_accent_color' => ['boolean'],
                'use_highlight_color' => ['boolean'],
            ];
        }

        return match ($section ?? 'basic') {
            'payments' => [
                'payment_gateway' => $this->user()?->hospital?->hasModule('online_payments')
                    ? ['required', Rule::in(['paystack', 'manual'])]
                    : ['nullable', Rule::in(['paystack', 'manual'])],
                'registration_fee' => ['required', 'numeric', 'min:0', 'max:99999999'],
                'emergency_fee' => ['required', 'numeric', 'min:0', 'max:99999999'],
                'mortuary_intake_fee' => ['required', 'numeric', 'min:0', 'max:99999999'],
                'mortuary_daily_storage_fee' => ['required', 'numeric', 'min:0', 'max:99999999'],
                'state_mortuary_tax_daily_fee' => ['required', 'numeric', 'min:0', 'max:99999999'],
                'oxygen_per_administration_fee' => ['required', 'numeric', 'min:0', 'max:99999999'],
                'nebulizer_fee_per_three_uses' => ['required', 'numeric', 'min:0', 'max:99999999'],
                'sugar_check_fee' => ['required', 'numeric', 'min:0', 'max:99999999'],
                'paystack_public_key' => ['nullable', 'string', 'max:255'],
                'paystack_secret_key' => ['nullable', 'string', 'max:1000'],
                'clear_paystack_secret_key' => ['boolean'],
            ],
            'branding' => [
                'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'accent_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'highlight_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'use_accent_color' => ['boolean'],
                'use_highlight_color' => ['boolean'],
                'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
                'administrator_signature' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
                'remove_logo' => ['boolean'],
                'remove_administrator_signature' => ['boolean'],
            ],
            default => [
                'name' => ['required', 'string', 'max:150'],
                'address' => ['nullable', 'string', 'max:500'],
                'phone' => ['nullable', 'string', 'max:50'],
                'email' => ['nullable', 'email', 'max:150'],
                'website' => ['nullable', 'string', 'max:150'],
                'receipt_footer' => ['nullable', 'string', 'max:255'],
            ],
        };
    }
}
