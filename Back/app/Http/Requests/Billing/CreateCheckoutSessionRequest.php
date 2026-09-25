<?php

namespace App\Http\Requests\Billing;

use App\Services\Billing\PublicSubscriptionPlanService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCheckoutSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'subscription_plan' => $this->input('subscription_plan', 'monthly'),
        ]);
    }

    public function rules(): array
    {
        $keys = array_column(app(PublicSubscriptionPlanService::class)->enabledPlans(), 'key');

        if ($keys === []) {
            $keys = ['monthly', 'yearly'];
        }

        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'subscription_plan' => ['sometimes', Rule::in($keys)],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'subscription_plan.in' => 'Please choose a valid subscription plan.',
        ];
    }
}
