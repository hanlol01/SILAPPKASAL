<?php

namespace App\Http\Resources\Concerns;

use App\Enums\ContentScope;
use App\Models\ContentItem;
use App\Models\ContentVersion;
use App\Models\User;
use Illuminate\Http\Request;

trait ProjectsContentAttribution
{
    /** @return array{name: string, email: string, role: string|null}|null */
    protected function basicContentActor(?User $actor, Request $request): ?array
    {
        if ($actor === null || ! $this->mayViewBasicContentAttribution($request)) {
            return null;
        }

        return $this->serializeContentActor($actor);
    }

    /** @return array{name: string, email: string, role: string|null}|null */
    protected function internalContentActor(?User $actor, Request $request): ?array
    {
        if ($actor === null || ! $this->mayViewInternalContentAttribution($request)) {
            return null;
        }

        return $this->serializeContentActor($actor);
    }

    /** @return array{name: string, email: string, role: string|null} */
    private function serializeContentActor(User $actor): array
    {
        return [
            'name' => $actor->name,
            'email' => $actor->email,
            'role' => $actor->role?->code,
        ];
    }

    /** @return array{name: string, email: string, role: string|null}|null */
    protected function reviewAttributionActor(?ContentVersion $version, Request $request): ?array
    {
        if ($version === null || ! $version->relationLoaded('latestReviewAttributionDecision')) {
            return null;
        }

        return $this->internalContentActor(
            $version->latestReviewAttributionDecision?->reviewer,
            $request,
        );
    }

    /** @return array{name: string, email: string, role: string|null}|null */
    protected function approvalAttributionActor(?ContentVersion $version, Request $request): ?array
    {
        if ($version === null || ! $version->relationLoaded('latestApprovalDecision')) {
            return null;
        }

        return $this->internalContentActor(
            $version->latestApprovalDecision?->reviewer,
            $request,
        );
    }

    /** @return array{name: string, email: string, role: string|null}|null */
    protected function publisherAttributionActor(?ContentVersion $version, Request $request): ?array
    {
        if ($version === null
            || ! $version->relationLoaded('publisher')
            || ! $this->mayViewInternalContentAttribution($request)) {
            return null;
        }

        return $version->publisher === null
            ? null
            : $this->serializeContentActor($version->publisher);
    }

    private function mayViewBasicContentAttribution(Request $request): bool
    {
        $viewer = $request->user();
        if ($viewer === null || ! $viewer->is_active) {
            return false;
        }

        if ($viewer->hasRole('super_admin') && $viewer->hasPermission('content.read.management.all')) {
            return true;
        }

        $item = $this->resource;

        return $viewer->hasRole('admin')
            && $viewer->hasPermission('content.read.management.own_campus')
            && $item instanceof ContentItem
            && $item->scope === ContentScope::Campus
            && $viewer->university_id !== null
            && (int) $item->university_id === (int) $viewer->university_id;
    }

    private function mayViewInternalContentAttribution(Request $request): bool
    {
        $viewer = $request->user();
        if ($viewer === null || ! $viewer->is_active) {
            return false;
        }

        return $viewer->hasRole('super_admin')
            && $viewer->hasPermission('content.read.management.all');
    }
}
