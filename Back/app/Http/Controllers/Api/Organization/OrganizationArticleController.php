<?php

namespace App\Http\Controllers\Api\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\OrganizationArticleIndexRequest;
use App\Http\Requests\Organization\StoreOrganizationArticleRequest;
use App\Http\Requests\Organization\UpdateOrganizationArticleRequest;
use App\Http\Resources\Organization\OrganizationArticleDetailResource;
use App\Http\Resources\Organization\OrganizationArticleResource;
use App\Services\Organization\OrganizationArticleService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationArticleController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected OrganizationArticleService $articles,
    ) {
    }

    public function index(OrganizationArticleIndexRequest $request): JsonResponse
    {
        return $this->success('Articles retrieved successfully.', [
            'articles' => OrganizationArticleResource::collection(
                $this->articles->paginate($request->user()->organizationProfile, $request)
            )->response()->getData(true),
        ]);
    }

    public function store(StoreOrganizationArticleRequest $request): JsonResponse
    {
        return $this->success('Article draft created successfully.', [
            'article' => new OrganizationArticleDetailResource(
                $this->articles->create($request->user()->organizationProfile, $request->validated())
            ),
        ], 201);
    }

    public function show(Request $request, string $article): JsonResponse
    {
        return $this->success('Article retrieved successfully.', [
            'article' => new OrganizationArticleDetailResource(
                $this->articles->details($request->user()->organizationProfile, $article)
            ),
        ]);
    }

    public function update(UpdateOrganizationArticleRequest $request, string $article): JsonResponse
    {
        return $this->success('Article updated successfully.', [
            'article' => new OrganizationArticleDetailResource(
                $this->articles->update($request->user()->organizationProfile, $article, $request->validated())
            ),
        ]);
    }

    public function submit(Request $request, string $article): JsonResponse
    {
        return $this->success('Article submitted for review successfully.', [
            'article' => new OrganizationArticleResource(
                $this->articles->submit($request->user()->organizationProfile, $request->user(), $article, $request)
            ),
        ]);
    }
}
