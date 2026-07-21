<?php

namespace App\Models;

use App\Models\Concerns\GuardsContentVersionImmutability;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultationVersionContent extends Model
{
    use GuardsContentVersionImmutability, HasFactory;

    protected $fillable = [
        'content_version_id',
        'service_name',
        'description',
        'email',
        'phone_display',
        'phone_normalized',
        'whatsapp_display',
        'whatsapp_normalized',
        'office_address',
        'operating_hours',
        'emergency_available',
        'appointment_url',
        'action_label',
        'icon_code',
        'sort_order',
        'is_active',
        'verification_date',
        'verified_owner',
    ];

    protected $hidden = ['id', 'content_version_id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'emergency_available' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'verification_date' => 'date',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ContentVersion::class, 'content_version_id');
    }
}
