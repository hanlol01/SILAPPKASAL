<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, array<string, string>> */
    private const CONSTRAINTS = [
        'content_categories' => [
            'content_categories_scope_university_check' => "(scope = 'global' AND university_id IS NULL) OR (scope = 'campus' AND university_id IS NOT NULL)",
        ],
        'content_items' => [
            'content_items_scope_university_check' => "(scope = 'global' AND university_id IS NULL) OR (scope = 'campus' AND university_id IS NOT NULL)",
            'content_items_type_check' => "content_type IN ('article', 'faq', 'consultation')",
        ],
        'content_versions' => [
            'content_versions_lifecycle_check' => "lifecycle_status IN ('draft', 'submitted', 'in_review', 'revision_requested', 'rejected', 'approved', 'published', 'archived')",
        ],
        'featured_content' => [
            'featured_content_scope_university_check' => "(scope = 'global' AND university_id IS NULL) OR (scope = 'campus' AND university_id IS NOT NULL)",
            'featured_content_rank_check' => 'rank BETWEEN 1 AND 5',
            'featured_content_window_check' => 'active_from IS NULL OR active_until IS NULL OR active_from <= active_until',
        ],
    ];

    public function up(): void
    {
        match (DB::getDriverName()) {
            'pgsql' => $this->addPostgreSqlConstraints(),
            'sqlite' => $this->addSqliteConstraintTriggers(),
            default => null,
        };
    }

    public function down(): void
    {
        match (DB::getDriverName()) {
            'pgsql' => $this->dropPostgreSqlConstraints(),
            'sqlite' => $this->dropSqliteConstraintTriggers(),
            default => null,
        };
    }

    private function addPostgreSqlConstraints(): void
    {
        foreach (self::CONSTRAINTS as $table => $constraints) {
            foreach ($constraints as $name => $expression) {
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$expression})");
            }
        }
    }

    private function dropPostgreSqlConstraints(): void
    {
        foreach (self::CONSTRAINTS as $table => $constraints) {
            foreach (array_keys($constraints) as $name) {
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$name}");
            }
        }
    }

    private function addSqliteConstraintTriggers(): void
    {
        foreach (self::CONSTRAINTS as $table => $constraints) {
            foreach ($constraints as $name => $expression) {
                foreach (['insert' => 'INSERT', 'update' => 'UPDATE'] as $suffix => $operation) {
                    $trigger = $name.'_'.$suffix;
                    $newExpression = $this->sqliteNewExpression($expression);
                    DB::statement(
                        "CREATE TRIGGER {$trigger} BEFORE {$operation} ON {$table} "
                        ."FOR EACH ROW WHEN NOT ({$newExpression}) "
                        ."BEGIN SELECT RAISE(ABORT, '{$name}'); END"
                    );
                }
            }
        }
    }

    private function dropSqliteConstraintTriggers(): void
    {
        foreach (self::CONSTRAINTS as $constraints) {
            foreach (array_keys($constraints) as $name) {
                DB::statement("DROP TRIGGER IF EXISTS {$name}_insert");
                DB::statement("DROP TRIGGER IF EXISTS {$name}_update");
            }
        }
    }

    private function sqliteNewExpression(string $expression): string
    {
        return preg_replace(
            '/\b(scope|university_id|content_type|lifecycle_status|rank|active_from|active_until)\b/',
            'NEW.$1',
            $expression,
        ) ?? $expression;
    }
};
