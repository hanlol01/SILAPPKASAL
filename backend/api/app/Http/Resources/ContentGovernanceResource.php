<?php

namespace App\Http\Resources;

use App\Enums\ContentLifecycleStatus;
use App\Policies\ContentItemPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentGovernanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $version = $this->currentDraftVersion ?? $this->latestVersion ?? $this->publishedVersion;

        return [
            'public_id' => $this->public_id,
            'content_type' => $this->content_type?->value,
            'scope' => $this->scope?->value,
            'section' => new ContentSectionResource($this->section),
            'category' => $this->category ? new ContentCategoryResource($this->category) : null,
            'university' => $this->university ? [
                'code' => $this->university->code,
                'name' => $this->university->name,
            ] : null,
            'author' => $version?->author ? [
                'name' => $version->author->name,
                'role' => $version->author->role?->code,
            ] : null,
            'lock_version' => $this->lock_version,
            'lifecycle_status' => $this->archived_at !== null
                ? ContentLifecycleStatus::Archived->value
                : $version?->lifecycle_status?->value,
            'version' => $version ? [
                'public_id' => $version->public_id,
                'version_number' => $version->version_number,
                'status' => $version->lifecycle_status?->value,
                'title' => $version->title,
                'excerpt' => $version->excerpt,
                'submitted_at' => $version->submitted_at?->toJSON(),
                'published_at' => $version->published_at?->toJSON(),
                'requires_editorial_review' => $version->requires_editorial_review,
            ] : null,
            'capabilities' => $this->capabilities($request, $version),
        ];
    }

    /** @return array<string, bool> */
    protected function capabilities(Request $request, mixed $version): array
    {
        $actor = $request->user();
        $policy = app(ContentItemPolicy::class);
        $canReview = $actor !== null && $version !== null && $policy->review($actor, $this->resource, $version);
        $status = $version?->lifecycle_status;

        return [
            'start_review' => $canReview && $status === ContentLifecycleStatus::Submitted,
            'request_revision' => $canReview && $status === ContentLifecycleStatus::InReview,
            'reject' => $canReview && $status === ContentLifecycleStatus::InReview,
            'approve' => $canReview && $status === ContentLifecycleStatus::InReview,
            'publish' => $canReview && $status === ContentLifecycleStatus::Approved,
            'archive' => $actor !== null && $policy->archive($actor)
                && $this->published_version_id !== null
                && $this->current_draft_version_id === null
                && $this->archived_at === null,
        ];
    }
}
