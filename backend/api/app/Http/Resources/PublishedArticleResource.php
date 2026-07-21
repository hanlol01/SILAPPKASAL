<?php

namespace App\Http\Resources;

use App\Enums\ContentAttachmentPurpose;
use App\Enums\ContentLifecycleStatus;
use App\Enums\ContentScope;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublishedArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $version = $this->publishedVersion;
        $article = $version?->articleContent;
        $detail = (bool) $this->resource->getAttribute('content_detail');
        $consultationCta = $article?->consultationCta;
        $consultationVersion = $consultationCta?->publishedVersion;
        $consultationCtaAllowed = $consultationCta !== null
            && $consultationCta->archived_at === null
            && $consultationVersion?->lifecycle_status === ContentLifecycleStatus::Published
            && $consultationVersion->published_at !== null
            && $consultationVersion->published_at->isPast()
            && $consultationVersion->consultationContent?->is_active === true
            && ($consultationCta->scope === ContentScope::Global
                || ($this->scope === ContentScope::Campus
                    && $consultationCta->scope === ContentScope::Campus
                    && (int) $consultationCta->university_id === (int) $this->university_id));

        return [
            'public_id' => $this->public_id,
            'slug' => $this->slug,
            'title' => $version?->title,
            'excerpt' => $version?->excerpt,
            'category' => new ContentCategoryResource($this->whenLoaded('category')),
            'section' => new ContentSectionResource($this->whenLoaded('section')),
            'scope' => $this->scope?->value,
            'cover' => $article?->coverAttachment
                ? new ContentAttachmentResource($article->coverAttachment)
                : null,
            'published_at' => $version?->published_at?->toJSON(),
            'estimated_reading_minutes' => $article?->estimated_reading_minutes,
            'featured' => (bool) $this->resource->getAttribute('is_featured'),
            'body' => $this->when($detail, $article?->document_json),
            'body_html' => $this->when($detail, $article?->sanitized_html),
            'attachments' => $this->when(
                $detail,
                fn () => ContentAttachmentResource::collection(
                    ($version?->attachments ?? collect())->filter(
                        fn ($attachment): bool => $attachment->purpose !== ContentAttachmentPurpose::Cover
                    )->values()
                )
            ),
            'related_articles' => $this->when(
                $detail && $this->resource->relationLoaded('relatedArticles'),
                fn () => self::collection($this->relatedArticles)
            ),
            'consultation_cta_public_id' => $this->when(
                $detail,
                $consultationCtaAllowed ? $consultationCta->public_id : null,
            ),
        ];
    }
}
