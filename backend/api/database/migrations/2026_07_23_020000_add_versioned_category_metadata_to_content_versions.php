<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertNoAmbiguousArticlePointers();
        $sqlitePointers = $this->sqlitePointerSnapshot();
        $sqliteDependents = $this->sqliteDependentSnapshot();
        $this->dropSqliteEditableDraftIndex();

        Schema::table('content_versions', function (Blueprint $table): void {
            $table->string('category_name', 100)->nullable()->after('excerpt');
            $table->foreignId('category_id')
                ->nullable()
                ->after('category_name')
                ->constrained('content_categories')
                ->restrictOnDelete();
        });

        $this->restoreSqlitePointers($sqlitePointers);
        $this->restoreSqliteDependents($sqliteDependents);
        $this->createSqliteEditableDraftIndex();
        $this->createSqliteLifecycleTriggers();

        DB::table('content_items')
            ->where('content_type', 'article')
            ->where(function ($pointers): void {
                $pointers->whereNotNull('published_version_id')
                    ->orWhereNotNull('current_draft_version_id');
            })
            ->select([
                'id',
                'public_id',
                'published_version_id',
                'current_draft_version_id',
                'category_name',
                'category_id',
            ])
            ->orderBy('id')
            ->chunkById(200, function ($items): void {
                foreach ($items as $item) {
                    $pointerIds = collect([
                        $item->published_version_id,
                        $item->current_draft_version_id,
                    ])->filter()->unique()->values();

                    if ($pointerIds->count() > 1) {
                        throw new RuntimeException(
                            "Ambiguous Article category pointers detected after preflight for content item {$item->public_id}."
                        );
                    }

                    $categoryName = is_string($item->category_name)
                        ? mb_substr(trim($item->category_name), 0, 100)
                        : null;
                    $categoryName = $categoryName === '' ? null : $categoryName;

                    DB::table('content_versions')
                        ->whereIn('id', $pointerIds)
                        ->where('content_item_id', $item->id)
                        ->whereNull('category_name')
                        ->whereNull('category_id')
                        ->update([
                            'category_name' => $categoryName,
                            'category_id' => $categoryName === null ? $item->category_id : null,
                        ]);
                }
            });
    }

    public function down(): void
    {
        $sqlitePointers = $this->sqlitePointerSnapshot();
        $sqliteDependents = $this->sqliteDependentSnapshot();
        $this->dropSqliteEditableDraftIndex();

        Schema::table('content_versions', function (Blueprint $table): void {
            $table->dropForeign(['category_id']);
            $table->dropColumn(['category_name', 'category_id']);
        });

        $this->restoreSqlitePointers($sqlitePointers);
        $this->restoreSqliteDependents($sqliteDependents);
        $this->createSqliteEditableDraftIndex();
        $this->createSqliteLifecycleTriggers();
    }

    private function assertNoAmbiguousArticlePointers(): void
    {
        $ambiguous = DB::table('content_items')
            ->where('content_type', 'article')
            ->whereNotNull('published_version_id')
            ->whereNotNull('current_draft_version_id')
            ->whereColumn('published_version_id', '<>', 'current_draft_version_id');

        $count = (clone $ambiguous)->count();
        if ($count === 0) {
            return;
        }

        $publicIds = (clone $ambiguous)
            ->orderBy('id')
            ->limit(5)
            ->pluck('public_id')
            ->implode(', ');

        throw new RuntimeException(
            "Cannot add versioned Article categories: {$count} item(s) have distinct published and current draft pointers without versioned category metadata. "
            ."Ambiguous public_id sample: {$publicIds}. Resolve them before rerunning the migration."
        );
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
        // Attachments must be restored before Article rows because cover_attachment_id
        // references content_attachments. The remaining tables only reference versions.
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
