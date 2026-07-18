<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class AuditLogQuery
{
    public function __construct(private readonly AuditLogVisibilityScope $visibility)
    {
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function build(
        User $user,
        array $filters,
        CarbonImmutable $cutoff,
        bool $excludeExportEvents = false,
    ): Builder {
        $query = $this->visibility->query($user)
            ->where('created_at', '<=', $cutoff)
            ->when($excludeExportEvents, fn (Builder $query): Builder => $query->where('action', '!=', AuditAction::AuditExport->value));

        foreach (['category', 'severity', 'action', 'result', 'actor_kind', 'actor_role_code', 'request_id'] as $filter) {
            $query->when(
                array_key_exists($filter, $filters) && $filters[$filter] !== null && $filters[$filter] !== '',
                fn (Builder $query): Builder => $query->where($filter, $filters[$filter]),
            );
        }

        if (array_key_exists('is_elevated_access', $filters) && $filters['is_elevated_access'] !== null && $filters['is_elevated_access'] !== '') {
            $query->where('is_elevated_access', filter_var($filters['is_elevated_access'], FILTER_VALIDATE_BOOLEAN));
        }

        if ($filters['date_from'] ?? null) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to'] ?? null) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
