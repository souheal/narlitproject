<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminArticleDeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'confirm' => ['required', 'string', 'in:DELETE'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirm.in' => 'Type DELETE exactly to confirm permanent deletion.',
        ];
    }
}
