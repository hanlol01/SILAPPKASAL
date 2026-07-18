<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditResult;
use App\Enums\AuditSeverity;
use App\Support\AuditFingerprint;
use Illuminate\Http\Request;
use Throwable;

final class LoginFailureAuditService
{
    public function __construct(
        private readonly AuditFingerprint $fingerprint,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function record(string $identifier, ?Request $request = null): void
    {
        $request ??= request();
        $fingerprints = $this->fingerprint->loginFailure($identifier, $request?->ip());

        if (! $fingerprints) {
            return;
        }

        try {
            $this->auditLogService->record(
                action: AuditAction::AuthLoginFailed,
                category: AuditCategory::Auth,
                severity: AuditSeverity::Warning,
                metadata: [
                    'fingerprint_version' => $fingerprints['version'],
                    'identifier_fingerprint' => $fingerprints['identifier'],
                    'network_fingerprint' => $fingerprints['network'],
                    'reason_code' => 'invalid_credentials',
                ],
                result: AuditResult::Failed,
                expiresAt: now()->addDays(max(1, min(7, (int) config('audit.login_failure_retention_days', 7)))),
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
