<?php

namespace App\Http\Resources\Organization;

use App\Models\Article;
use Illuminate\Http\Request;

class OrganizationArticleDetailResource extends OrganizationArticleResource
{
    public function toArray(Request $request): array
    {
        /** @var Article $article */
        $article = $this->resource;

        return array_merge(parent::toArray($request), [
            'content' => $article->content,
        ]);
    }
}
