<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const UNIQUE_INDEX = 'content_categories_scope_normalized_name_unique';

    public function up(): void
    {
        $this->assertPreflightIntegrity();

        Schema::table('content_categories', function (Blueprint $table): void {
            $table->string('normalized_name', 150)->default('')->after('name');
        });

        DB::table('content_categories')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->chunkById(200, function ($categories): void {
                foreach ($categories as $category) {
                    DB::table('content_categories')
                        ->where('id', $category->id)
                        ->update(['normalized_name' => $this->normalize((string) $category->name)]);
                }
            });

        Schema::table('content_categories', function (Blueprint $table): void {
            $table->unique(
                ['section_id', 'scope_key', 'normalized_name'],
                self::UNIQUE_INDEX
            );
        });
    }

    public function down(): void
    {
        Schema::table('content_categories', function (Blueprint $table): void {
            $table->dropUnique(self::UNIQUE_INDEX);
            $table->dropColumn('normalized_name');
        });
    }

    private function normalize(string $name): string
    {
        if (class_exists(Normalizer::class)) {
            $normalized = Normalizer::normalize($name, Normalizer::FORM_C);

            if (is_string($normalized)) {
                $name = $normalized;
            }
        }

        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $name)));
    }

    private function assertPreflightIntegrity(): void
    {
        $seen = [];

        DB::table('content_categories')
            ->select(['id', 'section_id', 'scope', 'scope_key', 'university_id', 'name'])
            ->orderBy('id')
            ->chunkById(200, function ($categories) use (&$seen): void {
                foreach ($categories as $category) {
                    $expectedScopeKey = match ($category->scope) {
                        'global' => $category->university_id === null ? 'global' : null,
                        'campus' => (int) $category->university_id > 0
                            ? 'campus:'.(int) $category->university_id
                            : null,
                        default => null,
                    };

                    if ($expectedScopeKey === null || $category->scope_key !== $expectedScopeKey) {
                        throw new RuntimeException(
                            "Content category {$category->id} has an invalid scope_key; expected "
                            .($expectedScopeKey ?? 'a valid scope/university combination')
                            .'.'
                        );
                    }

                    $normalizedName = $this->normalize((string) $category->name);
                    $identity = json_encode([
                        (int) $category->section_id,
                        $category->scope_key,
                        $normalizedName,
                    ], JSON_THROW_ON_ERROR);

                    if (isset($seen[$identity])) {
                        throw new RuntimeException(
                            "Duplicate normalized content category names found for rows {$seen[$identity]} "
                            ."and {$category->id} in the same section and scope."
                        );
                    }

                    $seen[$identity] = (int) $category->id;
                }
            });
    }
};
