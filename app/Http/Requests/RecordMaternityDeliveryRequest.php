<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RecordMaternityDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('hospital_supplied_delivery_pack')) {
            $this->merge([
                'hospital_supplied_delivery_pack' => $this->boolean('hospital_supplied_delivery_pack'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'delivery_at' => ['required', 'date', 'before_or_equal:now'],
            'delivery_mode' => ['required', Rule::in(['vaginal', 'assisted_vaginal', 'cesarean', 'other'])],
            'delivery_notes' => ['nullable', 'string', 'max:5000'],
            'hospital_supplied_delivery_pack' => ['required', 'boolean'],
            'delivery_material_notes' => ['nullable', 'string', 'max:2000'],
            'newborns' => ['required', 'array', 'min:1', 'max:20'],
            'newborns.*' => ['required', 'array'],
            'newborns.*.outcome' => ['required', Rule::in(['live_birth', 'stillbirth'])],
            'newborns.*.first_name' => ['nullable', 'string', 'max:100'],
            'newborns.*.last_name' => ['nullable', 'string', 'max:100'],
            'newborns.*.gender' => ['nullable', Rule::in(['male', 'female'])],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $newborns = $this->input('newborns', []);
                if (! is_array($newborns)) {
                    return;
                }

                foreach ($newborns as $index => $newborn) {
                    if (! is_array($newborn)) {
                        continue;
                    }

                    if (($newborn['outcome'] ?? null) !== 'live_birth') {
                        continue;
                    }

                    foreach (['first_name', 'last_name', 'gender'] as $field) {
                        if (blank($newborn[$field] ?? null)) {
                            $validator->errors()->add(
                                "newborns.{$index}.{$field}",
                                'A name and sex are required to create a patient record for a live-born baby.',
                            );
                        }
                    }
                }
            },
        ];
    }
}
