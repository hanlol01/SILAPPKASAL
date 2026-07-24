<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportWithdrawalAttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'attachment_reference' => $this->public_id,
            'document_type' => $this->document_type?->value,
            'version' => $this->version,
            'mime_type' => $this->server_mime,
            'size' => $this->size,
            'uploaded_at' => $this->created_at?->toJSON(),
        ];
    }
}
