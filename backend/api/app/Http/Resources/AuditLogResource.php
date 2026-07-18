<?php

namespace App\Http\Resources;

use App\Services\AuditLogPresentationSanitizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return app(AuditLogPresentationSanitizer::class)->sanitize($this->resource);
    }
}
