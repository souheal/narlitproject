<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class GeneratePayoutBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'month' => ['required', 'date_format:Y-m'],
            'preview' => ['sometimes', 'boolean'],
        ];
    }
}
