<?php

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $values = [];

        if (is_string($this->input('title'))) {
            $values['title'] = trim($this->input('title'));
        }

        if (is_string($this->input('slug'))) {
            $values['slug'] = $this->filled('slug') ? str($this->input('slug'))->slug()->toString() : null;
        }

        if (is_string($this->input('excerpt'))) {
            $values['excerpt'] = $this->filled('excerpt') ? trim($this->input('excerpt')) : null;
        }

        if (is_string($this->input('category'))) {
            $values['category'] = $this->filled('category') ? trim($this->input('category')) : null;
        }

        if ($values !== []) {
            $this->merge($values);
        }
    }

    public function rules(): array
    {
        return array_merge($this->authorFieldRules(true), $this->prohibitedFieldRules());
    }

    protected function authorFieldRules(bool $required): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'title' => [$presence, 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'excerpt' => ['sometimes', 'nullable', 'string'],
            'content' => [$presence, 'string'],
            'category' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }

    protected function prohibitedFieldRules(): array
    {
        return collect([
            'id',
            'public_id',
            'organization_profile_id',
            'user_id',
            'status',
            'rejection_reason',
            'featured_at',
            'published_at',
            'read_time',
            'total_reads',
            'total_unique_reads',
            'total_reading_seconds',
            'total_points_generated',
            'metadata',
            'deleted_at',
        ])->mapWithKeys(fn (string $field): array => [$field => ['prohibited']])->all();
    }
}
