<?php

namespace App\Console\Commands;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditResult;
use App\Enums\AuditSeverity;
use App\Models\AuditLog;
use App\Services\AuditLogService;
use App\Support\AuditEventCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class AuditPrivacyScrub extends Command
{
    protected $signature = 'audit:privacy-scrub
        {--execute : Apply safe corrections after scanning}
        {--batch=500 : Maximum records processed per batch}
        {--resume-after=0 : Resume after this numeric internal cursor}
        {--resume-after-public-id= : Resume after a public audit identifier}';

    protected $description = 'Dry-run or execute a bounded, resume-safe audit privacy scrub';

    public function __construct(
        private readonly AuditEventCatalog $catalog,
        private readonly AuditLogService $auditLogService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $batchSize = max(1, min(1000, (int) $this->option('batch')));
        $resumeAfterPublicId = trim((string) $this->option('resume-after-public-id'));
        $cursor = $resumeAfterPublicId !== ''
            ? (int) (AuditLog::query()->where('public_id', $resumeAfterPublicId)->value('id') ?? 0)
            : max(0, (int) $this->option('resume-after'));
        $lastPublicId = $resumeAfterPublicId !== '' ? $resumeAfterPublicId : null;
        $counts = ['scanned' => 0, 'changed' => 0, 'failed' => 0];
        $reasons = [];
        $failedRows = [];

        do {
            $rows = AuditLog::query()
                ->where('id', '>', $cursor)
                ->where('action', '!=', AuditAction::AuditPrivacyScrub->value)
                ->orderBy('id')
                ->limit($batchSize)
                ->get();

            foreach ($rows as $row) {
                $cursor = (int) $row->id;
                $lastPublicId = $row->public_id;
                $counts['scanned']++;

                try {
                    $safeMetadata = $this->catalog->sanitizeMetadata($row->action, $row->metadata ?? []);
                    $safeBefore = $this->catalog->sanitizeDeltas($row->action, $row->before_changes ?? []);
                    $safeAfter = $this->catalog->sanitizeDeltas($row->action, $row->after_changes ?? []);
                    $changes = array_filter([
                        'metadata' => $safeMetadata !== ($row->metadata ?? []) ? $safeMetadata : null,
                        'before_changes' => $safeBefore !== ($row->before_changes ?? []) ? $safeBefore : null,
                        'after_changes' => $safeAfter !== ($row->after_changes ?? []) ? $safeAfter : null,
                    ], static fn (mixed $value): bool => $value !== null);

                    if ($changes !== []) {
                        $counts['changed']++;
                        $reasons['unsafe_fields_removed'] = ($reasons['unsafe_fields_removed'] ?? 0) + 1;

                        if ($execute) {
                            AuditLog::withoutEvents(fn () => AuditLog::query()->whereKey($row->id)->update($changes));
                        }
                    }
                } catch (Throwable) {
                    $counts['failed']++;
                    $reasons['unrecognized_or_invalid_event'] = ($reasons['unrecognized_or_invalid_event'] ?? 0) + 1;
                    $failedRows[] = [
                        'public_id' => $row->public_id,
                        'reason_code' => 'unrecognized_or_invalid_event',
                    ];
                }
            }
        } while ($rows->isNotEmpty());

        ksort($reasons);
        $report = [
            'mode' => $execute ? 'execute' : 'dry_run',
            'generated_at' => now()->toJSON(),
            'resume_after_public_id' => $lastPublicId,
            'counts' => $counts,
            'reason_counts' => $reasons,
            'failed_rows' => $failedRows,
        ];
        $path = 'audit-scrub/reports/'.now()->format('Ymd_His_u').'.json';
        Storage::disk('local')->put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        if ($execute) {
            $result = $counts['failed'] > 0 ? AuditResult::Failed : AuditResult::Succeeded;
            $this->auditLogService->record(
                action: AuditAction::AuditPrivacyScrub,
                category: AuditCategory::System,
                severity: $counts['failed'] > 0 ? AuditSeverity::Warning : AuditSeverity::Info,
                metadata: [
                    'scanned_count' => $counts['scanned'],
                    'changed_count' => $counts['changed'],
                    'failed_count' => $counts['failed'],
                    'reason_summary' => collect($reasons)->map(fn (int $count, string $reason): string => "{$reason}:{$count}")->implode(','),
                    'dry_run' => false,
                ],
                result: $result,
            );
        }

        $this->info(($execute ? 'Execute' : 'Dry run')." complete. Private report: {$path}");

        return $counts['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

}
