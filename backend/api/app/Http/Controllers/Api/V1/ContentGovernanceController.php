<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ContentScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\ContentGovernanceActionRequest;
use App\Http\Requests\ContentGovernanceApprovalRequest;
use App\Http\Requests\ContentGovernanceIndexRequest;
use App\Http\Requests\ContentGovernanceReasonRequest;
use App\Http\Requests\DeleteFeaturedContentRequest;
use App\Http\Requests\FeaturedContentIndexRequest;
use App\Http\Requests\StoreFeaturedContentRequest;
use App\Http\Requests\UpdateFeaturedContentRequest;
use App\Http\Resources\ContentGovernanceDetailResource;
use App\Http\Resources\ContentGovernanceResource;
use App\Http\Resources\FeaturedContentGovernanceResource;
use App\Http\Resources\FeaturedEligibleContentResource;
use App\Http\Resources\PublishedContentGovernanceResource;
use App\Services\ContentGovernanceQueryService;
use App\Services\ContentPublicationService;
use App\Services\FeaturedContentGovernanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentGovernanceController extends Controller
{
    public function __construct(
        private readonly ContentGovernanceQueryService $queries,
        private readonly ContentPublicationService $publication,
        private readonly FeaturedContentGovernanceService $featured,
    ) {}

    public function reviews(ContentGovernanceIndexRequest $request): JsonResponse
    {
        $items = $this->queries->reviewQueue($request->user(), $request->validated());

        return $this->response(ContentGovernanceResource::collection($items->items()), 'Content review queue retrieved successfully', 200, [
            'current_page' => $items->currentPage(),
            'per_page' => $items->perPage(),
            'total' => $items->total(),
            'last_page' => $items->lastPage(),
        ]);
    }

    public function published(ContentGovernanceIndexRequest $request): JsonResponse
    {
        $items = $this->queries->publishedItems($request->user(), $request->validated());

        return $this->response(PublishedContentGovernanceResource::collection($items->items()), 'Published content governance list retrieved successfully', 200, [
            'current_page' => $items->currentPage(),
            'per_page' => $items->perPage(),
            'total' => $items->total(),
            'last_page' => $items->lastPage(),
        ]);
    }

    public function show(Request $request, string $item): JsonResponse
    {
        return $this->response(
            new ContentGovernanceDetailResource($this->queries->item($request->user(), $item)),
            'Content governance detail retrieved successfully',
        );
    }

    public function reviewCampuses(Request $request): JsonResponse
    {
        return $this->response(
            $this->queries->campuses($request->user())->map(fn ($university) => [
                'code' => $university->code,
                'name' => $university->name,
            ]),
            'Content governance campus choices retrieved successfully',
        );
    }

    public function reviewCategories(Request $request): JsonResponse
    {
        $section = $request->query('section');
        if ($section !== null && ! in_array($section, ['education', 'policy', 'faq', 'consultation'], true)) {
            abort(422, 'The selected section is invalid.');
        }

        return $this->response(
            $this->queries->categories($request->user(), $section)->map(fn ($category) => [
                'public_id' => $category->public_id,
                'name' => $category->name,
                'section_code' => $category->section?->code,
                'scope' => $category->scope?->value,
                'university' => $category->university ? [
                    'code' => $category->university->code,
                    'name' => $category->university->name,
                ] : null,
            ]),
            'Content governance category choices retrieved successfully',
        );
    }

    public function startReview(ContentGovernanceActionRequest $request, string $version): JsonResponse
    {
        $version = $this->queries->reviewVersion($request->user(), $version);
        $item = $this->publication->startReview($version, $request->user(), (int) $request->validated('lock_version'));

        return $this->response(new ContentGovernanceResource($item), 'Content review started successfully');
    }

    public function requestRevision(ContentGovernanceReasonRequest $request, string $version): JsonResponse
    {
        $version = $this->queries->reviewVersion($request->user(), $version);
        $item = $this->publication->requestRevision(
            $version,
            $request->user(),
            (string) $request->validated('reason'),
            (int) $request->validated('lock_version'),
        );

        return $this->response(new ContentGovernanceResource($item), 'Content revision requested successfully');
    }

    public function reject(ContentGovernanceReasonRequest $request, string $version): JsonResponse
    {
        $version = $this->queries->reviewVersion($request->user(), $version);
        $item = $this->publication->reject(
            $version,
            $request->user(),
            (string) $request->validated('reason'),
            (int) $request->validated('lock_version'),
        );

        return $this->response(new ContentGovernanceResource($item), 'Content rejected successfully');
    }

    public function approve(ContentGovernanceApprovalRequest $request, string $version): JsonResponse
    {
        $version = $this->queries->reviewVersion($request->user(), $version);
        $item = $this->publication->approve(
            $version,
            $request->user(),
            (int) $request->validated('lock_version'),
            $request->validated('note'),
        );

        return $this->response(new ContentGovernanceResource($item), 'Content approved successfully');
    }

    public function publish(ContentGovernanceActionRequest $request, string $version): JsonResponse
    {
        $version = $this->queries->reviewVersion($request->user(), $version);
        $item = $this->publication->publishApproved($version, $request->user(), (int) $request->validated('lock_version'));

        return $this->response(new ContentGovernanceResource($item), 'Approved content published successfully');
    }

    public function archive(ContentGovernanceReasonRequest $request, string $item): JsonResponse
    {
        $item = $this->queries->reviewItem($request->user(), $item);
        $item = $this->publication->archive(
            $item,
            $request->user(),
            (string) $request->validated('reason'),
            (int) $request->validated('lock_version'),
        );

        return $this->response(new ContentGovernanceResource($item), 'Content archived successfully');
    }

    public function featured(FeaturedContentIndexRequest $request): JsonResponse
    {
        return $this->response(
            FeaturedContentGovernanceResource::collection($this->featured->placements($request->user(), $request->validated())),
            'Featured placements retrieved successfully',
        );
    }

    public function eligibleFeatured(FeaturedContentIndexRequest $request): JsonResponse
    {
        $scope = ContentScope::from((string) ($request->validated('scope') ?? ContentScope::Global->value));

        return $this->response(
            FeaturedEligibleContentResource::collection($this->featured->eligible(
                $request->user(),
                $scope,
                $request->validated('university_code'),
                $request->validated('search'),
            )),
            'Eligible featured content retrieved successfully',
        );
    }

    public function campuses(Request $request): JsonResponse
    {
        return $this->response(
            $this->featured->campuses($request->user())->map(fn ($university) => [
                'code' => $university->code,
                'name' => $university->name,
            ]),
            'Featured campus choices retrieved successfully',
        );
    }

    public function storeFeatured(StoreFeaturedContentRequest $request): JsonResponse
    {
        $placement = $this->featured->create($request->user(), $request->validated());

        return $this->response(new FeaturedContentGovernanceResource($placement), 'Featured placement created successfully', 201);
    }

    public function updateFeatured(UpdateFeaturedContentRequest $request, string $placement): JsonResponse
    {
        $placement = $this->featured->placement($request->user(), $placement);
        $placement = $this->featured->update($request->user(), $placement, $request->validated());

        return $this->response(new FeaturedContentGovernanceResource($placement), 'Featured placement updated successfully');
    }

    public function removeFeatured(DeleteFeaturedContentRequest $request, string $placement): JsonResponse
    {
        $placement = $this->featured->placement($request->user(), $placement);
        $this->featured->remove($request->user(), $placement, (string) $request->validated('concurrency_token'));

        return $this->response(null, 'Featured placement removed successfully');
    }

    private function response(mixed $data, string $message, int $status = 200, ?array $meta = null): JsonResponse
    {
        $payload = ['success' => true, 'message' => $message, 'data' => $data];
        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status)->withHeaders([
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
