<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContentPublishedIndexRequest;
use App\Http\Resources\ContentCategoryResource;
use App\Http\Resources\ContentSectionResource;
use App\Http\Resources\PublishedArticleResource;
use App\Http\Resources\PublishedConsultationResource;
use App\Http\Resources\PublishedFaqResource;
use App\Services\PublishedContentQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ContentController extends Controller
{
    public function __construct(private readonly PublishedContentQueryService $queries) {}

    public function sections(Request $request): JsonResponse
    {
        return $this->success(ContentSectionResource::collection($this->queries->sections($request->user())));
    }

    public function categories(ContentPublishedIndexRequest $request): JsonResponse
    {
        return $this->success(ContentCategoryResource::collection(
            $this->queries->categories($request->user(), $request->validated('section'))
        ));
    }

    public function articles(ContentPublishedIndexRequest $request): JsonResponse
    {
        $items = $this->queries->articles($request->user(), $request->validated());

        return $this->success(PublishedArticleResource::collection($items->items()), [
            'current_page' => $items->currentPage(),
            'per_page' => $items->perPage(),
            'total' => $items->total(),
            'last_page' => $items->lastPage(),
        ]);
    }

    public function articleCategories(ContentPublishedIndexRequest $request): JsonResponse
    {
        $section = (string) $request->validated('section', '');
        if (! in_array($section, ['education', 'policy'], true)) {
            throw ValidationException::withMessages([
                'section' => ['The section must be education or policy.'],
            ]);
        }

        return $this->success($this->queries->articleCategories($request->user(), $section));
    }

    public function article(Request $request, string $publicId): JsonResponse
    {
        return $this->articleResponse($request, $this->queries->article($request->user(), $publicId));
    }

    public function articleBySlug(Request $request, string $section, string $slug): JsonResponse
    {
        return $this->articleResponse(
            $request,
            $this->queries->articleBySlug($request->user(), $section, $slug),
        );
    }

    private function articleResponse(Request $request, mixed $article): JsonResponse
    {
        $article->setAttribute('content_detail', true);
        $article->setRelation('relatedArticles', $this->queries->relatedArticles($request->user(), $article));

        return $this->success(new PublishedArticleResource($article));
    }

    public function faqs(ContentPublishedIndexRequest $request): JsonResponse
    {
        $items = $this->queries->faqs($request->user(), $request->validated());

        return $this->success(PublishedFaqResource::collection($items->items()), [
            'current_page' => $items->currentPage(),
            'per_page' => $items->perPage(),
            'total' => $items->total(),
            'last_page' => $items->lastPage(),
        ]);
    }

    public function consultation(Request $request): JsonResponse
    {
        return $this->success(PublishedConsultationResource::collection(
            $this->queries->consultation($request->user())
        ));
    }

    public function featured(ContentPublishedIndexRequest $request): JsonResponse
    {
        $items = $this->queries->featured($request->user(), $request->validated());
        $items->each->setAttribute('is_featured', true);

        return $this->success(PublishedArticleResource::collection($items));
    }

    private function success(mixed $data, ?array $meta = null): JsonResponse
    {
        $response = ['success' => true, 'message' => 'Published content retrieved successfully', 'data' => $data];
        if ($meta !== null) {
            $response['meta'] = $meta;
        }

        return response()->json($response)->withHeaders([
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
