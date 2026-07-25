<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseMinuteInternalResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $anonymized = (new CaseMinuteAnonymizedResource($this->resource))->resolve($request);

        return [
            ...$anonymized,
            'projection' => 'internal',
            'internal_summary' => $this->internal_summary,
            'case' => [
                'case_number' => $this->case?->case_number,
            ],
            'supersedes' => $this->supersedes ? [
                'public_id' => $this->supersedes->public_id,
                'version' => $this->supersedes->version,
            ] : null,
            'creator' => $this->actorReference($this->creator),
            'updater' => $this->actorReference($this->updater),
            'finalizer' => $this->actorReference($this->finalizer),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
            'lock_version' => $this->lockVersion(),
            'capabilities' => $this->getAttribute('case_minute_capabilities') ?? [],
        ];
    }

    /** @return array{id: int}|null */
    private function actorReference(mixed $actor): ?array
    {
        return $actor === null ? null : ['id' => (int) $actor->id];
    }
}
