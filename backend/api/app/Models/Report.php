<?php

namespace App\Models;

use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Report extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reporter_id',
        'registration_number',
        'tracking_code',
        'report_type',
        'category_code',
        'chronology',
        'incident_date',
        'incident_time',
        'incident_location',
        'location_type',
        'respondent_name',
        'respondent_campus_status',
        'respondent_relation',
        'respondent_details',
        'witness_info',
        'reporter_phone_encrypted',
        'status',
        'priority',
        'admin_notes',
        'rejection_reason',
        'submitted_at',
        'reviewed_at',
        'forwarded_at',
    ];

    protected function casts(): array
    {
        return [
            'chronology' => 'encrypted',
            'incident_date' => 'date',
            'incident_location' => 'encrypted',
            'respondent_name' => 'encrypted',
            'respondent_details' => 'encrypted',
            'witness_info' => 'encrypted',
            'reporter_phone_encrypted' => 'encrypted',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'forwarded_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ReportCategory::class, 'category_code', 'code');
    }

    public function locationType(): BelongsTo
    {
        return $this->belongsTo(LocationType::class, 'location_type', 'code');
    }

    public function campusStatus(): BelongsTo
    {
        return $this->belongsTo(CampusStatus::class, 'respondent_campus_status', 'code');
    }

    public function relation(): BelongsTo
    {
        return $this->belongsTo(Relation::class, 'respondent_relation', 'code');
    }

    public function priorityLevel(): BelongsTo
    {
        return $this->belongsTo(PriorityLevel::class, 'priority', 'code');
    }

    public function case(): HasOne
    {
        return $this->hasOne(CaseRecord::class);
    }

    public function evidenceSubmissions(): HasMany
    {
        return $this->hasMany(ReportEvidenceSubmission::class);
    }

    public function isSubmitted(): bool
    {
        return $this->status === ReportStatus::Submitted->value;
    }
}
