<?php

namespace App\Models;

use LogicException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_id',
        'actor_kind',
        'actor_role_code',
        'actor_display_name_safe',
        'request_id',
        'action',
        'category',
        'severity',
        'result',
        'subject_type',
        'subject_id',
        'subject_kind',
        'subject_reference_safe',
        'is_elevated_access',
        'metadata',
        'before_changes',
        'after_changes',
        'expires_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'before_changes' => 'array',
            'after_changes' => 'array',
            'is_elevated_access' => 'boolean',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AuditLog $auditLog): void {
            $auditLog->public_id ??= (string) Str::uuid();
        });

        static::updating(function (AuditLog $auditLog): void {
            if ($auditLog->isDirty('public_id')) {
                throw new LogicException('Audit public_id is immutable.');
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
