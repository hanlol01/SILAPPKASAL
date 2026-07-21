<?php

namespace App\Models;

use App\Enums\ContentScope;
use App\Enums\ContentType;
use App\Models\Concerns\HasContentScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ContentItem extends Model
{
    use HasContentScope, HasFactory, SoftDeletes;

    protected $fillable = [
        'public_id',
        'stable_seed_key',
        'content_type',
        'section_id',
        'category_id',
        'slug',
        'scope',
        'university_id',
        'creator_id',
        'current_draft_version_id',
        'published_version_id',
        'lock_version',
        'archived_at',
        'archived_by',
        'archive_reason',
    ];

    protected $hidden = [
        'id',
        'stable_seed_key',
        'section_id',
        'category_id',
        'scope_key',
        'university_id',
        'creator_id',
        'current_draft_version_id',
        'published_version_id',
        'archived_by',
        'archive_reason',
        'lock_version',
        'deleted_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (ContentItem $item): void {
            if (blank($item->public_id)) {
                $item->public_id = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'content_type' => ContentType::class,
            'scope' => ContentScope::class,
            'lock_version' => 'integer',
            'archived_at' => 'datetime',
            'archive_reason' => 'encrypted',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(ContentSection::class, 'section_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ContentCategory::class, 'category_id');
    }

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ContentVersion::class, 'content_item_id');
    }

    public function currentDraftVersion(): BelongsTo
    {
        return $this->belongsTo(ContentVersion::class, 'current_draft_version_id');
    }

    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(ContentVersion::class, 'published_version_id');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(ContentVersion::class, 'content_item_id')->ofMany('version_number', 'max');
    }

    public function featuredPlacements(): HasMany
    {
        return $this->hasMany(FeaturedContent::class, 'content_item_id');
    }
}
