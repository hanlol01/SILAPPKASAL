<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->addAttributionColumns();

            return;
        }

        DB::transaction(function (): void {
            $this->mutateSqlite(fn () => $this->addAttributionColumns());
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->dropAttributionColumns();

            return;
        }

        DB::transaction(function (): void {
            $this->mutateSqlite(fn () => $this->dropAttributionColumns());
        });
    }

    private function addAttributionColumns(): void
    {
        Schema::table('content_versions', function (Blueprint $table): void {
            $table->foreignId('submitted_by')
                ->nullable()
                ->after('submitted_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('published_by')
                ->nullable()
                ->after('published_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    private function dropAttributionColumns(): void
    {
        Schema::table('content_versions', function (Blueprint $table): void {
            $table->dropForeign(['submitted_by']);
            $table->dropForeign(['published_by']);
            $table->dropColumn(['submitted_by', 'published_by']);
        });
    }

    private function mutateSqlite(Closure $mutation): void
    {
        $sqlitePointers = $this->sqlitePointerSnapshot();
        $sqliteDependents = $this->sqliteDependentSnapshot();
        $this->dropSqliteEditableDraftIndex();

        $mutation();

        $this->restoreSqlitePointers($sqlitePointers);
        $this->restoreSqliteDependents($sqliteDependents);
        $this->createSqliteEditableDraftIndex();
        $this->createSqliteLifecycleTriggers();
    }

    /** @return array<int, array{id: int, current_draft_version_id: int|null, published_version_id: int|null}> */
    private function sqlitePointerSnapshot(): array
    {
        if (DB::getDriverName() !== 'sqlite') {
            return [];
        }

        return DB::table('content_items')
            ->where(function ($pointers): void {
                $pointers->whereNotNull('current_draft_version_id')
                    ->orWhereNotNull('published_version_id');
            })
            ->get(['id', 'current_draft_version_id', 'published_version_id'])
            ->map(fn ($item): array => [
                'id' => (int) $item->id,
                'current_draft_version_id' => $item->current_draft_version_id === null
                    ? null
                    : (int) $item->current_draft_version_id,
                'published_version_id' => $item->published_version_id === null
                    ? null
                    : (int) $item->published_version_id,
            ])
            ->all();
    }

    /** @param array<int, array{id: int, current_draft_version_id: int|null, published_version_id: int|null}> $pointers */
    private function restoreSqlitePointers(array $pointers): void
    {
        foreach ($pointers as $pointer) {
            DB::table('content_items')
                ->where('id', $pointer['id'])
                ->update([
                    'current_draft_version_id' => $pointer['current_draft_version_id'],
                    'published_version_id' => $pointer['published_version_id'],
                ]);
        }
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function sqliteDependentSnapshot(): array
    {
        if (DB::getDriverName() !== 'sqlite') {
            return [];
        }

        $snapshot = [];
        foreach ($this->sqliteDependentTables() as $table) {
            $snapshot[$table] = DB::table($table)
                ->orderBy('id')
                ->get()
                ->map(fn ($row): array => (array) $row)
                ->all();
        }

        return $snapshot;
    }

    /** @param array<string, array<int, array<string, mixed>>> $snapshot */
    private function restoreSqliteDependents(array $snapshot): void
    {
        foreach ($this->sqliteDependentTables() as $table) {
            $rows = $snapshot[$table] ?? [];
            if ($rows === []) {
                continue;
            }

            $columns = array_values(array_filter(
                array_keys($rows[0]),
                fn (string $column): bool => $column !== 'id',
            ));
            DB::table($table)->upsert($rows, ['id'], $columns);
        }
    }

    /** @return array<int, string> */
    private function sqliteDependentTables(): array
    {
        return [
            'content_attachments',
            'content_review_decisions',
            'faq_version_contents',
            'consultation_version_contents',
            'article_version_contents',
        ];
    }

    private function dropSqliteEditableDraftIndex(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS content_versions_one_editable_draft');
        }
    }

    private function createSqliteEditableDraftIndex(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement(
                "CREATE UNIQUE INDEX content_versions_one_editable_draft ON content_versions (content_item_id) WHERE lifecycle_status IN ('draft', 'revision_requested')"
            );
        }
    }

    private function createSqliteLifecycleTriggers(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        $allowed = "NEW.lifecycle_status IN ('draft', 'submitted', 'in_review', 'revision_requested', 'rejected', 'approved', 'published', 'archived')";
        foreach (['insert' => 'INSERT', 'update' => 'UPDATE'] as $suffix => $operation) {
            $trigger = 'content_versions_lifecycle_check_'.$suffix;
            DB::statement("DROP TRIGGER IF EXISTS {$trigger}");
            DB::statement(
                "CREATE TRIGGER {$trigger} BEFORE {$operation} ON content_versions "
                ."FOR EACH ROW WHEN NOT ({$allowed}) "
                ."BEGIN SELECT RAISE(ABORT, 'content_versions_lifecycle_check'); END"
            );
        }
    }
};
