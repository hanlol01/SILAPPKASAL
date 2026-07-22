<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_items', function (Blueprint $table): void {
            $table->string('category_name', 100)->nullable()->after('category_id');
            $table->index(
                ['content_type', 'section_id', 'category_name'],
                'content_items_article_category_idx',
            );
        });

        Schema::table('consultation_version_contents', function (Blueprint $table): void {
            $table->string('service_type', 150)->nullable()->after('description');
            $table->text('procedure')->nullable()->after('operating_hours');
            $table->text('confidentiality_info')->nullable()->after('procedure');
        });

        DB::table('content_items')
            ->where('content_type', 'article')
            ->whereNull('category_name')
            ->whereNotNull('category_id')
            ->orderBy('id')
            ->chunkById(200, function ($items): void {
                $names = DB::table('content_categories')
                    ->whereIn('id', $items->pluck('category_id')->filter()->unique()->values())
                    ->pluck('name', 'id');

                foreach ($items as $item) {
                    $name = $names[$item->category_id] ?? null;
                    if (is_string($name) && trim($name) !== '') {
                        DB::table('content_items')
                            ->where('id', $item->id)
                            ->update(['category_name' => mb_substr(trim($name), 0, 100)]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('consultation_version_contents', function (Blueprint $table): void {
            $table->dropColumn(['service_type', 'procedure', 'confidentiality_info']);
        });

        Schema::table('content_items', function (Blueprint $table): void {
            $table->dropIndex('content_items_article_category_idx');
            $table->dropColumn('category_name');
        });
    }
};
