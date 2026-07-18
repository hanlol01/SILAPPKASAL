<?php

namespace App\Services;

use App\Enums\AuditActorKind;
use App\Models\AuditLog;
use App\Support\AuditEventCatalog;
use App\Support\AuditSnapshot;
use InvalidArgumentException;

final class AuditLogPresentationSanitizer
{
    public function __construct(
        private readonly AuditEventCatalog $catalog,
        private readonly AuditSnapshot $snapshot,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function sanitize(AuditLog $auditLog): array
    {
        $actor = $this->safeActor($auditLog);

        return [
            'public_id' => $auditLog->public_id,
            'request_id' => $auditLog->request_id,
            'action' => $auditLog->action,
            'category' => $auditLog->category,
            'severity' => $auditLog->severity,
            'result' => $auditLog->result,
            'actor' => [
                ...$actor,
                'label' => $this->actorLabel($actor),
            ],
            'subject' => [
                'kind' => $auditLog->subject_kind,
                'reference' => $auditLog->subject_reference_safe,
            ],
            'is_elevated_access' => (bool) $auditLog->is_elevated_access,
            'metadata' => $this->safeMetadata($auditLog),
            'changes' => [
                'before' => $this->safeDeltas($auditLog, $auditLog->before_changes ?? []),
                'after' => $this->safeDeltas($auditLog, $auditLog->after_changes ?? []),
            ],
            'created_at' => $auditLog->created_at?->toJSON(),
        ];
    }

    /** @return array<string, bool|float|int|string|null> */
    private function safeMetadata(AuditLog $auditLog): array
    {
        try {
            return $this->catalog->sanitizeMetadata($auditLog->action, $auditLog->metadata ?? []);
        } catch (InvalidArgumentException) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, bool|float|int|string|null>
     */
    private function safeDeltas(AuditLog $auditLog, array $values): array
    {
        try {
            return $this->catalog->sanitizeDeltas($auditLog->action, $values);
        } catch (InvalidArgumentException) {
            return [];
        }
    }

    /** @return array{kind: string, role_code: ?string, display_name_safe: ?string} */
    private function safeActor(AuditLog $auditLog): array
    {
        $kind = AuditActorKind::tryFrom((string) $auditLog->actor_kind)?->value
            ?? AuditActorKind::System->value;
        $roleCode = in_array($auditLog->actor_role_code, ['super_admin', 'admin', 'satgas_ppks', 'reporter'], true)
            ? $auditLog->actor_role_code
            : null;

        if ($kind !== AuditActorKind::Staff->value) {
            return [
                'kind' => $kind,
                'role_code' => $kind === AuditActorKind::Reporter->value ? 'reporter' : null,
                'display_name_safe' => null,
            ];
        }

        return [
            'kind' => $kind,
            'role_code' => $roleCode === 'reporter' ? null : $roleCode,
            'display_name_safe' => $this->snapshot->safeStaffName($auditLog->actor_display_name_safe),
        ];
    }

    /** @param array{kind: string, role_code: ?string, display_name_safe: ?string} $actor */
    private function actorLabel(array $actor): string
    {
        return match ($actor['kind']) {
            AuditActorKind::Reporter->value => 'Pelapor',
            AuditActorKind::Staff->value => $actor['display_name_safe']
                ?? __('audit.roles.'.($actor['role_code'] ?? 'staff')),
            default => 'Sistem',
        };
    }
}
