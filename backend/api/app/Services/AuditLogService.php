<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditResult;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuditEventCatalog;
use App\Support\AuditSnapshot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AuditLogService
{
    public function __construct(
        private readonly AuditEventCatalog $catalog,
        private readonly AuditSnapshot $snapshot,
    ) {
    }

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
        AuditResult $result = AuditResult::Succeeded,
        ?\DateTimeInterface $expiresAt = null,
    ): AuditLog {
        $actionValue = $this->enumValue($action);
        $this->assertResultMatchesAction($actionValue, $result);
        $metadata = $this->catalog->sanitizeMetadata($actionValue, $metadata);
        $actorSnapshot = $this->snapshot->actor($actor);
        $subjectSnapshot = $this->snapshot->subject($subject, $metadata);

        $auditLog = new AuditLog([
            'actor_id' => $actor?->id,
            ...$actorSnapshot,
            'request_id' => $requestId ?? request()?->attributes->get('request_id'),
            'action' => $actionValue,
            'category' => $this->enumValue($category),
            'severity' => $this->enumValue($severity),
            'result' => $result->value,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            ...$subjectSnapshot,
            'is_elevated_access' => $isElevatedAccess,
            'metadata' => $metadata,
            'before_changes' => $this->catalog->sanitizeDeltas($actionValue, $beforeChanges),
            'after_changes' => $this->catalog->sanitizeDeltas($actionValue, $afterChanges),
            'expires_at' => $expiresAt,
        ]);
        $auditLog->public_id = (string) Str::uuid();
        $auditLog->save();

        return $auditLog;
    }

    private function enumValue(AuditAction|AuditCategory|AuditSeverity|string $value): string
    {
        return $value instanceof AuditAction || $value instanceof AuditCategory || $value instanceof AuditSeverity
            ? $value->value
            : $value;
    }

    private function assertResultMatchesAction(string $action, AuditResult $result): void
    {
        $requiredResult = match ($action) {
            AuditAction::AuthLoginFailed->value => AuditResult::Failed,
            AuditAction::SecurityAccessDenied->value => AuditResult::Denied,
            default => null,
        };

        if ($requiredResult && $result !== $requiredResult) {
            throw new InvalidArgumentException("Audit action {$action} requires result {$requiredResult->value}.");
        }
    }
}
