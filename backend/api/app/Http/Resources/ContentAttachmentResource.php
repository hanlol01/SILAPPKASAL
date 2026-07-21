<?php

namespace App\Http\Resources;

use App\Support\ContentAttachmentFilename;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'purpose' => $this->purpose?->value,
            'filename' => ContentAttachmentFilename::for($this->resource),
            'mime_type' => $this->detected_mime,
            'extension' => $this->extension,
            'size' => $this->file_size,
            'width' => $this->width,
            'height' => $this->height,
            'alt_text' => $this->alt_text,
            'display_order' => $this->display_order,
            'download_url' => route('content.attachments.download', ['attachment' => $this->public_id], false),
        ];
    }
}
