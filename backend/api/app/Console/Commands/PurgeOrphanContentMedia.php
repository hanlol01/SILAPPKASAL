<?php

namespace App\Console\Commands;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\ContentAttachmentPurpose;
use App\Enums\ContentLifecycleStatus;
use App\Models\ContentAttachment;
use App\Models\ContentItem;
use App\Models\ContentVersion;
use App\Services\AuditLogService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class PurgeOrphanContentMedia extends Command
{
    protected $signature = 'content:purge-orphan-media
        {--execute : Delete eligible private media and metadata}
        {--batch=200 : Maximum candidate rows inspected per run}
        {--older-than-hours= : Override the configured retention period, minimum 24 hours}';

    protected $description = 'Purge unreferenced cover and inline media only from current editable Content versions';

    public function __construct(private readonly AuditLogService $auditLogs)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $hours = $this->option('older-than-hours') !== null
            ? (int) $this->option('older-than-hours')
            : (int) config('content.attachments.orphan_media_retention_hours', 168);
        $hours = max(24, $hours);
        $batch = max(1, min(1000, (int) $this->option('batch')));
        $candidates = $this->candidates($hours)->limit($batch)->get();
        $orphanCount = $candidates->filter(fn (ContentAttachment $attachment): bool => $this->isOrphan($attachment))->count();

        if (! $this->option('execute')) {
            $this->info("Dry run: {$orphanCount} orphan Content media records are eligible after {$hours} hours.");

            return self::SUCCESS;
        }

        $purged = 0;
        $failed = 0;
        foreach ($candidates as $candidate) {
            try {
                if ($this->purge($candidate)) {
                    $purged++;
                }
            } catch (Throwable) {
                $failed++;
            }
        }

        $this->info(
            "Purged {$purged} orphan Content media records; "
            ."{$failed} cleanup operations failed or left binary cleanup deferred.",
        );

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return Builder<ContentAttachment> */
    private function candidates(int $hours): Builder
    {
        return ContentAttachment::query()
            ->select('content_attachments.*')
            ->join('content_versions', 'content_versions.id', '=', 'content_attachments.content_version_id')
            ->join('content_items', function ($join): void {
                $join->on('content_items.id', '=', 'content_versions.content_item_id')
                    ->on('content_items.current_draft_version_id', '=', 'content_versions.id');
            })
            ->whereIn('content_attachments.purpose', [
                ContentAttachmentPurpose::Cover->value,
                ContentAttachmentPurpose::InlineImage->value,
            ])
            ->whereIn('content_versions.lifecycle_status', [
                ContentLifecycleStatus::Draft->value,
                ContentLifecycleStatus::RevisionRequested->value,
            ])
            ->whereNull('content_items.archived_at')
            ->where('content_attachments.created_at', '<=', now()->subHours($hours))
            ->orderBy('content_attachments.id');
    }

    private function purge(ContentAttachment $candidate): bool
    {
        $plan = DB::transaction(function () use ($candidate): ?array {
            $attachment = ContentAttachment::query()->whereKey($candidate->id)->lockForUpdate()->first();
            if ($attachment === null) {
                return null;
            }

            $version = ContentVersion::query()->whereKey($attachment->content_version_id)->lockForUpdate()->first();
            if ($version === null || ! $version->lifecycle_status?->editable()) {
                return null;
            }

            $item = ContentItem::query()->whereKey($version->content_item_id)->lockForUpdate()->first();
            if ($item === null
                || $item->archived_at !== null
                || (int) $item->current_draft_version_id !== (int) $version->id) {
                return null;
            }

            $attachment->setRelation('version', $version);
            $version->setRelation('item', $item);
            if (! $this->isOrphan($attachment, true)) {
                return null;
            }

            if ($attachment->storage_disk !== 'content') {
                return null;
            }

            $plan = [
                'disk' => $attachment->storage_disk,
                'path' => $attachment->storage_path,
                'attachment_public_id' => $attachment->public_id,
                'purpose' => $attachment->purpose->value,
                'item' => $item,
                'version' => $version,
            ];
            $attachment->delete();

            return $plan;
        });

        if ($plan === null) {
            return false;
        }

        try {
            $disk = Storage::disk($plan['disk']);
            if (! $disk->delete($plan['path'])) {
                throw new RuntimeException('The orphan Content media binary cleanup was deferred.');
            }
        } catch (Throwable) {
            Log::warning('Content orphan media cleanup deferred after storage failure.', [
                'attachment_public_id' => $plan['attachment_public_id'],
                'result' => 'metadata_deleted_binary_cleanup_deferred',
            ]);

            throw new RuntimeException('The orphan Content media binary cleanup was deferred.');
        }

        try {
            $item = $plan['item'];
            $version = $plan['version'];
            $this->auditLogs->record(
                action: AuditAction::ContentAttachmentRemoved,
                category: AuditCategory::Content,
                severity: AuditSeverity::Info,
                subject: $item,
                metadata: [
                    'content_public_id' => $item->public_id,
                    'version_number' => $version->version_number,
                    'content_type' => $item->content_type->value,
                    'scope' => $item->scope->value,
                    'university_code' => $item->university()->value('code'),
                    'attachment_public_id' => $plan['attachment_public_id'],
                    'purpose' => $plan['purpose'],
                    'result' => 'orphan_cleanup',
                ],
            );
        } catch (Throwable $exception) {
            Log::error('Content orphan media was removed but its audit event could not be persisted.', [
                'attachment_public_id' => $plan['attachment_public_id'],
                'result' => 'audit_write_failed',
                'exception' => $exception::class,
            ]);
        }

        return true;
    }

    private function isOrphan(ContentAttachment $attachment, bool $lockArticle = false): bool
    {
        $version = $attachment->relationLoaded('version')
            ? $attachment->version
            : $attachment->version()->with('item')->first();
        if ($version === null
            || ! $version->lifecycle_status?->editable()
            || $version->item === null
            || $version->item->archived_at !== null
            || (int) $version->item->current_draft_version_id !== (int) $version->id) {
            return false;
        }

        $articleQuery = $version->articleContent();
        if ($lockArticle) {
            $articleQuery->lockForUpdate();
        }
        $article = $articleQuery->first();
        if ($article === null) {
            return true;
        }

        if ($attachment->purpose === ContentAttachmentPurpose::Cover) {
            return (int) $article->cover_attachment_id !== (int) $attachment->id;
        }

        return ! $this->documentReferencesAttachment($article->document_json, $attachment->public_id);
    }

    /** @param array<string, mixed>|null $document */
    private function documentReferencesAttachment(?array $document, string $publicId): bool
    {
        if ($document === null) {
            return false;
        }

        $stack = [$document];
        while ($stack !== []) {
            $node = array_pop($stack);
            if (! is_array($node)) {
                continue;
            }
            if (($node['type'] ?? null) === 'imageReference'
                && ($node['attrs']['attachment_public_id'] ?? null) === $publicId) {
                return true;
            }
            foreach ($node['content'] ?? [] as $child) {
                if (is_array($child)) {
                    $stack[] = $child;
                }
            }
        }

        return false;
    }
}
