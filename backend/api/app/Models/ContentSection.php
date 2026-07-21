<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'label_id',
        'label_en',
        'description',
        'display_order',
        'is_active',
    ];

    protected $hidden = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function categories(): HasMany
    {
        return $this->hasMany(ContentCategory::class, 'section_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ContentItem::class, 'section_id');
    }
}
