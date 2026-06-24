<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'role_id',
        'university_id',
        'name',
        'email',
        'nim',
        'nip',
        'phone_number',
        'password',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'reporter_id');
    }

    public function caseAssignments(): HasMany
    {
        return $this->hasMany(CaseAssignment::class, 'satgas_id');
    }

    public function assignedCaseRecords(): HasMany
    {
        return $this->hasMany(CaseAssignment::class, 'assigned_by');
    }

    public function leadInvestigations(): HasMany
    {
        return $this->hasMany(Investigation::class, 'lead_investigator_id');
    }

    public function investigationActivities(): HasMany
    {
        return $this->hasMany(InvestigationActivity::class, 'investigator_id');
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class, 'author_id');
    }

    public function recommendationStatusChanges(): HasMany
    {
        return $this->hasMany(RecommendationStatusHistory::class, 'changed_by');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(Decision::class, 'recorder_id');
    }

    public function decisionStatusChanges(): HasMany
    {
        return $this->hasMany(DecisionStatusHistory::class, 'changed_by');
    }

    public function recoveries(): HasMany
    {
        return $this->hasMany(Recovery::class, 'created_by');
    }

    public function recoveryStatusChanges(): HasMany
    {
        return $this->hasMany(RecoveryStatusHistory::class, 'changed_by');
    }

    public function recoveryMonitorings(): HasMany
    {
        return $this->hasMany(RecoveryMonitoring::class, 'monitor_id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(Evidence::class, 'submitted_by');
    }

    public function evidenceStatusChanges(): HasMany
    {
        return $this->hasMany(EvidenceStatusHistory::class, 'changed_by');
    }

    public function evidenceCustodyEvents(): HasMany
    {
        return $this->hasMany(EvidenceCustodyEvent::class, 'actor_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }

    public function hasRole(string $role): bool
    {
        return $this->role?->code === $role;
    }

    public function hasPermission(string $permission): bool
    {
        return $this->permissions()->contains('code', $permission);
    }

    /**
     * @return EloquentCollection<int, Permission>
     */
    public function permissions(): EloquentCollection
    {
        if (! $this->relationLoaded('role')) {
            $this->load('role.permissions');
        } elseif ($this->role && ! $this->role->relationLoaded('permissions')) {
            $this->role->load('permissions');
        }

        return $this->role?->permissions ?? new EloquentCollection();
    }
}
