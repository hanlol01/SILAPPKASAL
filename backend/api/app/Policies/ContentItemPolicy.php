<?php

namespace App\Policies;

use App\Enums\ContentLifecycleStatus;
use App\Enums\ContentScope;
use App\Enums\ContentType;
use App\Models\ContentItem;
use App\Models\ContentVersion;
use App\Models\User;

class ContentItemPolicy extends BasePolicy
{
    public function viewPublished(User $user): bool
    {
        return $user->is_active && $this->allowPermission($user, 'content.read.published');
    }

    public function viewManagement(User $user, ContentItem $item): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($this->allowRole($user, 'super_admin') && $this->allowPermission($user, 'content.read.management.all')) {
            return true;
        }

        return $this->allowRole($user, 'admin')
            && $this->allowPermission($user, 'content.read.management.own_campus')
            && $item->scope === ContentScope::Campus
            && $user->university_id !== null
            && (int) $item->university_id === (int) $user->university_id;
    }

    public function createCampus(User $user, int $universityId): bool
    {
        return $user->is_active
            && $this->allowRole($user, 'admin')
            && $this->allowPermission($user, 'content.create.campus')
            && $user->university_id !== null
            && (int) $user->university_id === $universityId;
    }

    public function createGlobal(User $user): bool
    {
        return $user->is_active
            && $this->allowRole($user, 'super_admin')
            && $this->allowPermission($user, 'content.publish.global');
    }

    public function updateDraft(User $user, ContentItem $item, ContentVersion $version): bool
    {
        if ($item->archived_at !== null
            || ! $version->lifecycle_status?->editable()
            || (int) $version->content_item_id !== (int) $item->id) {
            return false;
        }

        if ($item->scope === ContentScope::Global) {
            return $this->createGlobal($user);
        }

        return $this->createCampus($user, (int) $item->university_id)
            && $this->allowPermission($user, 'content.update.own_campus');
    }

    public function submit(User $user, ContentItem $item, ContentVersion $version): bool
    {
        if ($item->scope === ContentScope::Global) {
            return $this->createGlobal($user) && $version->lifecycle_status === ContentLifecycleStatus::Draft;
        }

        return $version->lifecycle_status === ContentLifecycleStatus::Draft
            && $this->updateDraft($user, $item, $version)
            && $this->allowPermission($user, 'content.submit.own_campus');
    }

    public function manageAttachment(User $user, ContentItem $item, ContentVersion $version): bool
    {
        if ($item->scope === ContentScope::Global) {
            return $this->updateDraft($user, $item, $version);
        }

        return $this->updateDraft($user, $item, $version)
            && $this->allowPermission($user, 'content.attachment.manage.own_campus');
    }

    public function viewEditableAttachment(User $user, ContentItem $item, ContentVersion $version): bool
    {
        return $item->content_type === ContentType::Article
            && $item->archived_at === null
            && (int) $item->current_draft_version_id === (int) $version->id
            && (int) $version->content_item_id === (int) $item->id
            && $version->lifecycle_status?->editable() === true
            && $this->manageAttachment($user, $item, $version);
    }

    public function viewGovernanceAttachment(User $user, ContentItem $item, ContentVersion $version): bool
    {
        if (! $user->is_active
            || ! $this->allowRole($user, 'super_admin')
            || ! $this->allowPermission($user, 'content.read.management.all')
            || ! $this->allowPermission($user, 'content.review')
            || $item->content_type !== ContentType::Article
            || $item->archived_at !== null
            || (int) $version->content_item_id !== (int) $item->id) {
            return false;
        }

        $reviewVersion = (int) $item->current_draft_version_id === (int) $version->id
            && in_array($version->lifecycle_status, [
                ContentLifecycleStatus::Submitted,
                ContentLifecycleStatus::InReview,
                ContentLifecycleStatus::Approved,
            ], true);
        $publishedComparison = (int) $item->published_version_id === (int) $version->id
            && $version->lifecycle_status === ContentLifecycleStatus::Published;

        return $reviewVersion || $publishedComparison;
    }

    public function createRevision(User $user, ContentItem $item): bool
    {
        if ($item->archived_at !== null) {
            return false;
        }

        if ($item->scope === ContentScope::Global) {
            return $this->createGlobal($user);
        }

        return $this->createCampus($user, (int) $item->university_id)
            && $this->allowPermission($user, 'content.update.own_campus');
    }

    public function review(User $user, ContentItem $item, ?ContentVersion $version = null): bool
    {
        return $user->is_active
            && $this->allowRole($user, 'super_admin')
            && $this->allowPermission($user, 'content.review')
            && (int) $item->creator_id !== (int) $user->id
            && ($version === null || (
                (int) $version->author_id !== (int) $user->id
                && (int) ($version->editor_id ?? 0) !== (int) $user->id
            ));
    }

    public function archive(User $user): bool
    {
        return $user->is_active
            && $this->allowRole($user, 'super_admin')
            && $this->allowPermission($user, 'content.archive');
    }

    public function manageFeatured(User $user): bool
    {
        return $user->is_active
            && $this->allowRole($user, 'super_admin')
            && $this->allowPermission($user, 'content.feature.manage');
    }

    public function governCategory(User $user): bool
    {
        return $user->is_active
            && $this->allowRole($user, 'super_admin')
            && $this->allowPermission($user, 'content.category.govern');
    }

    public function manageCampusCategory(User $user): bool
    {
        return $user->is_active
            && $this->allowRole($user, 'admin')
            && $user->university_id !== null
            && $this->allowPermission($user, 'content.category.manage.own_campus');
    }
}
