<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TriageQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ];
    }
}
