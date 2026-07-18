<?php

namespace App\Services;

use App\Exceptions\AuditExportLimitExceeded;
use App\Models\User;
use Carbon\CarbonImmutable;

final class AuditExportService
{
    public const MAX_ROWS = 10000;

    public function __construct(
        private readonly AuditLogQuery $auditQuery,
        private readonly AuditLogPresentationSanitizer $sanitizer,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{content: string, row_count: int, filename: string}
     */
    public function create(
        User $user,
        array $filters,
        CarbonImmutable $cutoff,
    ): array {
        $query = $this->auditQuery->build($user, $filters, $cutoff, excludeExportEvents: true);
        $rowCount = (clone $query)->reorder()->count();

        if ($rowCount > self::MAX_ROWS) {
            throw new AuditExportLimitExceeded($rowCount);
        }

        $stream = fopen('php://temp/maxmemory:5242880', 'w+b');

        if ($stream === false) {
            throw new \RuntimeException('Unable to create audit export stream.');
        }

        try {
            fputcsv($stream, array_values(__('audit.export.headers')));

            foreach ($query->cursor() as $auditLog) {
                $safe = $this->sanitizer->sanitize($auditLog);
                fputcsv($stream, array_map($this->csvCell(...), [
                    $safe['public_id'],
                    $safe['created_at'],
                    $safe['action'],
                    $safe['category'],
                    $safe['severity'],
                    $safe['result'],
                    $safe['actor']['label'],
                    $safe['actor']['role_code'],
                    $safe['subject']['kind'],
                    $safe['subject']['reference'],
                    $safe['is_elevated_access'] ? '1' : '0',
                    $safe['request_id'],
                ]));
            }

            rewind($stream);
            $content = stream_get_contents($stream);

            if ($content === false) {
                throw new \RuntimeException('Unable to read audit export stream.');
            }
        } finally {
            fclose($stream);
        }

        return [
            'content' => $content,
            'row_count' => $rowCount,
            'filename' => 'audit-log-'.$cutoff->format('Ymd-His').'.csv',
        ];
    }

    private function csvCell(mixed $value): string
    {
        $cell = is_scalar($value) ? (string) $value : '';

        return preg_match('/^[\s]*[=+\-@]/u', $cell) === 1 ? "'{$cell}" : $cell;
    }
}
