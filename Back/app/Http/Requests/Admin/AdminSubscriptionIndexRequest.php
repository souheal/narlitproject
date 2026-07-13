<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminSubscriptionIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'plan' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', Rule::in(['active', 'canceled', 'past_due', 'unpaid', 'incomplete'])],
            'currency' => ['nullable', 'string', 'size:3'],
            'started_from' => ['nullable', 'date'],
            'started_to' => ['nullable', 'date', 'after_or_equal:started_from'],
            'sort' => ['nullable', Rule::in(['date', 'amount', 'subscriber', 'status', 'renewal'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
