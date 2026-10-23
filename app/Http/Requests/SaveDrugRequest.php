<?php

namespace App\Http\Requests;

use App\Models\Drug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDrugRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['is_fluid' => $this->boolean('is_fluid')]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Drug|null $drug */
        $drug = $this->route('drug');
        $rules = [
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('drugs', 'name')
                    ->where(fn ($query) => $query->where('strength', $this->input('strength'))->where('form', $this->input('form')))
                    ->ignore($drug?->id),
            ],
            'generic_name' => ['nullable', 'string', 'max:150'],
            'is_fluid' => ['boolean'],
            'form' => ['nullable', 'string', 'max:30'],
            'strength' => ['nullable', 'string', 'max:30'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'reorder_level' => ['required', 'integer', 'min:0', 'max:100000'],
        ];

        if (! $drug) {
            $rules['stock_quantity'] = ['required', 'integer', 'min:0', 'max:1000000'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return ['name.unique' => 'This drug already exists with the same strength and form.'];
    }
}
