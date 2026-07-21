<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContentManagementActionRequest;
use App\Http\Requests\ContentManagementIndexRequest;
use App\Http\Requests\StoreContentAttachmentRequest;
use App\Http\Requests\StoreContentItemRequest;
use App\Http\Requests\UpdateContentDraftRequest;
use App\Http\Resources\ContentAttachmentResource;
use App\Http\Resources\ContentManagementConsultationOptionResource;
use App\Http\Resources\ContentManagementDetailResource;
use App\Http\Resources\ContentManagementResource;
use App\Models\ContentAttachment;
use App\Models\ContentItem;
use App\Models\ContentVersion;
use App\Services\ContentAttachmentService;
use App\Services\ContentManagementQueryService;
use App\Services\ContentPublicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentManagementController extends Controller
{
    public function __construct(
        private readonly ContentPublicationService $publication,
        private readonly ContentAttachmentService $attachments,
        private readonly ContentManagementQueryService $queries,
    ) {}

    public function index(ContentManagementIndexRequest $request): JsonResponse
    {
        $items = $this->queries->items($request->user(), $request->validated());

        return $this->response(ContentManagementResource::collection($items->items()), 'Campus content retrieved successfully', 200, [
            'current_page' => $items->currentPage(),
            'per_page' => $items->perPage(),
            'total' => $items->total(),
            'last_page' => $items->lastPage(),
        ]);
    }

    public function summary(ContentManagementActionRequest $request): JsonResponse
    {
        return $this->response($this->queries->summary($request->user()), 'Campus content summary retrieved successfully');
    }

    public function show(ContentManagementActionRequest $request, ContentItem $item): JsonResponse
    {
        return $this->response(
            new ContentManagementDetailResource($this->queries->item($request->user(), $item)),
            'Campus content detail retrieved successfully',
        );
    }

    public function consultationOptions(ContentManagementActionRequest $request): JsonResponse
    {
        return $this->response(
            ContentManagementConsultationOptionResource::collection($this->queries->eligibleConsultations($request->user())),
            'Eligible Consultation choices retrieved successfully',
        );
    }

    public function store(StoreContentItemRequest $request): JsonResponse
    {
        $item = $this->publication->createDraft($request->user(), $request->validated());

        return $this->response(new ContentManagementResource($item), 'Content draft created successfully', 201);
    }

    public function update(UpdateContentDraftRequest $request, ContentVersion $version): JsonResponse
    {
        $item = $this->publication->updateDraft($version, $request->user(), $request->validated());

        return $this->response(new ContentManagementResource($item), 'Content draft updated successfully');
    }

    public function submit(Request $request, ContentVersion $version): JsonResponse
    {
        $item = $this->publication->submit($version, $request->user());

        return $this->response(new ContentManagementResource($item), 'Content submitted for review successfully');
    }

    public function createRevision(ContentManagementActionRequest $request, ContentItem $item): JsonResponse
    {
        $item = $this->publication->createRevision($item, $request->user());

        return $this->response(new ContentManagementResource($item), 'Content revision created successfully', 201);
    }

    public function upload(StoreContentAttachmentRequest $request, ContentVersion $version): JsonResponse
    {
        $attachment = $this->attachments->upload(
            $version,
            $request->user(),
            $request->file('file'),
            $request->safe()->except('file'),
        );

        return $this->response(new ContentAttachmentResource($attachment), 'Content attachment uploaded successfully', 201);
    }

    public function removeAttachment(ContentManagementActionRequest $request, ContentAttachment $attachment): JsonResponse
    {
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
