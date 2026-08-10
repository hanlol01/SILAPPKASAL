<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContentCategoryIndexRequest;
use App\Http\Requests\ContentManagementActionRequest;
use App\Http\Requests\ContentManagementIndexRequest;
use App\Http\Requests\StoreContentAttachmentRequest;
use App\Http\Requests\StoreContentCategoryRequest;
use App\Http\Requests\StoreContentItemRequest;
use App\Http\Requests\SubmitContentVersionRequest;
use App\Http\Requests\UpdateContentDraftRequest;
use App\Http\Resources\ContentAttachmentResource;
use App\Http\Resources\ContentManagementDetailResource;
use App\Http\Resources\ContentManagementResource;
use App\Services\ContentAttachmentService;
use App\Services\ContentCategoryRegistryService;
use App\Services\ContentManagementQueryService;
use App\Services\ContentPublicationService;
use Illuminate\Http\JsonResponse;

class ContentManagementController extends Controller
{
    public function __construct(
        private readonly ContentPublicationService $publication,
        private readonly ContentAttachmentService $attachments,
        private readonly ContentManagementQueryService $queries,
        private readonly ContentCategoryRegistryService $categories,
    ) {}

    public function index(ContentManagementIndexRequest $request): JsonResponse
    {
        $items = $this->queries->items($request->user(), $request->validated());

        return $this->response(ContentManagementResource::collection($items->items()), 'Managed content retrieved successfully', 200, [
            'current_page' => $items->currentPage(),
            'per_page' => $items->perPage(),
            'total' => $items->total(),
            'last_page' => $items->lastPage(),
        ]);
    }

    public function summary(ContentManagementActionRequest $request): JsonResponse
    {
        return $this->response($this->queries->summary($request->user()), 'Managed content summary retrieved successfully');
    }

    public function show(ContentManagementActionRequest $request, string $item): JsonResponse
    {
        return $this->response(
            new ContentManagementDetailResource($this->queries->item($request->user(), $item)),
            'Managed content detail retrieved successfully',
        );
    }

    public function articleCategories(ContentCategoryIndexRequest $request): JsonResponse
    {
        return $this->response(
            $this->categories->categories($request->user(), $request->validated('section')),
            'Managed Article categories retrieved successfully',
        );
    }

    public function storeArticleCategory(StoreContentCategoryRequest $request): JsonResponse
    {
        $outcome = $this->categories->create(
            $request->user(),
            $request->validated('section'),
            $request->validated('name'),
        );
        $category = $outcome['category'];
        $created = $outcome['result'] === 'created';

        return $this->response([
            'public_id' => $category->public_id,
            'name' => $category->name,
            'section_code' => $category->section?->code,
            'scope' => $category->scope->value,
            'usage_count' => $outcome['usage_count'],
            'can_manage' => true,
            'can_deactivate' => $outcome['can_deactivate'],
            'result' => $outcome['result'],
        ], $created ? 'Article category created successfully' : 'Article category retrieved successfully', $created ? 201 : 200);
    }

    public function destroyArticleCategory(ContentManagementActionRequest $request, string $category): JsonResponse
    {
        $this->categories->deactivate($request->user(), $category);

        return $this->response(null, 'Article category deactivated successfully');
    }

    public function capabilities(ContentManagementActionRequest $request): JsonResponse
    {
        $this->queries->summary($request->user());

        return $this->response([
            'image_upload_available' => $this->attachments->imageUploadsAvailable(),
            'image_formats' => $this->attachments->supportedImageMimeTypes(),
            'cover_max_bytes' => (int) config('content.attachments.cover_max_bytes'),
            'inline_image_max_bytes' => (int) config('content.attachments.inline_image_max_bytes'),
            'max_image_source_bytes' => (int) config('content.attachments.max_image_source_bytes'),
            'alt_text_max_length' => (int) config('content.attachments.alt_text_max_length', 500),
        ], 'Content management capabilities retrieved successfully');
    }

    public function store(StoreContentItemRequest $request): JsonResponse
    {
        $item = $this->publication->createDraft($request->user(), $request->validated());

        return $this->response(new ContentManagementResource($item), 'Content draft created successfully', 201);
    }

    public function update(UpdateContentDraftRequest $request, string $version): JsonResponse
    {
        $version = $this->queries->version($request->user(), $version);
        $item = $this->publication->updateDraft($version, $request->user(), $request->validated());

        return $this->response(new ContentManagementResource($item), 'Content draft updated successfully');
    }

    public function submit(SubmitContentVersionRequest $request, string $version): JsonResponse
    {
        $version = $this->queries->version($request->user(), $version);
        $item = $this->publication->submit(
            $version,
            $request->user(),
            (int) $request->validated('lock_version'),
        );

        return $this->response(new ContentManagementResource($item), 'Content submitted for review successfully');
    }

    public function createRevision(SubmitContentVersionRequest $request, string $item): JsonResponse
    {
        $item = $this->queries->itemModel($request->user(), $item);
        $item = $this->publication->createRevision(
            $item,
            $request->user(),
            (int) $request->validated('lock_version'),
        );

        return $this->response(new ContentManagementResource($item), 'Content revision created successfully', 201);
    }

    public function upload(StoreContentAttachmentRequest $request, string $version): JsonResponse
    {
        $version = $this->queries->version($request->user(), $version);
        $attachment = $this->attachments->upload(
            $version,
            $request->user(),
            $request->file('file'),
            $request->safe()->except('file'),
        );

        return $this->response(new ContentAttachmentResource($attachment), 'Content attachment uploaded successfully', 201);
    }

    public function removeAttachment(ContentManagementActionRequest $request, string $attachment): JsonResponse
    {
        $attachment = $this->queries->attachment($request->user(), $attachment);
        $this->attachments->remove($attachment, $request->user());

        return $this->response(null, 'Content attachment removed successfully');
    }

    private function response(mixed $data, string $message, int $status = 200, ?array $meta = null): JsonResponse
    {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status)->withHeaders([
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
