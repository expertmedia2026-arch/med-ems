<?php

namespace App\Http\Requests;

use App\Models\LabRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveLabResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'result' => ['nullable', 'string', 'max:5000'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'files.*.mimes' => 'Only JPG, PNG, WebP or PDF files are allowed.',
            'files.*.max' => 'Each file must be 10 MB or smaller.',
            'files.*.uploaded' => 'Upload failed. The file may be larger than the server allows.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            /** @var LabRequest|null $labRequest */
            $labRequest = $this->route('labRequest');
            if (blank($this->input('result')) && ! $this->hasFile('files') && $labRequest?->attachments()->doesntExist()) {
                $validator->errors()->add('result', 'Enter a result or attach at least one file.');
            }
        }];
    }
}
