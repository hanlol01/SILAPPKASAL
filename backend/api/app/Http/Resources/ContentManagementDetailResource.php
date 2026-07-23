<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ProjectsContentAttribution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentManagementDetailResource extends JsonResource
{
    use ProjectsContentAttribution;

    public function toArray(Request $request): array
    {
        $version = $this->currentDraftVersion ?? $this->latestVersion ?? $this->publishedVersion;
        $hasVersionCategory = $version !== null
            && ($version->category_name !== null || $version->category_id !== null);
        $category = $hasVersionCategory ? $version->category : $this->category;
        $categoryName = $hasVersionCategory
            ? ($version->category_name ?? $version->category?->name)
            : ($this->category_name ?? $this->category?->name);
        $reviewDecision = $version?->relationLoaded('latestFeedbackDecision')
            ? $version->latestFeedbackDecision
            : null;

        return [
            'public_id' => $this->public_id,
            'content_type' => $this->content_type?->value,
            'slug' => $this->slug,
            'scope' => $this->scope?->value,
            'section' => new ContentSectionResource($this->section),
            'category' => $category ? new ContentCategoryResource($category) : null,
            'category_name' => $categoryName,
            'university' => $this->university ? [
                'code' => $this->university->code,
                'name' => $this->university->name,
            ] : null,
            'created_by' => $this->basicContentActor($this->creator, $request),
            'submitted_by' => $this->basicContentActor($version?->submitter, $request),
            'reviewed_by' => $this->reviewAttributionActor($version, $request),
            'approved_by' => $this->approvalAttributionActor($version, $request),
            'published_by' => $this->publisherAttributionActor($version, $request),
            'lock_version' => $this->lock_version,
            'lifecycle_status' => $this->archived_at !== null ? 'archived' : $version?->lifecycle_status?->value,
            'has_editable_version' => $this->archived_at === null
                && ($this->currentDraftVersion?->lifecycle_status?->editable() ?? false),
            'version' => $version ? $this->version($version) : null,
            'published_version' => $this->publishedVersion ? [
                'public_id' => $this->publishedVersion->public_id,
                'version_number' => $this->publishedVersion->version_number,
                'published_at' => $this->publishedVersion->published_at?->toJSON(),
            ] : null,
            'review_feedback' => $reviewDecision ? [
                'decision' => $reviewDecision->decision_code?->value,
                'reason' => $reviewDecision->narrative_reason,
                'decided_at' => $reviewDecision->decided_at?->toJSON(),
            ] : null,
            'editorial_timeline' => $this->managementHistory?->values() ?? [],
            'editorial_timeline_truncated' => (bool) ($this->managementHistoryTruncated ?? false),
            'archived_at' => $this->archived_at?->toJSON(),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }

    /** @return array<string, mixed> */
    private function version(mixed $version): array
    {
        $article = $version->articleContent;
        $faq = $version->faqContent;
        $consultation = $version->consultationContent;

        return [
            'public_id' => $version->public_id,
            'version_number' => $version->version_number,
            'status' => $version->lifecycle_status?->value,
            'title' => $version->title,
            'excerpt' => $version->excerpt,
            'requires_editorial_review' => $version->requires_editorial_review,
            'submitted_at' => $version->submitted_at?->toJSON(),
            'reviewed_at' => $version->reviewed_at?->toJSON(),
            'approved_at' => $version->approved_at?->toJSON(),
            'published_at' => $version->published_at?->toJSON(),
            'updated_at' => $version->updated_at?->toJSON(),
            'article' => $article ? [
                'document' => $article->document_json,
                'estimated_reading_minutes' => $article->estimated_reading_minutes,
                'cover_alt_text' => $article->cover_alt_text,
            ] : null,
            'faq' => $faq ? [
                'question' => $faq->question,
                'answer_document' => $faq->answer_document_json,
                'display_order' => $faq->display_order,
            ] : null,
            'consultation' => $consultation ? [
                'service_name' => $consultation->service_name,
                'description' => $consultation->description,
                'service_type' => $consultation->service_type,
                'email' => $consultation->email,
                'phone_display' => $consultation->phone_display,
                'whatsapp_display' => $consultation->whatsapp_display,
                'office_address' => $consultation->office_address,
                'operating_hours' => $consultation->operating_hours,
                'procedure' => $consultation->procedure,
                'confidentiality_info' => $consultation->confidentiality_info,
                'emergency_available' => $consultation->emergency_available,
                'appointment_url' => $consultation->appointment_url,
                'action_label' => $consultation->action_label,
                'icon_code' => $consultation->icon_code,
                'sort_order' => $consultation->sort_order,
                'is_active' => $consultation->is_active,
                'verification_date' => $consultation->verification_date?->format('Y-m-d'),
                'verified_owner' => $consultation->verified_owner,
            ] : null,
            'attachments' => ContentAttachmentResource::collection($version->attachments),
        ];
    }
}
