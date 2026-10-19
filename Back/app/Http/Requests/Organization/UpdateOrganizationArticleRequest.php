<?php

namespace App\Http\Requests\Organization;

class UpdateOrganizationArticleRequest extends StoreOrganizationArticleRequest
{
    public function rules(): array
    {
        return array_merge($this->authorFieldRules(false), $this->prohibitedFieldRules());
    }
}
