<?php

namespace App\Models;

use App\Enums\ContentScope;
use App\Enums\ContentType;
use App\Models\Concerns\HasContentScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use InvalidArgumentException;

class FeaturedContent extends Model
{
    use HasContentScope, HasFactory;

    protected $table = 'featured_content';

    protected $fillable = [
        'public_id',
        'scope',
        'university_id',
        'content_item_id',
        'rank',
        'is_active',
        'active_from',
        'active_until',
        'creator_id',
    ];

    protected $hidden = [
        'id',
        'scope_key',
        'university_id',
        'content_item_id',
        'creator_id',
        'created_at',
        'updated_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (FeaturedContent $featured): void {
            if (blank($featured->public_id)) {
                $featured->public_id = (string) Str::uuid();
            }
        });

        static::saving(function (FeaturedContent $featured): void {
            if ($featured->rank < 1 || $featured->rank > 5) {
                throw new InvalidArgumentException('Featured content rank must be between 1 and 5.');
            }

            $item = ContentItem::query()->find($featured->content_item_id);
            if ($item === null
                || $item->content_type !== ContentType::Article
                || $item->scope !== $featured->scope
                || (int) ($item->university_id ?? 0) !== (int) ($featured->university_id ?? 0)) {
                throw new InvalidArgumentException('Featured placement must match an Article and its scope.');
            }
            if ($featured->is_active && ($item->published_version_id === null || $item->archived_at !== null)) {
                throw new InvalidArgumentException('Active featured placement requires an eligible published Article.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'scope' => ContentScope::class,
            'rank' => 'integer',
            'is_active' => 'boolean',
            'active_from' => 'datetime',
            'active_until' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class, 'content_item_id');
    }

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
}
