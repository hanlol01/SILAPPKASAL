<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublishedFaqResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $content = $this->publishedVersion?->faqContent;

        return [
            'public_id' => $this->public_id,
            'category' => new ContentCategoryResource($this->whenLoaded('category')),
            'question' => $content?->question,
            'answer' => $content?->answer_document_json,
            'answer_html' => $content?->sanitized_answer_html,
            'display_order' => $content?->display_order,
        ];
    }
}
