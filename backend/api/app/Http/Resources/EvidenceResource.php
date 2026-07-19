<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvidenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $originalFilename = $this->resource->getAttribute('oversight_filename')
            ?? $this->original_filename;

        return [
            'id' => $this->id,
            'investigation_id' => $this->investigation_id,
            'case_id' => $this->whenLoaded('investigation', fn () => $this->investigation?->case_id),
            'case_number' => $this->whenLoaded('investigation', fn () => $this->investigation?->case?->case_number),
            'evidence_type' => $this->whenLoaded('evidenceType', fn (): array => [
                'code' => $this->evidenceType->code,
                'name' => $this->evidenceType->name,
                'description' => $this->evidenceType->description,
            ]),
            'title' => $this->title,
            'description' => $this->description,
            'source' => $this->source,
            'collected_at' => $this->collected_at?->toJSON(),
            'classification' => $this->classification,
            'status' => $this->status,
            'status_semantics' => $this->status === 'verified'
                ? 'metadata_reviewed_admin_complete_not_forensic_authenticity'
                : null,
            'file_metadata' => [
                'original_filename' => $originalFilename,
                'mime_type' => $this->mime_type,
                'file_size' => $this->file_size,
                'checksum_sha256' => $this->checksum_sha256,
            ],
            'file_attachment' => $this->storage_path === null ? null : [
                'original_filename' => $originalFilename,
                'mime_type' => $this->mime_type,
                'file_size' => $this->file_size,
                'uploaded_at' => $this->file_uploaded_at?->toJSON(),
                'uploaded_by' => $this->whenLoaded('fileUploader', fn (): ?array => $this->fileUploader ? [
                    'id' => $this->fileUploader->id,
                    'name' => $this->fileUploader->name,
                ] : null),
            ],
            'submitted_by' => $this->whenLoaded('submitter', fn (): array => [
                'id' => $this->submitter->id,
                'name' => $this->submitter->name,
            ]),
            'status_history' => EvidenceStatusHistoryResource::collection($this->whenLoaded('statusHistories')),
            'custody_events' => EvidenceCustodyEventResource::collection($this->whenLoaded('custodyEvents')),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
