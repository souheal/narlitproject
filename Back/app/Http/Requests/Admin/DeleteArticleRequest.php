<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeleteArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            // The admin UI makes the operator type DELETE; enforce it server-side too,
            // since this permanently removes the row.
            'confirm' => ['required', 'string', Rule::in(['DELETE'])],
        ];
    }

    public function messages(): array
    {
        return [
            'confirm.in' => 'Type DELETE exactly to confirm permanent deletion.',
        ];
    }
}
