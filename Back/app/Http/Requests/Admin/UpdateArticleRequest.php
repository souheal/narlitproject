<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'min:3', 'max:255'],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'content' => ['sometimes', 'required', 'string', 'min:20'],
            'category' => ['sometimes', 'nullable', 'string', 'max:120'],
            'edit_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
