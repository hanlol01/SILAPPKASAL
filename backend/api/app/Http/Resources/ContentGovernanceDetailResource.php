<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ContentGovernanceDetailResource extends ContentGovernanceResource
{
    public function toArray(Request $request): array
    {
        $base = parent::toArray($request);
        $version = $this->currentDraftVersion ?? $this->latestVersion ?? $this->publishedVersion;
        $previousPublished = $this->publishedVersion;

        return [
            ...$base,
            'version' => $version ? $this->version($version, true) : null,
            'previous_published_version' => $previousPublished !== null
                && (int) $previousPublished->id !== (int) $version?->id
                ? $this->version($previousPublished, false)
                : null,
            'decision_history' => $this->governanceHistory?->values() ?? [],
            'decision_history_truncated' => (bool) ($this->governanceHistoryTruncated ?? false),
            'archived_at' => $this->archived_at?->toJSON(),
        ];
    }

    /** @return array<string, mixed> */
    private function version(mixed $version, bool $includeEditorialNote): array
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
            'editorial_note' => $includeEditorialNote ? $version->editorial_note : null,
            'submitted_at' => $version->submitted_at?->toJSON(),
            'reviewed_at' => $version->reviewed_at?->toJSON(),
            'approved_at' => $version->approved_at?->toJSON(),
            'published_at' => $version->published_at?->toJSON(),
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
