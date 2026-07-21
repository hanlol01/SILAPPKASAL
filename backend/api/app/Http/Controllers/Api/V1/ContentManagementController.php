<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContentAttachmentRequest;
use App\Http\Requests\StoreContentItemRequest;
use App\Http\Requests\UpdateContentDraftRequest;
use App\Http\Resources\ContentAttachmentResource;
use App\Http\Resources\ContentManagementResource;
use App\Models\ContentVersion;
use App\Services\ContentAttachmentService;
use App\Services\ContentPublicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentManagementController extends Controller
{
    public function __construct(
        private readonly ContentPublicationService $publication,
        private readonly ContentAttachmentService $attachments,
    ) {}

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

    private function response(mixed $data, string $message, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status)->withHeaders([
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
