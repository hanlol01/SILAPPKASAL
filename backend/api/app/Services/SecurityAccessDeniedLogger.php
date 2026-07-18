<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditResult;
use App\Enums\AuditSeverity;
use App\Support\SensitiveAuditOperation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class SecurityAccessDeniedLogger
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    public function record(
        Request $request,
        string $reasonCode = 'authorization_denied',
        ?string $operationCode = null,
    ): void
    {
        $operationCode ??= SensitiveAuditOperation::fromRouteName($request->route()?->getName());

        if (! $operationCode) {
            return;
        }

        $actor = $request->user();
        $deduplicationKey = 'audit-denial:'.hash('sha256', implode('|', [
            $operationCode,
            (string) ($actor?->id ?? 'anonymous'),
            (string) ($actor?->role_id ?? 'no-role'),
            $reasonCode,
        ]));

        try {
            $deduplicationMinutes = max(1, (int) config('audit.security_denial_deduplication_minutes', 5));

            if (! Cache::add($deduplicationKey, true, now()->addMinutes($deduplicationMinutes))) {
                return;
            }

            $this->auditLogService->record(
                action: AuditAction::SecurityAccessDenied,
                category: AuditCategory::Security,
                severity: AuditSeverity::Warning,
                actor: $actor,
                metadata: [
                    'operation_code' => $operationCode,
                    'reason_code' => $reasonCode,
                ],
                result: AuditResult::Denied,
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
