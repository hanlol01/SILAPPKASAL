<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditLogService
{
    /**
     * Keys containing these fragments are always redacted before persistence.
     *
     * @var list<string>
     */
    private array $sensitiveKeyFragments = [
        'password',
        'token',
        'hash',
        'encrypted',
        'payload',
        'content',
        'file',
        'chronology',
        'incident_location',
        'respondent',
        'witness',
        'phone',
        'narrative',
        'findings',
        'conclusion',
        'plan_summary',
        'recommended_action',
        'sanction_recommendation',
        'recovery_recommendation',
        'prevention_recommendation',
        'decision_summary',
        'decision_body',
        'recovery_plan',
        'support_needs',
        'condition_summary',
        'follow_up_plan',
        'notes',
        'description',
        'source',
    ];

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $beforeChanges
     * @param  array<string, mixed>  $afterChanges
     */
    public function record(
        AuditAction|string $action,
        AuditCategory|string $category,
        AuditSeverity|string $severity = AuditSeverity::Info,
        ?User $actor = null,
        ?Model $subject = null,
        array $metadata = [],
        array $beforeChanges = [],
        array $afterChanges = [],
        ?string $requestId = null,
        bool $isElevatedAccess = false,
    ): AuditLog {
        $metadata = $this->sanitizeMetadata($metadata);
        $metadata['is_elevated_access'] = (bool) ($metadata['is_elevated_access'] ?? $isElevatedAccess);

        return AuditLog::query()->create([
            'actor_id' => $actor?->id,
            'request_id' => $requestId,
            'action' => $this->enumValue($action),
            'category' => $this->enumValue($category),
            'severity' => $this->enumValue($severity),
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata,
            'before_changes' => $this->sanitizeDelta($beforeChanges),
            'after_changes' => $this->sanitizeDelta($afterChanges),
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function sanitizeMetadata(array $metadata): array
    {
        $safe = [];

        foreach ($metadata as $key => $value) {
            $safe[$key] = $this->sanitizeValue((string) $key, $value, allowArrays: true);
        }

        return $safe;
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private function sanitizeDelta(array $changes): array
    {
        $safe = [];

        foreach ($changes as $key => $value) {
            $safe[$key] = $this->sanitizeValue((string) $key, $value, allowArrays: false);
        }

        return $safe;
    }

    private function sanitizeValue(string $key, mixed $value, bool $allowArrays): mixed
    {
        if ($this->isSensitiveKey($key)) {
            return '[REDACTED]';
        }

        if (is_null($value) || is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            return $value;
        }

        if ($allowArrays && is_array($value)) {
            return collect($value)
                ->mapWithKeys(fn (mixed $nestedValue, string|int $nestedKey): array => [
                    $nestedKey => $this->sanitizeValue((string) $nestedKey, $nestedValue, allowArrays: false),
                ])
                ->all();
        }

        return '[REDACTED_NON_SCALAR]';
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = Str::of($key)->lower()->replace(['-', ' '], '_')->toString();

        foreach ($this->sensitiveKeyFragments as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function enumValue(AuditAction|AuditCategory|AuditSeverity|string $value): string
    {
        return $value instanceof AuditAction || $value instanceof AuditCategory || $value instanceof AuditSeverity
            ? $value->value
            : $value;
    }
}
