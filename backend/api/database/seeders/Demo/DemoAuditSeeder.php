<?php

namespace Database\Seeders\Demo;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\AuditResult;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Database\Seeder;

class DemoAuditSeeder extends Seeder
{
    /**
     * @var list<array{0: AuditAction, 1: AuditCategory}>
     */
    private array $events = [
        [AuditAction::AuthLogin, AuditCategory::Auth],
        [AuditAction::ReportCreated, AuditCategory::Report],
        [AuditAction::ReportForwarded, AuditCategory::Report],
        [AuditAction::CaseAssigned, AuditCategory::Case],
        [AuditAction::CaseStatusChanged, AuditCategory::Case],
        [AuditAction::InvestigationCreated, AuditCategory::Investigation],
        [AuditAction::InvestigationActivityCreated, AuditCategory::Investigation],
        [AuditAction::InvestigationStatusChanged, AuditCategory::Investigation],
        [AuditAction::RecommendationCreated, AuditCategory::Recommendation],
        [AuditAction::RecommendationStatusChanged, AuditCategory::Recommendation],
        [AuditAction::DecisionCreated, AuditCategory::Decision],
        [AuditAction::DecisionStatusChanged, AuditCategory::Decision],
        [AuditAction::RecoveryCreated, AuditCategory::Recovery],
        [AuditAction::RecoveryStatusChanged, AuditCategory::Recovery],
        [AuditAction::EvidenceMetadataCreated, AuditCategory::Evidence],
        [AuditAction::SecurityAccessDenied, AuditCategory::Security],
    ];

    public function run(AuditLogService $auditLogService): void
    {
        $actors = User::query()->whereIn('email', [
            'superadmin@silappkasal.test',
            DemoSeed::campusEmail('admin', 'STAI-SA'),
            DemoSeed::campusEmail('satgas', 'STAI-SA'),
            DemoSeed::campusEmail('reporter', 'STAI-SA'),
        ])->get()->values();

        for ($i = 1; $i <= 120; $i++) {
            [$action, $category] = $this->events[$i % count($this->events)];
            $actor = $actors[($i - 1) % max(1, $actors->count())] ?? null;
            $requestId = sprintf('demo-request-%03d', $i);

            if (AuditLog::query()->where('request_id', $requestId)->where('action', $action->value)->exists()) {
                continue;
            }

            $auditLog = $auditLogService->record(
                action: $action,
                category: $category,
                severity: $category === AuditCategory::Security ? AuditSeverity::Warning : AuditSeverity::Info,
                actor: $actor,
                metadata: $action === AuditAction::SecurityAccessDenied
                    ? ['operation_code' => 'demo.audit', 'reason_code' => 'demo_denied']
                    : [],
                requestId: $requestId,
                result: $action === AuditAction::SecurityAccessDenied
                    ? AuditResult::Denied
                    : AuditResult::Succeeded,
            );

            $auditLog->forceFill(['created_at' => DemoSeed::date($i % 30)])->save();
        }
    }
}
