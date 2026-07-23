<?php

namespace App\Http\Resources;

use App\Enums\ContentAttachmentPurpose;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublishedArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $version = $this->publishedVersion;
        $article = $version?->articleContent;
        $category = $version?->category;
        $detail = (bool) $this->resource->getAttribute('content_detail');

        return [
            'public_id' => $this->public_id,
            'slug' => $this->slug,
            'title' => $version?->title,
            'excerpt' => $version?->excerpt,
            'category' => $category ? new ContentCategoryResource($category) : null,
            'category_name' => $version?->category_name ?? $category?->name,
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
        ];
    }
}
