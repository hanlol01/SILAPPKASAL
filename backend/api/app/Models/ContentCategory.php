<?php

namespace App\Models;

use App\Enums\ContentScope;
use App\Models\Concerns\HasContentScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ContentCategory extends Model
{
    use HasContentScope, HasFactory;

    protected $fillable = [
        'public_id',
        'section_id',
        'stable_seed_key',
        'code',
        'name',
        'slug',
        'description',
        'icon_code',
        'display_order',
        'scope',
        'university_id',
        'is_active',
        'creator_id',
    ];

    protected $hidden = [
        'id',
        'section_id',
        'scope_key',
        'university_id',
        'creator_id',
        'stable_seed_key',
        'created_at',
        'updated_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (ContentCategory $category): void {
            if (blank($category->public_id)) {
                $category->public_id = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'scope' => ContentScope::class,
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(ContentSection::class, 'section_id');
    }

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ContentItem::class, 'category_id');
    }
}
