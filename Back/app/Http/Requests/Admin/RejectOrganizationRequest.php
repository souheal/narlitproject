<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RejectOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reason' => trim((string) $this->input('reason')),
        ]);
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Please enter a rejection reason.',
            'reason.max' => 'The rejection reason is too long.',
        ];
    }
}
