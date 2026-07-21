<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContentAttachment;
use App\Services\ContentAttachmentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContentAttachmentController extends Controller
{
    public function __construct(private readonly ContentAttachmentService $attachments) {}

    public function download(Request $request, string $attachment): StreamedResponse
    {
        $record = ContentAttachment::query()
            ->where('public_id', $attachment)
            ->firstOrFail();

        return $this->attachments->download($record, $request->user());
    }
}
