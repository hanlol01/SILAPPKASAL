<?php

namespace App\Models;

use App\Models\Concerns\GuardsContentVersionImmutability;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleVersionContent extends Model
{
    use GuardsContentVersionImmutability, HasFactory;

    protected $fillable = [
        'content_version_id',
        'document_json',
        'sanitized_html',
        'search_text',
        'estimated_reading_minutes',
        'cover_attachment_id',
        'cover_alt_text',
        'consultation_cta_item_id',
    ];

    protected $hidden = [
        'id',
        'content_version_id',
        'cover_attachment_id',
        'consultation_cta_item_id',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'document_json' => 'array',
            'estimated_reading_minutes' => 'integer',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ContentVersion::class, 'content_version_id');
    }

    public function coverAttachment(): BelongsTo
    {
        return $this->belongsTo(ContentAttachment::class, 'cover_attachment_id');
    }

    public function consultationCta(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class, 'consultation_cta_item_id');
    }
}
