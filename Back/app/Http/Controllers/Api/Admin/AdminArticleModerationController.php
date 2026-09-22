<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminArticleActionRequest;
use App\Http\Requests\Admin\AdminArticleIndexRequest;
use App\Http\Requests\Admin\DeleteArticleRequest;
use App\Http\Requests\Admin\ModerateArticleRequest;
use App\Http\Requests\Admin\UpdateArticleRequest;
use App\Http\Resources\Admin\AdminArticleDetailResource;
use App\Http\Resources\Admin\AdminArticleResource;
use App\Services\Admin\AdminArticleModerationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminArticleModerationController extends Controller
{
    use ApiResponse;

    public function index(AdminArticleIndexRequest $request, AdminArticleModerationService $articles): JsonResponse
    {
        return $this->success('Articles retrieved successfully.', [
            'articles' => AdminArticleResource::collection($articles->paginate($request))->response()->getData(true),
        ]);
    }

    public function show(string $publicId, AdminArticleModerationService $articles): JsonResponse
    {
        return $this->success('Article retrieved successfully.', [
            'article' => new AdminArticleDetailResource($articles->details($publicId)),
        ]);
    }

    public function approve(string $publicId, AdminArticleActionRequest $request, AdminArticleModerationService $articles): JsonResponse
    {
        return $this->articleResponse('Article approved successfully.', $articles->approve($request->user(), $publicId, $request));
    }

    public function reject(string $publicId, ModerateArticleRequest $request, AdminArticleModerationService $articles): JsonResponse
    {
        return $this->articleResponse('Article rejected successfully.', $articles->reject($request->user(), $publicId, $request->validated('reason'), $request));
    }

    public function requestChanges(string $publicId, ModerateArticleRequest $request, AdminArticleModerationService $articles): JsonResponse
    {
        return $this->articleResponse('Article returned for changes successfully.', $articles->requestChanges($request->user(), $publicId, $request->validated('reason'), $request));
    }

    public function publish(string $publicId, AdminArticleActionRequest $request, AdminArticleModerationService $articles): JsonResponse
    {
        return $this->articleResponse('Article published successfully.', $articles->publish($request->user(), $publicId, $request));
    }

    public function feature(string $publicId, AdminArticleActionRequest $request, AdminArticleModerationService $articles): JsonResponse
    {
        return $this->articleResponse('Article featured successfully.', $articles->feature($request->user(), $publicId, $request));
    }

    public function unfeature(string $publicId, AdminArticleActionRequest $request, AdminArticleModerationService $articles): JsonResponse
    {
        return $this->articleResponse('Article unfeatured successfully.', $articles->unfeature($request->user(), $publicId, $request));
    }

    public function archive(string $publicId, AdminArticleActionRequest $request, AdminArticleModerationService $articles): JsonResponse
    {
        return $this->articleResponse('Article archived successfully.', $articles->archive($request->user(), $publicId, $request));
    }

    public function restore(string $publicId, AdminArticleActionRequest $request, AdminArticleModerationService $articles): JsonResponse
    {
        return $this->articleResponse('Article restored successfully.', $articles->restore($request->user(), $publicId, $request));
    }

    public function update(string $publicId, UpdateArticleRequest $request, AdminArticleModerationService $articles): JsonResponse
    {
        $article = $articles->update($request->user(), $publicId, $request->validated(), $request);

        return $this->success('Article updated successfully.', [
            'article' => new AdminArticleDetailResource($articles->details($article->public_id)),
        ]);
    }

    public function destroy(string $publicId, DeleteArticleRequest $request, AdminArticleModerationService $articles): JsonResponse
    {
        $articles->destroy($request->user(), $publicId, $request->validated()['reason'], $request);

        return $this->success('Article deleted permanently.');
    }

    protected function articleResponse(string $message, object $article): JsonResponse
    {
        return $this->success($message, [
            'article' => new AdminArticleResource($article->loadMissing('organizationProfile.user')),
        ]);
    }
}
