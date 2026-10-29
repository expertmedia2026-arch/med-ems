<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RecordMaternityDeliveryRequest extends FormRequest
{
    /** The route restricts the module and role; the service checks access to the admission. */
    public function authorize(): bool
    {
        return true;
    }

    /** Normalize HTML radio values so they validate consistently with JSON booleans. */
    protected function prepareForValidation(): void
    {
        if ($this->has('hospital_supplied_delivery_pack')) {
            $this->merge([
                'hospital_supplied_delivery_pack' => $this->boolean('hospital_supplied_delivery_pack'),
            ]);
        }
    }

    /** Validate delivery details, whether a hospital pack was supplied, and each newborn entry. */
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

    /** Require identifying details only when a live-born baby's patient record will be created. */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $newborns = $this->input('newborns', []);
                if (! is_array($newborns)) {
                    return;
                }

                // Stillbirth entries deliberately do not create patient records,
                // so name and sex are required only for live births.
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
