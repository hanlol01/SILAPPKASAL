<?php

namespace App\Console\Commands;

use App\Enums\ContentLifecycleStatus;
use App\Enums\ContentType;
use App\Models\ContentCategory;
use App\Models\ContentItem;
use App\Models\ContentSection;
use App\Models\ContentVersion;
use App\Services\TestDatabaseGuard;
use Database\Seeders\Foundation\ContentFoundationSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class VerifyContentPostgreSqlMigrations extends Command
{
    protected $signature = 'content:verify-postgresql-migrations';

    protected $description = 'Verify C1 migrations and repair rollback/re-apply on guarded PostgreSQL test database';

    /** @var list<string> */
    private const REPAIR_CONSTRAINTS = [
        'content_categories_scope_university_check',
        'content_items_scope_university_check',
        'content_items_type_check',
        'content_versions_lifecycle_check',
        'featured_content_scope_university_check',
        'featured_content_rank_check',
        'featured_content_window_check',
    ];

    /** @var list<string> */
    private const REQUIRED_INDEXES = [
        'featured_content_active_rank_unique',
        'featured_content_active_item_unique',
    ];

    public function handle(TestDatabaseGuard $guard): int
    {
        $target = $guard->assertSafe();
        if (
            $target['driver'] !== 'pgsql'
            || $target['database'] !== 'silappkasal_test'
            || ! in_array($target['host'], ['127.0.0.1', 'localhost'], true)
        ) {
            throw new RuntimeException('This command runs only against guarded local PostgreSQL silappkasal_test.');
        }

        $this->line('APP_ENV='.$target['environment']);
        $this->line('DB_CONNECTION='.$target['driver']);
        $this->line('DB_HOST='.$target['host']);
        $this->line('DB_DATABASE='.$target['database']);

        Artisan::call('migrate:fresh', ['--force' => true]);
        Artisan::call('db:seed', [
            '--class' => ContentFoundationSeeder::class,
            '--force' => true,
        ]);
        $this->assertSeedCounts();
        $this->assertRequiredIndexes();
        $this->assertRepairConstraints(true);

        $migration = require database_path('migrations/2026_07_21_020000_harden_content_publication_constraints.php');
        $migration->down();
        $this->assertRepairConstraints(false);
        $migration->up();

        $this->assertSeedCounts();
        $this->assertRequiredIndexes();
        $this->assertRepairConstraints(true);

        $this->info('PostgreSQL migrate:fresh, seed, repair rollback, constraint inspection, and repair re-apply passed.');

        return self::SUCCESS;
    }

    private function assertSeedCounts(): void
    {
        $actual = [
            'sections' => ContentSection::query()->count(),
            'categories' => ContentCategory::query()->whereNotNull('stable_seed_key')->count(),
            'articles' => ContentItem::query()->where('content_type', ContentType::Article->value)->count(),
            'faqs' => ContentItem::query()->where('content_type', ContentType::Faq->value)->count(),
            'published' => ContentItem::query()->whereNotNull('published_version_id')->count(),
            'drafts' => ContentVersion::query()->where('lifecycle_status', ContentLifecycleStatus::Draft->value)->count(),
        ];
        $expected = [
            'sections' => 4,
            'categories' => 10,
            'articles' => 41,
            'faqs' => 8,
            'published' => 0,
            'drafts' => 49,
        ];

        if ($actual !== $expected) {
            throw new RuntimeException('Unexpected C1 seed verification counts: '.json_encode($actual));
        }
    }

    private function assertRepairConstraints(bool $expected): void
    {
        $count = DB::table('pg_constraint')
            ->whereIn('conname', self::REPAIR_CONSTRAINTS)
            ->count();

        if ($count !== ($expected ? count(self::REPAIR_CONSTRAINTS) : 0)) {
            throw new RuntimeException('Unexpected PostgreSQL repair constraint count: '.$count.'.');
        }
    }

    private function assertRequiredIndexes(): void
    {
        $count = DB::table('pg_indexes')
            ->where('schemaname', DB::raw('current_schema()'))
            ->whereIn('indexname', self::REQUIRED_INDEXES)
            ->count();

        if ($count !== count(self::REQUIRED_INDEXES)) {
            throw new RuntimeException('Unexpected PostgreSQL featured-content index count: '.$count.'.');
        }
    }
}
