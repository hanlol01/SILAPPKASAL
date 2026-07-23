<?php

namespace App\Http\Resources;

use App\Enums\ContentAttachmentPurpose;
use App\Support\ContentMediaManifest;
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
        $cover = $version ? ContentMediaManifest::cover($version) : null;

        return [
            'public_id' => $this->public_id,
            'slug' => $this->slug,
            'title' => $version?->title,
            'excerpt' => $version?->excerpt,
            'category' => $category ? new ContentCategoryResource($category) : null,
            'category_name' => $version?->category_name ?? $category?->name,
            'section' => new ContentSectionResource($this->whenLoaded('section')),
            'scope' => $this->scope?->value,
            'cover' => $cover
                ? new ContentAttachmentResource($cover)
                : null,
            'published_at' => $version?->published_at?->toJSON(),
            'estimated_reading_minutes' => $article?->estimated_reading_minutes,
            'featured' => (bool) $this->resource->getAttribute('is_featured'),
            'body' => $this->when($detail, $article?->document_json),
            'body_html' => $this->when($detail, $article?->sanitized_html),
            'attachments' => $this->when(
                $detail,
                fn () => ContentAttachmentResource::collection(
                    $version
                        ? ContentMediaManifest::forPurpose($version, ContentAttachmentPurpose::Attachment)
                        : collect()
                )
            ),
            'inline_images' => $this->when(
                $detail,
                fn () => ContentAttachmentResource::collection(
                    $version
                        ? ContentMediaManifest::forPurpose($version, ContentAttachmentPurpose::InlineImage)
                        : collect()
                )
            ),
            'related_articles' => $this->when(
                $detail && $this->resource->relationLoaded('relatedArticles'),
                fn () => self::collection($this->relatedArticles)
            ),
        ];
    }
}
