<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'organization_name' => trim((string) $this->input('organization_name')),
            'email' => strtolower(trim((string) $this->input('email'))),
            'phone' => trim((string) $this->input('phone')),
            'website' => $this->filled('website') ? trim((string) $this->input('website')) : null,
            'tax_id' => preg_replace('/[^0-9]/', '', (string) $this->input('tax_id')),
        ]);
    }

    public function rules(): array
    {
        return [
            'organization_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{8,15}$/'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'website' => ['nullable', 'url', 'max:2048'],
            'tax_id' => [
                'required',
                'digits:9',
                Rule::unique('organization_profiles', 'tax_id')->whereNull('deleted_at'),
            ],
            'certificate_pdf' => ['required', 'file', 'mimetypes:application/pdf', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'organization_name.required' => 'Please enter the organization name.',
            'organization_name.max' => 'The organization name is too long.',
            'email.required' => 'Please enter a valid email address.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'An account with this email address already exists.',
            'phone.required' => 'Please enter your phone number.',
            'phone.regex' => 'Please enter a valid phone number.',
            'password.required' => 'Please enter a password.',
            'password.confirmed' => 'Your password confirmation does not match.',
            'website.url' => 'Please enter a valid website URL.',
            'tax_id.required' => 'Please enter the organization tax ID.',
            'tax_id.digits' => 'Please enter a valid 9-digit tax ID.',
            'tax_id.unique' => 'An organization with this tax ID already exists.',
            'certificate_pdf.required' => 'Please upload the certificate of incorporation PDF.',
            'certificate_pdf.file' => 'Please upload a valid certificate of incorporation PDF.',
            'certificate_pdf.mimetypes' => 'The certificate of incorporation must be a PDF file.',
            'certificate_pdf.max' => 'The certificate of incorporation PDF must be 10 MB or smaller.',
        ];
    }
}
