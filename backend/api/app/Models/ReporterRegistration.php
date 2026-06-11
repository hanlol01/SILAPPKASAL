<?php

namespace App\Models;

use App\Enums\ReporterRegistrationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReporterRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'registration_number',
        'name',
        'email',
        'nim',
        'phone_number',
        'password_hash',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'approved_user_id',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'status' => ReporterRegistrationStatus::class,
        ];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_user_id');
    }
}
