<?php

namespace App\Models;

use App\Enums\ContentLifecycleStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LogicException;

class ContentVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
        'content_item_id',
        'version_number',
        'lifecycle_status',
        'title',
        'excerpt',
        'author_id',
        'editor_id',
        'source_type',
        'seed_key',
        'requires_editorial_review',
        'editorial_note',
        'submitted_at',
        'review_started_at',
        'reviewed_at',
        'approved_at',
        'published_at',
        'rejected_at',
        'revision_requested_at',
    ];

    protected $hidden = [
        'id',
        'content_item_id',
        'author_id',
        'editor_id',
        'seed_key',
        'editorial_note',
        'submitted_at',
        'review_started_at',
        'reviewed_at',
        'approved_at',
        'rejected_at',
        'revision_requested_at',
        'created_at',
        'updated_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (ContentVersion $version): void {
            if (blank($version->public_id)) {
                $version->public_id = (string) Str::uuid();
            }
        });

        static::updating(function (ContentVersion $version): void {
            $original = ContentLifecycleStatus::tryFrom((string) $version->getRawOriginal('lifecycle_status'));

            if ($original?->immutable()) {
                throw new LogicException('Published and archived content versions are immutable.');
            }
        });

        static::deleting(function (ContentVersion $version): void {
            if ($version->lifecycle_status?->immutable()) {
                throw new LogicException('Published and archived content versions cannot be deleted.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'lifecycle_status' => ContentLifecycleStatus::class,
            'version_number' => 'integer',
            'requires_editorial_review' => 'boolean',
            'editorial_note' => 'encrypted',
            'submitted_at' => 'datetime',
            'review_started_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
            'rejected_at' => 'datetime',
            'revision_requested_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class, 'content_item_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editor_id');
    }

    public function articleContent(): HasOne
    {
        return $this->hasOne(ArticleVersionContent::class, 'content_version_id');
    }

    public function faqContent(): HasOne
    {
        return $this->hasOne(FaqVersionContent::class, 'content_version_id');
    }

    public function consultationContent(): HasOne
    {
        return $this->hasOne(ConsultationVersionContent::class, 'content_version_id');
    }

    public function reviewDecisions(): HasMany
    {
        return $this->hasMany(ContentReviewDecision::class, 'content_version_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ContentAttachment::class, 'content_version_id');
    }
}
