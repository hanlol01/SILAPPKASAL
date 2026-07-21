<?php

namespace App\Models;

use App\Models\Concerns\GuardsContentVersionImmutability;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaqVersionContent extends Model
{
    use GuardsContentVersionImmutability, HasFactory;

    protected $fillable = [
        'content_version_id',
        'question',
        'answer_document_json',
        'sanitized_answer_html',
        'plain_search_text',
        'display_order',
    ];

    protected $hidden = ['id', 'content_version_id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'answer_document_json' => 'array',
            'display_order' => 'integer',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ContentVersion::class, 'content_version_id');
    }
}
